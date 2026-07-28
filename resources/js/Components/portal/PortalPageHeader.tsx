/**
 * Per-page heading — ported 1:1 from the Next.js app's
 * PortalPageHeader.tsx.
 */
export default function PortalPageHeader({
    title,
    meta,
}: {
    title: string;
    meta?: string;
}) {
    return (
        <div className="mb-7 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 className="font-display text-primary text-2xl font-semibold">
                    {title}
                </h1>
                {meta && (
                    <div className="text-text-secondary mt-1 text-sm">
                        {meta}
                    </div>
                )}
            </div>
        </div>
    );
}
