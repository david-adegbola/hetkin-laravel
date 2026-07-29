<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\Enums\LabourClassification;
use App\Domain\Scheduling\Enums\ScheduleStatus;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's addScheduleBlock() Server Action (src/app/
 * (portal)/dashboard/schedule/actions.ts) — manual schedule entry
 * (hetkin-phase5-roadmap.md M1: "manual schedule import (CSV)" — this is
 * the form-entry fallback of that; a real CSV/ERP importer is a follow-up
 * once there's a sample export to build against, same reasoning as
 * FingridCsvParser existing because a real Fingrid export format did).
 *
 * Input validation (required fields, valid dates, end after start,
 * positive estimated kWh) is the caller's Form Request's job — this Action
 * assumes already-valid primitives, same division of responsibility as
 * every other Action in this app.
 *
 * Ensures a default Site exists and reuses (or creates) the organization's
 * latest ProductionSchedule version rather than requiring separate setup
 * steps first, matching v2's exact bootstrap behavior — see
 * UploadMeterReadingsAction's docblock for the same pattern in the
 * metering domain. Also binds CurrentOrganization for the duration of the
 * call (MIGRATION_PLAN.md ADR-31) since Site is BelongsToOrganization-scoped.
 *
 * src/lib/onboarding-status.ts's cache invalidation is omitted — no
 * onboarding checklist exists yet in this port, not silently dropped; see
 * MIGRATION_PLAN.md ADR-29/32.
 *
 * `$labourClassification` defaults to LabourClassification::LabourBound —
 * matching the creating migration's DB-level default, not overriding it
 * more permissively — so a caller (a future API integration, a bulk
 * importer) that omits the argument gets the same conservative behavior
 * as a raw INSERT would. The portal's own form (StoreScheduleBlockRequest)
 * always sends an explicit value; this default exists for every other
 * caller.
 */
class AddScheduleBlockAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function execute(
        Organization $organization,
        User $user,
        string $assetLabel,
        Carbon $startAt,
        Carbon $endAt,
        ?float $estimatedKwh,
        LabourClassification $labourClassification = LabourClassification::LabourBound,
    ): ScheduleBlock {
        $previousOrganization = $this->currentOrganization->get();
        $this->currentOrganization->set($organization);

        try {
            return DB::transaction(function () use ($organization, $user, $assetLabel, $startAt, $endAt, $estimatedKwh, $labourClassification) {
                $site = Site::query()->firstWhere('organization_id', $organization->id);
                if (! $site) {
                    $site = Site::create([
                        'organization_id' => $organization->id,
                        'name' => 'Main site',
                    ]);
                }

                $schedule = ProductionSchedule::query()
                    ->where('organization_id', $organization->id)
                    ->orderByDesc('version')
                    ->first();
                if (! $schedule) {
                    $schedule = ProductionSchedule::create([
                        'organization_id' => $organization->id,
                        'version' => 1,
                        'status' => ScheduleStatus::Draft,
                        'submitted_by_id' => $user->id,
                    ]);
                }

                $block = ScheduleBlock::create([
                    'schedule_id' => $schedule->id,
                    'site_id' => $site->id,
                    'asset_label' => $assetLabel,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'estimated_kwh' => $estimatedKwh,
                    'labour_classification' => $labourClassification,
                ]);

                $this->auditLogService->record(new AuditEvent(
                    actorType: AuditActorType::User,
                    actorId: $user->id,
                    action: 'schedule_block.created',
                    entityType: 'ScheduleBlock',
                    entityId: $block->id,
                    organizationId: $organization->id,
                ));

                return $block;
            });
        } finally {
            $this->currentOrganization->set($previousOrganization);
        }
    }
}
