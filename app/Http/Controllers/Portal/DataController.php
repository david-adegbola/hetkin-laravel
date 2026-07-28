<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Metering\Actions\UploadMeterReadingsAction;
use App\Domain\Metering\Exceptions\InvalidMeterCsvException;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreMeterUploadRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/data/{page.tsx,
 * upload/page.tsx} + upload/actions.ts's uploadMeterCsv() — the one
 * portal domain page in this stage with no unbuilt-model dependency (see
 * DashboardController's docblock for the pages that do have one).
 */
class DataController extends Controller
{
    public function index(): Response
    {
        $sites = Site::query()->with('meteringPoints')->get();
        $meteringPoints = $sites
            ->flatMap(fn (Site $site) => $site->meteringPoints->map(fn ($point) => [
                'id' => $point->id,
                'label' => $point->label,
                'site_name' => $site->name,
                'data_source' => $point->data_source->value,
                'connection_healthy' => $point->connection_healthy,
                'last_reading_at' => $point->last_reading_at?->toIso8601String(),
            ]))
            ->values();

        return Inertia::render('Portal/Data', [
            'meteringPoints' => $meteringPoints,
        ]);
    }

    public function create(): Response
    {
        // Forwarding session('status') explicitly, not assuming Inertia
        // does this automatically — a real bug from the Account Security
        // module (MIGRATION_PLAN.md ADR-20) was exactly this omission on a
        // sibling create()/store() pair.
        return Inertia::render('Portal/DataUpload', [
            'status' => session('status'),
        ]);
    }

    public function store(
        StoreMeterUploadRequest $request,
        UploadMeterReadingsAction $action,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $file = $request->file('file');

        try {
            $result = $action->execute(
                $currentOrganization->get(),
                $user,
                $file->get(),
                $file->getClientOriginalName(),
            );
        } catch (InvalidMeterCsvException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('status', [
            'rowsWritten' => $result->rowsWritten,
        ]);
    }
}
