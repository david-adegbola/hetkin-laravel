<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Scheduling\Actions\AddScheduleBlockAction;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreScheduleBlockRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/schedule/{page.tsx,
 * history/page.tsx} + actions.ts's addScheduleBlock(). Deliberately does
 * NOT include runOptimization()/applyDecisionsToNewScheduleVersion() or
 * their pending-decision-count display — both depend on the Optimization/
 * Recommendations domain (Stage 6), which doesn't exist yet. See
 * DashboardController's docblock and MIGRATION_PLAN.md ADR-33 for the same
 * reasoning applied here.
 */
class ScheduleController extends Controller
{
    public function index(): Response
    {
        /** @var ProductionSchedule|null $schedule */
        $schedule = ProductionSchedule::query()
            ->orderByDesc('version')
            ->with(['blocks' => fn ($query) => $query->orderBy('start_at'), 'blocks.site'])
            ->first();

        return Inertia::render('Portal/Schedule', [
            'schedule' => $schedule ? [
                'version' => $schedule->version,
                'status' => $schedule->status->value,
                'blocks' => $schedule->blocks->map(fn ($block) => [
                    'id' => $block->id,
                    'site_name' => $block->site->name,
                    'asset_label' => $block->asset_label,
                    'start_at' => $block->start_at->toIso8601String(),
                    'end_at' => $block->end_at->toIso8601String(),
                    'estimated_kwh' => $block->estimated_kwh !== null ? (float) $block->estimated_kwh : null,
                ]),
            ] : null,
        ]);
    }

    public function store(
        StoreScheduleBlockRequest $request,
        AddScheduleBlockAction $action,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $action->execute(
            $currentOrganization->get(),
            $user,
            $request->string('asset_label')->toString(),
            Carbon::parse($request->string('start_at')->toString()),
            Carbon::parse($request->string('end_at')->toString()),
            $request->filled('estimated_kwh') ? (float) $request->input('estimated_kwh') : null,
        );

        return back()->with('status', 'schedule-block-added');
    }

    public function history(): Response
    {
        $versions = ProductionSchedule::query()
            ->orderByDesc('version')
            ->with(['submittedBy:id,name,email', 'blocks' => fn ($query) => $query->orderBy('start_at'), 'blocks.site'])
            ->get();

        return Inertia::render('Portal/ScheduleHistory', [
            'versions' => $versions->map(fn (ProductionSchedule $version) => [
                'id' => $version->id,
                'version' => $version->version,
                'status' => $version->status->value,
                'created_at' => $version->created_at->toIso8601String(),
                'submitted_by' => $version->submittedBy
                    ? ($version->submittedBy->name ?? $version->submittedBy->email)
                    : null,
                'blocks' => $version->blocks->map(fn ($block) => [
                    'id' => $block->id,
                    'site_name' => $block->site->name,
                    'asset_label' => $block->asset_label,
                    'start_at' => $block->start_at->toIso8601String(),
                    'end_at' => $block->end_at->toIso8601String(),
                    'estimated_kwh' => $block->estimated_kwh !== null ? (float) $block->estimated_kwh : null,
                ]),
            ]),
        ]);
    }
}
