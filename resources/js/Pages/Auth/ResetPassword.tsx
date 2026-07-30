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
import { PasswordStrengthMeter } from '@/Components/ui/PasswordStrengthMeter';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * Reached from the emailed reset link (token in the URL). On success the
 * controller redirects to Login with a status message — this page never
 * logs the user in itself (App\Http\Controllers\Auth\NewPasswordController).
 */
export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'));
    };

    return (
        <div className="bg-background flex min-h-screen flex-col items-center justify-center gap-6 p-6">
            <Head title="Reset password" />
            <Logo />
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle as="h1">Set a new password</CardTitle>
                </CardHeader>
                <CardDescription className="mb-5">
                    Choose a new password for {email}.
                </CardDescription>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            disabled
                        />
                    </div>
                    <div>
                        <Label htmlFor="password">New password</Label>
                        <PasswordInput
                            id="password"
                            required
                            autoFocus
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
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
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full"
                    >
                        {processing ? 'Resetting…' : 'Reset password'}
                    </Button>
                </form>
            </Card>
        </div>
    );
}
