import { Logo } from '@/Components/Logo';
import { Button } from '@/Components/ui/Button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/Card';
import { FieldError, Label } from '@/Components/ui/Input';
import { PasswordInput } from '@/Components/ui/PasswordInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * Re-auth checkpoint before a sensitive action — Laravel's standard
 * `password.confirm` middleware redirects here, then back to the
 * originally-intended URL once confirmed (App\Http\Controllers\Auth\
 * ConfirmablePasswordController).
 */
export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="bg-background flex min-h-screen flex-col items-center justify-center gap-6 p-6">
            <Logo />
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Confirm your password</CardTitle>
                </CardHeader>
                <CardDescription className="mb-5">
                    For your security, please confirm your password to continue.
                </CardDescription>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput
                            id="password"
                            required
                            autoFocus
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />
                        <FieldError>{errors.password}</FieldError>
                    </div>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full"
                    >
                        {processing ? 'Confirming…' : 'Confirm'}
                    </Button>
                </form>
            </Card>
        </div>
    );
}
