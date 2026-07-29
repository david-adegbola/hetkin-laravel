<?php

namespace App\Domain\Metering\Actions;

use App\Domain\Analytics\Services\PostHogService;
use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Metering\DTOs\UploadMeterReadingsResult;
use App\Domain\Metering\Enums\MeterDataSource;
use App\Domain\Metering\Exceptions\InvalidMeterCsvException;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\Site;
use App\Domain\Metering\Services\FingridCsvParser;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ports the Next.js app's uploadMeterCsv() Server Action (src/app/(portal)/
 * dashboard/data/upload/actions.ts) — the parsing, bootstrap, and write
 * concerns only. Deliberately excludes what belongs to the HTTP boundary
 * once Stage 5's portal shell adds a real upload page/controller:
 *   - the uploaded-file-shaped checks (file present, size <= 10MB) — this
 *     Action takes plain CSV text, not a file-upload object, the same way
 *     every other Action in this app is kept HTTP-agnostic;
 *   - session/membership resolution — takes an already-resolved
 *     Organization + User, the caller's job (see
 *     App\Domain\Organizations\Services\OrganizationContextResolver);
 *   - src/lib/onboarding-status.ts's cache invalidation — no onboarding
 *     checklist exists yet in this port, omitted as not yet built, not
 *     silently dropped; see MIGRATION_PLAN.md ADR-29.
 *
 * Ensures a default Site + MeteringPoint exist for the organization rather
 * than requiring a separate "add a site" step first, matching v2's own
 * "Main site" / "Manual upload" bootstrap exactly — most pilot accounts
 * start with one site, and a real multi-site UI is a later stage's concern
 * there too.
 */
class UploadMeterReadingsAction
{
    /**
     * Matches v2's UPSERT_BATCH_SIZE exactly: Postgres's wire protocol caps
     * a statement at 65,535 bound parameters, and 2,000 rows stays
     * comfortably under any reasonable per-row parameter count while still
     * turning a 50,000-row upload (FingridCsvParser's own row cap) into
     * ~25 round trips instead of one per row.
     */
    private const UPSERT_BATCH_SIZE = 2000;

    public function __construct(
        private readonly FingridCsvParser $parser,
        private readonly AuditLogService $auditLogService,
        private readonly CurrentOrganization $currentOrganization,
        private readonly PostHogService $postHog,
    ) {}

    /**
     * @throws InvalidMeterCsvException
     */
    public function execute(
        Organization $organization,
        User $user,
        string $csvContents,
        string $filename,
    ): UploadMeterReadingsResult {
        // Parsing happens BEFORE the transaction opens, same as v2 (parse,
        // then write) — a malformed file fails fast without ever touching
        // the database.
        $rows = $this->parser->parse($csvContents);

        // This Action is given its organization explicitly rather than
        // reading it from ambient request state, but the Site query below
        // still goes through BelongsToOrganization's fail-closed scope
        // (MIGRATION_PLAN.md ADR-26) like any other query against that
        // model — so CurrentOrganization has to actually be bound for the
        // duration of this call, not just "known" to this method. Binding
        // it here (and restoring whatever was bound before, rather than
        // assuming nothing was) keeps this Action correct whether it's
        // called directly, as in its tests, or from inside an HTTP request
        // that already bound a — possibly different — organization.
        $previousOrganization = $this->currentOrganization->get();
        $this->currentOrganization->set($organization);

        try {
            $result = DB::transaction(function () use ($organization, $user, $rows, $filename) {
                $site = Site::query()->firstWhere('organization_id', $organization->id);
                if (! $site) {
                    $site = Site::create([
                        'organization_id' => $organization->id,
                        'name' => 'Main site',
                    ]);
                }

                $meteringPoint = MeteringPoint::query()
                    ->where('site_id', $site->id)
                    ->where('data_source', MeterDataSource::ManualUpload)
                    ->first();
                if (! $meteringPoint) {
                    $meteringPoint = MeteringPoint::create([
                        'site_id' => $site->id,
                        'label' => 'Manual upload',
                        'data_source' => MeterDataSource::ManualUpload,
                    ]);
                }

                foreach (array_chunk($rows, self::UPSERT_BATCH_SIZE) as $batch) {
                    $values = array_map(fn ($row) => [
                        'id' => (string) Str::ulid(),
                        'metering_point_id' => $meteringPoint->id,
                        'hour_start' => $row->hourStart,
                        'consumption_kwh' => $row->consumptionKwh,
                        'source' => MeterDataSource::ManualUpload->value,
                        'created_at' => now(),
                    ], $batch);

                    DB::table('meter_readings')->upsert(
                        $values,
                        ['metering_point_id', 'hour_start'],
                        ['consumption_kwh'],
                    );
                }

                $lastReading = end($rows)->hourStart;
                $meteringPoint->update([
                    'last_reading_at' => $lastReading,
                    'connection_healthy' => true,
                ]);

                $this->auditLogService->record(new AuditEvent(
                    actorType: AuditActorType::User,
                    actorId: $user->id,
                    action: 'meter_data.uploaded',
                    entityType: 'MeteringPoint',
                    entityId: $meteringPoint->id,
                    organizationId: $organization->id,
                    metadata: ['rowCount' => count($rows), 'filename' => $filename],
                ));

                return new UploadMeterReadingsResult(
                    rowsWritten: count($rows),
                    meteringPoint: $meteringPoint->refresh(),
                );
            });

            // Phase 1 §8 funnel: "Onboarding → First Data Upload".
            $this->postHog->capture('meter_data_uploaded', $user->id, [
                'organizationId' => $organization->id,
                'rowCount' => $result->rowsWritten,
            ]);

            return $result;
        } finally {
            $this->currentOrganization->set($previousOrganization);
        }
    }
}
