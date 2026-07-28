import { Button } from '@/Components/ui/Button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/Card';
import { FieldError, Input, Label } from '@/Components/ui/Input';
import { PasswordInput } from '@/Components/ui/PasswordInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface InvitationSummary {
    id: string;
    email: string;
    organization_name: string;
}

/**
 * Mirrors the Next.js app's src/app/invite/[invitationId]/{page.tsx,
 * AcceptInvitationForm.tsx}. Combines v2's client signUp/signIn dance plus
 * a separate acceptInvitation() Server Action into one server-side POST —
 * see App\Http\Controllers\InvitationController's docblock for why.
 */
export default function Accept({
    invitation,
    invalid_reason: invalidReason,
}: {
    invitation: InvitationSummary | null;
    invalid_reason: string | null;
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!invitation) return;
        post(route('invite.accept', { invitation: invitation.id }));
    };

    return (
        <div className="bg-background flex min-h-screen items-center justify-center p-6">
            <Card className="w-full max-w-sm">
                {invalidReason || !invitation ? (
                    <>
                        <CardHeader>
                            <CardTitle>Invitation unavailable</CardTitle>
                        </CardHeader>
                        <CardDescription>{invalidReason}</CardDescription>
                    </>
                ) : (
                    <>
                        <CardHeader>
                            <CardTitle>
                                Join {invitation.organization_name} on Hetkin
                            </CardTitle>
                        </CardHeader>
                        <CardDescription className="mb-5">
                            Set a password to finish setting up your account.
                        </CardDescription>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={invitation.email}
                                    disabled
                                />
                            </div>
                            <div>
                                <Label htmlFor="name">Your name</Label>
                                <Input
                                    id="name"
                                    required
                                    autoFocus
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                />
                                <FieldError>{errors.name}</FieldError>
                            </div>
                            <div>
                                <Label htmlFor="password">
                                    Choose a password
                                </Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    minLength={12}
                                    autoComplete="new-password"
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
                                {processing
                                    ? 'Setting up your account…'
                                    : 'Create account'}
                            </Button>
                        </form>
                    </>
                )}
            </Card>
        </div>
    );
}
