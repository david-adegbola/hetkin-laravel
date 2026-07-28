<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Actions\LockBaselineAction;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/baselines/
 * {page.tsx,actions.ts}. The index has no role gate; locking is
 * `admin.role:ADMIN,VERIFICATION_ANALYST`, matching v2's
 * `requireAdminRole(["ADMIN", "VERIFICATION_ANALYST"])` exactly.
 */
class BaselineController extends Controller
{
    public function index(): Response
    {
        $baselines = Baseline::withoutGlobalScope(OrganizationScope::class)
            ->with('organization')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Baselines', [
            'baselines' => $baselines->map(fn (Baseline $baseline) => [
                'id' => $baseline->id,
                'organization_id' => $baseline->organization_id,
                'organization_name' => $baseline->organization->name,
                'methodology_version' => $baseline->methodology_version,
                'baseline_annual_cost_cents' => $baseline->baseline_annual_cost_cents,
                'status' => $baseline->status->value,
                'created_at' => $baseline->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function lock(string $baseline, LockBaselineAction $action): RedirectResponse
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        $action->execute($baseline, $admin);

        return back();
    }
}
