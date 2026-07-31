<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\DTOs\ImportScheduleBlocksResult;
use App\Domain\Scheduling\Enums\ScheduleStatus;
use App\Domain\Scheduling\Exceptions\InvalidScheduleCsvException;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use App\Domain\Scheduling\Services\ScheduleCsvParser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Imports a full planning export as a new immutable schedule version. This
 * intentionally does not overwrite the current schedule or contact the ERP;
 * the customer remains in control of their source system.
 */
class ImportScheduleBlocksAction
{
    public function __construct(
        private readonly ScheduleCsvParser $parser,
        private readonly AuditLogService $auditLogService,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /** @throws InvalidScheduleCsvException */
    public function execute(Organization $organization, User $user, string $csvContents, string $filename): ImportScheduleBlocksResult
    {
        $rows = $this->parser->parse($csvContents);
        $previousOrganization = $this->currentOrganization->get();
        $this->currentOrganization->set($organization);

        try {
            return DB::transaction(function () use ($organization, $user, $rows, $filename) {
                // Serialise imports per organization before calculating the
                // successor. Locking only the newest schedule is insufficient
                // for two concurrent *first* imports, where no schedule row
                // exists yet for either transaction to lock.
                Organization::query()
                    ->whereKey($organization->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $previous = ProductionSchedule::query()
                    ->where('organization_id', $organization->id)
                    ->orderByDesc('version')
                    ->first();

                $schedule = ProductionSchedule::create([
                    'organization_id' => $organization->id,
                    'version' => $previous ? $previous->version + 1 : 1,
                    'status' => ScheduleStatus::Draft,
                    'submitted_by_id' => $user->id,
                ]);

                /** @var array<string, Site> $sites */
                $sites = [];
                foreach ($rows as $row) {
                    $key = mb_strtolower($row->siteName);
                    $site = $sites[$key] ??= Site::query()->firstOrCreate(
                        ['organization_id' => $organization->id, 'name' => $row->siteName],
                    );

                    ScheduleBlock::create([
                        'schedule_id' => $schedule->id,
                        'site_id' => $site->id,
                        'asset_label' => $row->assetLabel,
                        'start_at' => $row->startAt,
                        'end_at' => $row->endAt,
                        'description' => $row->description,
                        'estimated_kwh' => $row->estimatedKwh,
                        'labour_classification' => $row->labourClassification,
                    ]);
                }

                if ($previous) {
                    $previous->update(['status' => ScheduleStatus::Superseded]);
                }

                $this->auditLogService->record(new AuditEvent(
                    actorType: AuditActorType::User,
                    actorId: $user->id,
                    action: 'schedule.imported',
                    entityType: 'ProductionSchedule',
                    entityId: $schedule->id,
                    organizationId: $organization->id,
                    metadata: [
                        'filename' => $filename,
                        'rowCount' => count($rows),
                        'previousVersion' => $previous?->version,
                        'newVersion' => $schedule->version,
                    ],
                ));

                return new ImportScheduleBlocksResult($schedule->refresh(), count($rows));
            });
        } finally {
            $this->currentOrganization->set($previousOrganization);
        }
    }
}
