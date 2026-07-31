import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { FieldError } from '@/Components/ui/Input';
import PortalLayout from '@/Layouts/PortalLayout';
import { usePortalBasePath } from '@/lib/subdomain';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactElement, useRef } from 'react';

export default function ScheduleImport() {
    const base = usePortalBasePath();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { setData, post, processing, errors } = useForm<{
        file: File | null;
    }>({ file: null });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(`${base}/schedule/import`, {
            forceFormData: true,
            onSuccess: () => {
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    return (
        <>
            <Head title="Import schedule" />
            <PortalPageHeader title="Import schedule" />
            <Card className="max-w-2xl">
                <p className="text-text-secondary mb-4 text-sm">
                    Upload a CSV exported from your ERP. Hetkin reads this file
                    only; it never connects to or changes your ERP. A successful
                    import creates a new schedule version and keeps the prior
                    version in history.
                </p>
                <div className="bg-surface-subtle text-text-secondary mb-5 rounded-lg p-4 text-xs leading-5">
                    <p className="text-primary font-medium">CSV columns</p>
                    <p>
                        Required: <code>asset_label</code>,{' '}
                        <code>start_at</code>, <code>end_at</code>. Optional:{' '}
                        <code>estimated_kwh</code>,{' '}
                        <code>labour_classification</code> (
                        <code>UNMANNED</code> or <code>LABOUR_BOUND</code>),{' '}
                        <code>site_name</code>, <code>description</code>. Common
                        ERP names such as “Asset”, “Planned start”, and “Work
                        center” are also recognised. Times without an offset are
                        interpreted in Finnish local time.
                    </p>
                </div>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label
                            htmlFor="file"
                            className="text-primary mb-1.5 block text-sm font-medium"
                        >
                            ERP schedule CSV
                        </label>
                        <input
                            ref={fileInputRef}
                            id="file"
                            name="file"
                            type="file"
                            accept=".csv,text/csv"
                            required
                            onChange={(event) =>
                                setData('file', event.target.files?.[0] ?? null)
                            }
                            className="file:bg-accent hover:file:bg-accent-hover text-text-secondary block w-full text-sm file:mr-4 file:rounded-lg file:border-0 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white"
                        />
                        <FieldError>{errors.file}</FieldError>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing}
                        >
                            {processing ? 'Importing…' : 'Import schedule'}
                        </Button>
                        <Link
                            href={`${base}/schedule`}
                            className="text-accent text-sm font-medium hover:underline"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </Card>
        </>
    );
}

ScheduleImport.layout = (page: ReactElement) => (
    <PortalLayout>{page}</PortalLayout>
);
