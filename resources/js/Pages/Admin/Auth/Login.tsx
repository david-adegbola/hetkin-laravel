import { Logo } from '@/Components/Logo';
import { Button } from '@/Components/ui/Button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/Card';
import { FieldError, Input, Label } from '@/Components/ui/Input';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * Internal admin sign-in — pixel port of the Next.js app's
 * src/app/admin/sign-in/page.tsx. Same layout as the customer sign-in page
 * (Components/Pages/Auth/Login.tsx) but a distinct route/page, matching the
 * original's two entirely separate sign-in screens.
 */
export default function AdminLogin() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.login.attempt'));
    };

    return (
        <main className="bg-background flex min-h-screen flex-col items-center justify-center gap-6 p-8">
            <Head title="Admin sign in" />
            <Logo />
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Admin sign-in</CardTitle>
                </CardHeader>
                <CardDescription className="mb-5">
                    Internal Hetkin staff only.
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
        </main>
    );
}
