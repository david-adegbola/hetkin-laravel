import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { FieldError } from '@/Components/ui/Input';
import PortalLayout from '@/Layouts/PortalLayout';
import { usePortalBasePath } from '@/lib/subdomain';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactElement, useRef } from 'react';

interface UploadStatus {
    rowsWritten: number;
}

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/data/upload/
 * {page.tsx,UploadForm.tsx} + actions.ts's uploadMeterCsv(). Posts to
 * `${base}/data/upload` (host-relative, not a hardcoded named route) so
 * this stays correct on both the main domain and the dashboard subdomain
 * — see MIGRATION_PLAN.md ADR-21/ADR-22.
 */
export default function DataUpload({ status }: { status?: UploadStatus }) {
    const base = usePortalBasePath();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { setData, post, processing, errors } = useForm<{
        file: File | null;
    }>({
        file: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`${base}/data/upload`, {
            forceFormData: true,
            onSuccess: () => {
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    return (
        <>
            <Head title="Upload meter data" />
            <PortalPageHeader title="Upload meter data" />
            <Card className="max-w-lg">
                <p className="text-text-secondary mb-4 text-sm">
                    Export your consumption data from Fingrid Datahub as CSV
                    (PT1H or PT15M resolution) and upload it here. This is the
                    manual fallback path — Fingrid Datahub consent-based
                    integration replaces this once authorised-party registration
                    completes.
                </p>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label
                            htmlFor="file"
                            className="text-primary mb-1.5 block text-sm font-medium"
                        >
                            Fingrid Datahub CSV export
                        </label>
                        <input
                            ref={fileInputRef}
                            id="file"
                            name="file"
                            type="file"
                            accept=".csv,text/csv"
                            required
                            onChange={(e) =>
                                setData('file', e.target.files?.[0] ?? null)
                            }
                            className="file:bg-accent hover:file:bg-accent-hover text-text-secondary block w-full text-sm file:mr-4 file:rounded-lg file:border-0 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white"
                        />
                        <FieldError>{errors.file}</FieldError>
                    </div>

                    {status && (
                        <div className="bg-success-subtle rounded-lg px-4 py-4">
                            <p className="text-success flex items-center gap-2 text-sm font-medium">
                                <span aria-hidden>✓</span>
                                Uploaded {status.rowsWritten.toLocaleString()}{' '}
                                hourly readings.
                            </p>
                            <p className="text-text-secondary mt-1.5 text-sm">
                                Your data is saved and ready.
                            </p>
                        </div>
                    )}

                    <Button
                        type="submit"
                        disabled={processing}
                        aria-busy={processing}
                    >
                        {processing ? 'Uploading…' : 'Upload'}
                    </Button>
                </form>
            </Card>
        </>
    );
}

DataUpload.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
