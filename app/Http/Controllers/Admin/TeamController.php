<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Actions\ChangeAdminRoleAction;
use App\Domain\Auth\Actions\CreateAdminUserAction;
use App\Domain\Auth\Actions\DeactivateAdminUserAction;
use App\Domain\Auth\Actions\ReactivateAdminUserAction;
use App\Domain\Auth\Exceptions\AdminTeamActionException;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/team/
 * {page.tsx,actions.ts,CreateAdminUserForm.tsx}. Every mutation here is
 * `admin.role:ADMIN` only. The index itself has no role gate — any active
 * admin can view the roster, matching v2 (the page only conditionally
 * renders the mutation controls for non-ADMIN viewers; this port applies
 * the same read/write split at the route layer instead).
 */
class TeamController extends Controller
{
    public function index(): Response
    {
        $admins = AdminUser::query()->orderBy('name')->get();

        return Inertia::render('Admin/Team', [
            'admins' => $admins->map(fn (AdminUser $admin) => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role->value,
                'active' => $admin->active,
                'created_at' => $admin->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request, CreateAdminUserAction $action): RedirectResponse
    {
        try {
            $result = $action->execute(
                $this->admin(),
                (string) $request->input('name'),
                (string) $request->input('email'),
                (string) $request->input('role'),
            );
        } catch (AdminTeamActionException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        return back()->with('created_admin', [
            'email' => $result->adminUser->email,
            'temp_password' => $result->tempPassword,
        ]);
    }

    public function changeRole(Request $request, AdminUser $adminUser, ChangeAdminRoleAction $action): RedirectResponse
    {
        try {
            $action->execute($adminUser, $this->admin(), (string) $request->input('role'));
        } catch (AdminTeamActionException $e) {
            throw ValidationException::withMessages(['role' => $e->getMessage()]);
        }

        return back();
    }

    public function deactivate(AdminUser $adminUser, DeactivateAdminUserAction $action): RedirectResponse
    {
        try {
            $action->execute($adminUser, $this->admin());
        } catch (AdminTeamActionException $e) {
            throw ValidationException::withMessages(['admin' => $e->getMessage()]);
        }

        return back();
    }

    public function reactivate(AdminUser $adminUser, ReactivateAdminUserAction $action): RedirectResponse
    {
        $action->execute($adminUser, $this->admin());

        return back();
    }

    private function admin(): AdminUser
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }
}
