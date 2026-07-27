import { Logo } from '@/Components/Logo';
import { Button } from '@/Components/ui/Button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/Card';
import { FieldError, Input, Label } from '@/Components/ui/Input';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * Customer sign-in — pixel port of the Next.js app's src/app/sign-in/page.tsx.
 * No "remember me" checkbox and no "forgot password" link: neither exists
 * in the original (MIGRATION_PLAN.md ADR-6). Rendered standalone, no shared
 * app shell — matches the original exactly (a sign-in page shouldn't share
 * chrome with the app it's gating). No <Head title> override either: the
 * original page.tsx has no page-specific metadata export, so it inherits
 * the root layout's default document title — matching that means NOT
 * setting one here.
 */
export default function Login() {
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
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
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
