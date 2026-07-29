<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/team/page.tsx —
 * restored per MIGRATION_PLAN.md's post-Stage-12 ADR (deferred at Stage 5
 * by ADR-33, backend — MemberPolicy/InvitationPolicy — already built
 * anticipating this exact page). Read-only: v2 itself never built an
 * invite-member form on this page either ("Inviting new members is a
 * follow-up pass" — v2's own comment), so this port doesn't invent one.
 */
class TeamController extends Controller
{
    public function index(CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();

        $members = Member::query()
            ->where('organization_id', $organization->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        $invitations = Invitation::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'pending')
            ->get();

        return Inertia::render('Portal/Team', [
            'members' => $members->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role->value,
            ]),
            'invitations' => $invitations->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
            ]),
        ]);
    }
}
