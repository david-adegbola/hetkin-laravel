import { Button } from '@/Components/ui/Button';
import { FieldError, Input, Label, Textarea } from '@/Components/ui/Input';
import { toHelsinkiDatetimeLocalValue } from '@/lib/format';
import { router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/
 * RecommendationDecisionButtons.tsx. Posts directly via router.post
 * (not usePortalBasePath-relative Ziggy route()) per this codebase's
 * established rule for portal links/actions. No local "success" state is
 * needed: a successful decide redirects back with the recommendation's
 * `decision` now set, and the parent only renders this component when
 * `decision` is null — so it simply stops being rendered.
 */

type Mode = 'idle' | 'reject' | 'partial';

export default function RecommendationDecisionButtons({
    base,
    recommendationId,
    proposedStartAt,
    proposedEndAt,
}: {
    base: string;
    recommendationId: string;
    proposedStartAt: string;
    proposedEndAt: string;
}) {
    const [mode, setMode] = useState<Mode>('idle');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [reason, setReason] = useState('');
    const [adjustedStartAt, setAdjustedStartAt] = useState(
        toHelsinkiDatetimeLocalValue(proposedStartAt),
    );

    const durationMinutes = Math.round(
        (new Date(proposedEndAt).getTime() -
            new Date(proposedStartAt).getTime()) /
            60_000,
    );

    function submit(payload: Record<string, string | number>) {
        setProcessing(true);
        setError(null);
        router.post(
            `${base}/recommendations/${recommendationId}/decide`,
            payload,
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.decision ?? null),
                onFinish: () => setProcessing(false),
            },
        );
    }

    const submitReject: FormEventHandler = (e) => {
        e.preventDefault();
        submit({ decision: 'REJECTED', reason });
    };

    const submitPartial: FormEventHandler = (e) => {
        e.preventDefault();
        submit({
            decision: 'PARTIAL',
            adjusted_start_at: adjustedStartAt,
            duration_minutes: durationMinutes,
            reason,
        });
    };

    if (mode === 'reject') {
        return (
            <form onSubmit={submitReject} className="w-full max-w-sm">
                <Label htmlFor={`reject-reason-${recommendationId}`}>
                    Why are you rejecting this?
                </Label>
                <Textarea
                    id={`reject-reason-${recommendationId}`}
                    required
                    rows={2}
                    placeholder="e.g. conflicts with a delivery commitment on this line"
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                />
                <div className="mt-2 flex items-center gap-2">
                    <Button
                        type="submit"
                        size="sm"
                        variant="destructive"
                        disabled={processing}
                    >
                        {processing ? 'Saving…' : 'Confirm reject'}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setMode('idle')}
                    >
                        Cancel
                    </Button>
                </div>
                <FieldError>{error}</FieldError>
            </form>
        );
    }

    if (mode === 'partial') {
        return (
            <form onSubmit={submitPartial} className="w-full max-w-sm">
                <Label htmlFor={`partial-time-${recommendationId}`}>
                    Move it to (instead of the full proposed shift) — Helsinki
                    time
                </Label>
                <Input
                    id={`partial-time-${recommendationId}`}
                    type="datetime-local"
                    required
                    value={adjustedStartAt}
                    onChange={(e) => setAdjustedStartAt(e.target.value)}
                />
                <Label
                    htmlFor={`partial-reason-${recommendationId}`}
                    className="mt-2"
                >
                    Note (optional)
                </Label>
                <Textarea
                    id={`partial-reason-${recommendationId}`}
                    rows={2}
                    placeholder="e.g. splitting the difference to protect the morning shift"
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                />
                <div className="mt-2 flex items-center gap-2">
                    <Button type="submit" size="sm" disabled={processing}>
                        {processing ? 'Saving…' : 'Confirm partial adoption'}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setMode('idle')}
                    >
                        Cancel
                    </Button>
                </div>
                <FieldError>{error}</FieldError>
            </form>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                size="sm"
                disabled={processing}
                onClick={() => submit({ decision: 'ACCEPTED' })}
            >
                {processing ? 'Saving…' : 'Accept'}
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setMode('partial')}
            >
                Partially adopt
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setMode('reject')}
            >
                Reject
            </Button>
            <FieldError>{error}</FieldError>
        </div>
    );
}
