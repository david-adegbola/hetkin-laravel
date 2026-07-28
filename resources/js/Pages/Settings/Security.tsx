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
import type { PageProps } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

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
}

/**
 * Settings → Security (Account Security module). No shared portal shell
 * exists yet (that lands with the real portal in Stage 5) — this page uses
 * the same Card/Button/Input primitives every other page in this app
 * does, wrapped in a plain centered container matching the placeholder
 * pattern from Stage 1, rather than inventing settings-page chrome that
 * will just be replaced.
 */
export default function Security({
    passwordChangedAt,
    passwordAgeDays,
    sessions,
}: SecurityPageProps) {
    const { auth } = usePage<PageProps>().props;

    return (
        <div className="bg-background min-h-screen p-6">
            <div className="mx-auto flex max-w-2xl flex-col gap-6">
                <div>
                    <p className="text-text-secondary text-sm">Settings</p>
                    <h1 className="text-primary text-2xl font-semibold">
                        Security
                    </h1>
                </div>

                <SecurityRecommendations
                    passwordAgeDays={passwordAgeDays}
                    sessionCount={sessions.length}
                />
                <ChangePasswordCard passwordChangedAt={passwordChangedAt} />
                <ActiveSessionsCard sessions={sessions} />

                <p className="text-center text-sm">
                    <Link
                        href={route('dashboard')}
                        className="text-accent hover:text-accent-hover font-medium"
                    >
                        ← Back to dashboard
                    </Link>
                </p>
                <p className="text-text-secondary text-center text-xs">
                    Signed in as {auth.user?.email}
                </p>
            </div>
        </div>
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
