import { evaluatePasswordStrength } from '@/lib/passwordStrength';
import { cn } from '@/lib/utils';

const LEVEL_COPY: Record<string, string> = {
    empty: '',
    weak: 'Weak',
    fair: 'Fair',
    good: 'Good',
    strong: 'Strong',
};

// Reuses the app's existing cheap/mid/expensive semantic colors
// (Badge.tsx's `classifyPriceTier` triad) rather than inventing a new
// weak/strong color language — success=strong, warning=weak/fair,
// error is never used here (an empty/weak password isn't a system error).
const LEVEL_BAR_CLASS: Record<string, string> = {
    empty: 'bg-border',
    weak: 'bg-error',
    fair: 'bg-warning',
    good: 'bg-warning',
    strong: 'bg-success',
};

const LEVEL_TEXT_CLASS: Record<string, string> = {
    empty: 'text-text-secondary',
    weak: 'text-error',
    fair: 'text-warning',
    good: 'text-warning',
    strong: 'text-success',
};

/**
 * Real-time, non-blocking password strength feedback (Account Security
 * brief: "Do not block typing. Validate fully on submit."). Purely visual
 * — the authoritative check is Password::defaults() server-side (see
 * resources/js/lib/passwordStrength.ts's docblock).
 */
export function PasswordStrengthMeter({ password }: { password: string }) {
    const { level, score, criteria } = evaluatePasswordStrength(password);

    if (level === 'empty') return null;

    return (
        <div className="mt-2" aria-live="polite">
            <div className="flex items-center gap-2">
                <div className="flex flex-1 gap-1" role="presentation">
                    {[0, 1, 2, 3, 4].map((i) => (
                        <span
                            key={i}
                            className={cn(
                                'h-1.5 flex-1 rounded-full transition-colors duration-150 ease-out',
                                i < score
                                    ? LEVEL_BAR_CLASS[level]
                                    : 'bg-border',
                            )}
                        />
                    ))}
                </div>
                <span
                    className={cn(
                        'text-xs font-medium',
                        LEVEL_TEXT_CLASS[level],
                    )}
                >
                    {LEVEL_COPY[level]}
                </span>
            </div>
            <ul className="mt-2 space-y-0.5">
                {criteria.map((criterion) => (
                    <li
                        key={criterion.label}
                        className={cn(
                            'text-xs',
                            criterion.met
                                ? 'text-success'
                                : 'text-text-secondary',
                        )}
                    >
                        {criterion.met ? '✓' : '·'} {criterion.label}
                    </li>
                ))}
            </ul>
        </div>
    );
}
