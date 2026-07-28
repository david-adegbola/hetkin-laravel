<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Optimization\Actions\ApplyDecisionsToNewScheduleVersionAction;
use App\Domain\Optimization\Actions\RunOptimizationAction;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Exceptions\OptimizationActionException;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
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
 * history/page.tsx} + actions.ts's addScheduleBlock()/runOptimization()/
 * applyDecisionsToNewScheduleVersion().
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

        $pendingDecisionCount = $schedule
            ? RecommendationDecision::query()
                ->whereIn('decision', [RecommendationDecisionType::Accepted, RecommendationDecisionType::Partial])
                ->whereHas('recommendation.scheduleBlock', fn ($query) => $query->where('schedule_id', $schedule->id))
                ->count()
            : 0;

        return Inertia::render('Portal/Schedule', [
            'schedule' => $schedule ? [
                'version' => $schedule->version,
                'status' => $schedule->status->value,
                'hasBlocksWithEstimatedKwh' => $schedule->blocks->contains(fn ($block) => $block->estimated_kwh !== null),
                'blocks' => $schedule->blocks->map(fn ($block) => [
                    'id' => $block->id,
                    'site_name' => $block->site->name,
                    'asset_label' => $block->asset_label,
                    'start_at' => $block->start_at->toIso8601String(),
                    'end_at' => $block->end_at->toIso8601String(),
                    'estimated_kwh' => $block->estimated_kwh !== null ? (float) $block->estimated_kwh : null,
                ]),
            ] : null,
            'pendingDecisionCount' => $pendingDecisionCount,
            // Explicit forwarding, not assumed automatic — see
            // MIGRATION_PLAN.md ADR-20/ADR-36.
            'optimizationStatus' => session('optimizationStatus'),
            'applyDecisionsStatus' => session('applyDecisionsStatus'),
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

    public function optimize(
        RunOptimizationAction $action,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        try {
            $run = $action->execute($currentOrganization->get(), $user);
        } catch (OptimizationActionException $e) {
            return back()->withErrors(['optimization' => $e->getMessage()]);
        }

        $recommendationCount = Recommendation::query()->where('optimization_run_id', $run->id)->count();

        return back()->with('optimizationStatus', ['recommendationCount' => $recommendationCount]);
    }

    public function applyDecisions(
        ApplyDecisionsToNewScheduleVersionAction $action,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        try {
            $result = $action->execute($currentOrganization->get(), $user);
        } catch (OptimizationActionException $e) {
            return back()->withErrors(['applyDecisions' => $e->getMessage()]);
        }

        return back()->with('applyDecisionsStatus', [
            'newVersion' => $result->newVersion,
            'blocksChanged' => $result->blocksChanged,
        ]);
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
