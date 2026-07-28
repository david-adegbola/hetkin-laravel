<?php

namespace App\Http\Controllers;

use App\Domain\Pilots\Actions\SubmitPilotApplicationAction;
use App\Http\Requests\PilotApplicationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/pilot-program/apply/{page.tsx,
 * actions.ts}. Fully public, unauthenticated — the only thing standing
 * between this and a scripted flood of PilotApplication rows is the
 * `pilot-application` rate limiter (routes/web.php).
 */
class PilotApplicationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('PilotProgram/Apply', [
            'status' => session('status'),
        ]);
    }

    public function store(PilotApplicationRequest $request, SubmitPilotApplicationAction $action): RedirectResponse
    {
        $action->execute($request->validated());

        return back()->with('status', 'application-submitted');
    }
}
