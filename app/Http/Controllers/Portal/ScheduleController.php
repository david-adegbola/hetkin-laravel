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
use App\Domain\Scheduling\Enums\LabourClassification;
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
                // Renamed from hasBlocksWithEstimatedKwh — a block also
                // needs to be Unmanned to actually be considered when
                // running optimization now (ScheduleBlock::
                // isEligibleForOptimization()), so the "why can't I
                // optimize" hint on the frontend needs the real answer,
                // not just the kWh half of it.
                'hasEligibleBlocks' => $schedule->blocks->contains(fn ($block) => $block->isEligibleForOptimization()),
                'blocks' => $schedule->blocks->map(fn ($block) => [
                    'id' => $block->id,
                    'site_name' => $block->site->name,
                    'asset_label' => $block->asset_label,
                    'start_at' => $block->start_at->toIso8601String(),
                    'end_at' => $block->end_at->toIso8601String(),
                    'estimated_kwh' => $block->estimated_kwh !== null ? (float) $block->estimated_kwh : null,
                    'labour_classification' => $block->labour_classification->value,
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

        // The browser's native <input type="datetime-local"> sends a
        // timezone-naive string (e.g. "2026-07-31T07:00") — the site's
        // physical wall-clock time, with no offset information at all.
        // Carbon::parse() with no timezone argument would interpret that
        // string as config('app.timezone') (UTC), silently corrupting
        // "7am in Finland" into "7am UTC" — a real bug a live pilot user
        // hit. Parsing in config('scheduling.timezone') first, then
        // converting to UTC, is what actually stores the moment the user
        // meant; see config/scheduling.php's own docblock for why this is
        // a separate setting from the app's UTC system timezone.
        $timezone = config('scheduling.timezone');

        $action->execute(
            $currentOrganization->get(),
            $user,
            $request->string('asset_label')->toString(),
            Carbon::parse($request->string('start_at')->toString(), $timezone)->utc(),
            Carbon::parse($request->string('end_at')->toString(), $timezone)->utc(),
            $request->filled('estimated_kwh') ? (float) $request->input('estimated_kwh') : null,
            LabourClassification::from($request->string('labour_classification')->toString()),
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
                    'labour_classification' => $block->labour_classification->value,
                ]),
            ]),
        ]);
    }
}
