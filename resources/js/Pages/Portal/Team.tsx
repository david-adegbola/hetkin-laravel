import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { ReactElement } from 'react';

interface MemberRow {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface InvitationRow {
    id: string;
    email: string;
    role: string;
}

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/team/page.tsx —
 * restored per MIGRATION_PLAN.md's post-Stage-12 ADR. Read-only, same as
 * v2: no invite-member form here, matching v2's own scope exactly (see
 * TeamController's docblock).
 */
export default function Team({
    members,
    invitations,
}: {
    members: MemberRow[];
    invitations: InvitationRow[];
}) {
    return (
        <>
            <PortalPageHeader title="Team & Permissions" />
            <Card>
                <table className="w-full text-left text-sm">
                    <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                        <tr>
                            <th className="py-2 pr-4">Name</th>
                            <th className="py-2 pr-4">Email</th>
                            <th className="py-2 pr-4">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        {members.map((member) => (
                            <tr
                                key={member.id}
                                className="border-border border-b last:border-0"
                            >
                                <td className="py-2 pr-4">{member.name}</td>
                                <td className="py-2 pr-4">{member.email}</td>
                                <td className="py-2 pr-4">
                                    <Badge variant="navy" showGlyph={false}>
                                        {member.role}
                                    </Badge>
                                </td>
                            </tr>
                        ))}
                        {invitations.map((invitation) => (
                            <tr
                                key={invitation.id}
                                className="border-border text-text-secondary border-b last:border-0"
                            >
                                <td className="py-2 pr-4">—</td>
                                <td className="py-2 pr-4">
                                    {invitation.email}
                                </td>
                                <td className="py-2 pr-4">
                                    <Badge variant="mid" showGlyph={false}>
                                        Invited · {invitation.role}
                                    </Badge>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
            <p className="text-text-secondary mt-3 text-xs">
                Roles: Owner, Scheduler, Finance, Viewer. Inviting new members
                is a follow-up pass on top of this list.
            </p>
        </>
    );
}

Team.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
