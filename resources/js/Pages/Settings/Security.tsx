import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/Card';
import { FieldError, Label } from '@/Components/ui/Input';
import { PasswordInput } from '@/Components/ui/PasswordInput';
import { PasswordStrengthMeter } from '@/Components/ui/PasswordStrengthMeter';
import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactElement, useState } from 'react';

interface SessionRow {
    id: string;
    browser: string;
    platform: string;
    ip_address: string | null;
    location: string | null;
    last_active_at: string;
    last_active_at_human: string;
    is_current_device: boolean;
}

interface SecurityPageProps {
    passwordChangedAt: string | null;
    passwordAgeDays: number | null;
    sessions: SessionRow[];
    /** ISO timestamps only — see SecurityController's docblock for why. */
    recentLogins: string[];
}

/**
 * Settings → Security (Account Security module). Now wrapped in
 * PortalLayout rather than its own standalone chrome — Stage 1's original
 * placeholder said this "uses ... a plain centered container ... rather
 * than inventing settings-page chrome that will just be replaced" once a
 * shared portal layout existed. Stage 5 built that layout but never came
 * back to do the replacement, and no nav item pointed here either; both
 * are fixed as of MIGRATION_PLAN.md's post-Stage-12 ADR. Every Card's
 * content below is unchanged from the original — only the outer wrapper
 * changed, to match every other portal page.
 */
export default function Security({
    passwordChangedAt,
    passwordAgeDays,
    sessions,
    recentLogins,
}: SecurityPageProps) {
    return (
        <>
            <Head title="Security" />
            <PortalPageHeader title="Security" />
            <div className="flex flex-col gap-6">
                <SecurityRecommendations
                    passwordAgeDays={passwordAgeDays}
                    sessionCount={sessions.length}
                />
                <ChangePasswordCard passwordChangedAt={passwordChangedAt} />
                {/*
                 * "Trusted devices" isn't a separate concept from "active
                 * sessions" in this architecture — there's no distinct
                 * trusted-device model, only App\Domain\Auth\Models\
                 * SessionRecord, and each row below already IS one
                 * device's session. A second, parallel "trusted devices"
                 * list would just duplicate this one under a different
                 * name, so it isn't built as its own section.
                 */}
                <ActiveSessionsCard sessions={sessions} />
                <RecentLoginsCard logins={recentLogins} />
                <TwoFactorCard />
                <p className="text-text-secondary text-xs">
                    Password changes, resets, and signing out of all devices
                    each send you a confirmation email automatically — see{' '}
                    <span className="font-mono">
                        App\Notifications\Password*
                    </span>{' '}
                    and <span className="font-mono">LoggedOutAllDevices</span>.
                    There&rsquo;s nothing to configure; these always fire.
                </p>
            </div>
        </>
    );
}

function formatDate(iso: string | null): string {
    if (!iso) return 'Never';
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function SecurityRecommendations({
    passwordAgeDays,
    sessionCount,
}: {
    passwordAgeDays: number | null;
    sessionCount: number;
}) {
    const recommendations: string[] = [];

    if (passwordAgeDays === null || passwordAgeDays > 180) {
        recommendations.push(
            passwordAgeDays === null
                ? "You haven't changed your password since it was set — consider changing it now."
                : `Your password hasn't been changed in over ${Math.floor(passwordAgeDays / 30)} months — consider updating it.`,
        );
    }

    if (sessionCount > 3) {
        recommendations.push(
            `You have ${sessionCount} active sessions — review them below and sign out of any you don't recognize.`,
        );
    }

    if (recommendations.length === 0) return null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Security recommendations</CardTitle>
            </CardHeader>
            <CardContent>
                <ul className="space-y-2">
                    {recommendations.map((text) => (
                        <li
                            key={text}
                            className="text-primary flex items-start gap-2 text-sm"
                        >
                            <Badge
                                variant="mid"
                                showGlyph={false}
                                className="mt-0.5 shrink-0"
                            >
                                !
                            </Badge>
                            <span>{text}</span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function ChangePasswordCard({
    passwordChangedAt,
}: {
    passwordChangedAt: string | null;
}) {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('settings.security.password'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Change password</CardTitle>
            </CardHeader>
            <CardDescription className="mb-5">
                Last changed: {formatDate(passwordChangedAt)}
            </CardDescription>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="current_password">Current password</Label>
                    <PasswordInput
                        id="current_password"
                        required
                        autoComplete="current-password"
                        value={data.current_password}
                        onChange={(e) =>
                            setData('current_password', e.target.value)
                        }
                    />
                    <FieldError>{errors.current_password}</FieldError>
                </div>
                <div>
                    <Label htmlFor="password">New password</Label>
                    <PasswordInput
                        id="password"
                        required
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <PasswordStrengthMeter password={data.password} />
                    <FieldError>{errors.password}</FieldError>
                </div>
                <div>
                    <Label htmlFor="password_confirmation">
                        Confirm new password
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                    <FieldError>{errors.password_confirmation}</FieldError>
                </div>
                <Button type="submit" disabled={processing}>
                    {processing ? 'Updating…' : 'Update password'}
                </Button>
            </form>
        </Card>
    );
}

function ActiveSessionsCard({ sessions }: { sessions: SessionRow[] }) {
    const [revokingId, setRevokingId] = useState<string | null>(null);
    const [loggingOutOthers, setLoggingOutOthers] = useState(false);
    const otherSessionCount = sessions.filter(
        (s) => !s.is_current_device,
    ).length;

    function revoke(sessionId: string) {
        setRevokingId(sessionId);
        router.delete(route('settings.security.sessions.destroy', sessionId), {
            preserveScroll: true,
            onFinish: () => setRevokingId(null),
        });
    }

    function logoutOtherDevices() {
        setLoggingOutOthers(true);
        router.delete(route('settings.security.sessions.destroy-others'), {
            preserveScroll: true,
            onFinish: () => setLoggingOutOthers(false),
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Active sessions</CardTitle>
                {otherSessionCount > 0 && (
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={logoutOtherDevices}
                        disabled={loggingOutOthers}
                    >
                        {loggingOutOthers
                            ? 'Signing out…'
                            : 'Log out other devices'}
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                <ul className="divide-border divide-y">
                    {sessions.map((session) => (
                        <li
                            key={session.id}
                            className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div>
                                <p className="text-primary text-sm font-medium">
                                    {session.browser} on {session.platform}
                                    {session.is_current_device && (
                                        <Badge
                                            variant="navy"
                                            showGlyph={false}
                                            className="ml-2"
                                        >
                                            This device
                                        </Badge>
                                    )}
                                </p>
                                <p className="text-text-secondary text-xs">
                                    {session.ip_address ?? 'Unknown IP'} ·
                                    Active {session.last_active_at_human}
                                </p>
                            </div>
                            {!session.is_current_device && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => revoke(session.id)}
                                    disabled={revokingId === session.id}
                                >
                                    {revokingId === session.id
                                        ? 'Revoking…'
                                        : 'Revoke'}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            </CardContent>
            <CardFooter className="text-text-secondary text-xs">
                Approximate location isn&apos;t available yet for these
                sessions.
            </CardFooter>
        </Card>
    );
}

/**
 * Backed by real data (App\Domain\Auth\Actions\LoginAction's audit
 * entries), but deliberately minimal — timestamps only, since IP/device
 * metadata isn't captured on login events today (only on SessionRecord).
 * Extend App\Domain\Audit\DTOs\AuditEvent's metadata at the LoginAction
 * call site first if a richer history (device, approximate location) is
 * ever wanted — this card would then just render the extra fields, no
 * structural change needed here.
 */
function RecentLoginsCard({ logins }: { logins: string[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Recent login history</CardTitle>
            </CardHeader>
            <CardContent>
                {logins.length === 0 ? (
                    <p className="text-text-secondary text-sm">
                        No login history recorded yet.
                    </p>
                ) : (
                    <ul className="divide-border divide-y">
                        {logins.map((iso) => (
                            <li
                                key={iso}
                                className="text-primary py-2 text-sm first:pt-0 last:pb-0"
                            >
                                {formatDateTime(iso)}
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * Extension point, not a feature: no 2FA infrastructure exists anywhere in
 * this codebase yet (no columns, no TOTP/WebAuthn package, no enrollment
 * flow) — this card is deliberately inert. Wiring real 2FA would need, at
 * minimum: a secret/recovery-codes column set on `users`, an enrollment +
 * verification flow, and a second factor check inserted into
 * App\Http\Requests\Auth\LoginRequest alongside its existing credential
 * check.
 */
function TwoFactorCard() {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Two-factor authentication</CardTitle>
                <Badge variant="neutral" showGlyph={false}>
                    Coming soon
                </Badge>
            </CardHeader>
            <CardContent>
                <p className="text-text-secondary text-sm">
                    Not available yet. When it ships, you&rsquo;ll be able to
                    require a second step at sign-in on top of your password.
                </p>
            </CardContent>
        </Card>
    );
}

Security.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
