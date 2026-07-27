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
 * TEMPORARY — Stage 1 placeholder only, proving the customer guard and
 * post-login redirect work end to end. Replaced by the real portal
 * (dashboard overview, sidebar nav, etc. — src/app/(portal)/dashboard in
 * the Next.js app) in Stage 5. See MIGRATION_PLAN.md ADR-10.
 */
export default function DashboardPlaceholder() {
    const { auth } = usePage<PageProps>().props;

    return (
        <div className="bg-background flex min-h-screen items-center justify-center p-6">
            <Card className="w-full max-w-md">
                <CardHeader>
                    <CardTitle>Signed in as {auth.user?.name}</CardTitle>
                </CardHeader>
                <CardDescription className="mb-5">
                    Customer portal placeholder — the real dashboard lands in a
                    later migration stage.
                </CardDescription>
                <Link href={route('logout')} method="post" as="button">
                    <Button variant="secondary">Sign out</Button>
                </Link>
            </Card>
        </div>
    );
}
