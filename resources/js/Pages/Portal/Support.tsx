import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { ReactElement } from 'react';

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/support/page.tsx —
 * restored per MIGRATION_PLAN.md's post-Stage-12 ADR.
 */
export default function Support() {
    return (
        <>
            <PortalPageHeader title="Support" />
            <Card>
                <p className="text-text-secondary text-sm">
                    For anything about your pilot, savings assessment, or a
                    disputed verification figure, reach your Hetkin contact
                    directly — every recommendation, override, and verification
                    decision is logged, so we can trace any question back to its
                    exact calculation.
                </p>
            </Card>
        </>
    );
}

Support.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
