<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Optimization\Actions\DecideRecommendationAction;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Exceptions\OptimizationActionException;
use App\Domain\Organizations\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\DecideRecommendationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/actions.ts's
 * decideRecommendation() Server Action, called from
 * RecommendationDecisionButtons on the dashboard.
 */
class RecommendationController extends Controller
{
    public function decide(
        DecideRecommendationRequest $request,
        string $recommendation,
        DecideRecommendationAction $action,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        try {
            $action->execute(
                $currentOrganization->get(),
                $user,
                $recommendation,
                RecommendationDecisionType::from($request->string('decision')->toString()),
                $request->input('reason'),
                // Same timezone-naive <input type="datetime-local"> shape,
                // and the same fix, as ScheduleController::store() — see
                // its own comment. This one carries the extra step of
                // RecommendationDecisionButtons.tsx's toDatetimeLocalValue()
                // pre-filling that input from an already-UTC ISO string, so
                // both ends of this round trip need to agree on
                // config('scheduling.timezone'), not just this write side.
                $request->filled('adjusted_start_at')
                    ? Carbon::parse($request->string('adjusted_start_at')->toString(), config('scheduling.timezone'))->utc()
                    : null,
                $request->filled('duration_minutes') ? (int) $request->input('duration_minutes') : null,
            );
        } catch (OptimizationActionException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('status', 'decision-recorded');
    }
}
