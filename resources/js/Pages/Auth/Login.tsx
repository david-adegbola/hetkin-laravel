import { Logo } from '@/Components/Logo';
import { Button } from '@/Components/ui/Button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/Card';
import { FieldError, Input, Label } from '@/Components/ui/Input';
import { PasswordInput } from '@/Components/ui/PasswordInput';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * Customer sign-in — pixel port of the Next.js app's src/app/sign-in/page.tsx,
 * plus the "Forgot your password?" link and status-message banner added by
 * the Account Security module: v2 has no reset-password flow at all
 * (MIGRATION_PLAN.md ADR-6), so there was nothing to link to before this
 * module added one. This is the one deliberate UX addition on this page —
 * required for the new feature to be reachable, not a redesign of anything
 * that already existed. No "remember me" checkbox: still doesn't exist in
 * the original. Rendered standalone, no shared app shell — matches the
 * original exactly. No <Head title> override either: the original page.tsx
 * has no page-specific metadata export, so it inherits the root layout's
 * default document title — matching that means NOT setting one here.
 */
export default function Login({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <div className="bg-background flex min-h-screen flex-col items-center justify-center gap-6 p-6">
            <Logo />
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Sign in to Hetkin</CardTitle>
                </CardHeader>
                <CardDescription className="mb-5">
                    For customers on an active or approved pilot.
                </CardDescription>
                {status && (
                    <p
                        role="status"
                        className="text-success mb-4 text-sm font-medium"
                    >
                        {status}
                    </p>
                )}
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            autoComplete="username"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </div>
                    <div>
                        <div className="mb-1.5 flex items-center justify-between">
                            <Label htmlFor="password" className="mb-0">
                                Password
                            </Label>
                            <Link
                                href={route('password.request')}
                                className="text-accent hover:text-accent-hover text-xs font-medium"
                            >
                                Forgot your password?
                            </Link>
                        </div>
                        <PasswordInput
                            id="password"
                            required
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />
                    </div>
                    <FieldError>{errors.email ?? errors.password}</FieldError>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full"
                    >
                        {processing ? 'Signing in…' : 'Sign in'}
                    </Button>
                </form>
            </Card>
        </div>
    );
}
