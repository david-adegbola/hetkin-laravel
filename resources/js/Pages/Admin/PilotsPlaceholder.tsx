import { Button } from '@/Components/ui/Button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/Card';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';

/**
 * TEMPORARY — Stage 1 placeholder only, proving the admin guard and
 * post-login redirect work end to end. Replaced by the real pilots review
 * page (src/app/admin/(protected)/pilots in the Next.js app) in Stage 9.
 * See MIGRATION_PLAN.md ADR-10.
 */
export default function PilotsPlaceholder() {
    const { auth } = usePage<PageProps>().props;

    return (
        <div className="bg-background flex min-h-screen items-center justify-center p-6">
            <Card className="w-full max-w-md">
                <CardHeader>
                    <CardTitle>Signed in as {auth.admin?.name}</CardTitle>
                </CardHeader>
                <CardDescription className="mb-5">
                    Admin console placeholder — the real pilots review page
                    lands in a later migration stage.
                </CardDescription>
                <Link href={route('admin.logout')} method="post" as="button">
                    <Button variant="secondary">Sign out</Button>
                </Link>
            </Card>
        </div>
    );
}
