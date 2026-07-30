import { Logo } from '@/Components/Logo';
import { Button } from '@/Components/ui/Button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/Card';
import { FieldError, Input, Label } from '@/Components/ui/Input';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * "Forgot password?" request form. The success state (`status` prop) is
 * intentionally the SAME generic message regardless of whether the email
 * belongs to a real account — see
 * App\Domain\Auth\Actions\RequestPasswordResetAction's docblock. This page
 * must never branch its rendering on "was an email actually found."
 */
export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <div className="bg-background flex min-h-screen flex-col items-center justify-center gap-6 p-6">
            <Head title="Forgot password" />
            <Logo />
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle as="h1">Reset your password</CardTitle>
                </CardHeader>
                <CardDescription className="mb-5">
                    Enter your email and we&apos;ll send you a link to reset
                    your password.
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
                    <FieldError>{errors.email}</FieldError>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full"
                    >
                        {processing ? 'Sending…' : 'Email password reset link'}
                    </Button>
                </form>
                <p className="text-text-secondary mt-5 text-center text-sm">
                    <Link
                        href={route('login')}
                        className="text-accent hover:text-accent-hover font-medium"
                    >
                        Back to sign in
                    </Link>
                </p>
            </Card>
        </div>
    );
}
