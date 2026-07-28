/**
 * Client-side strength feedback only — non-blocking, purely informational.
 * The real, authoritative check is server-side (Password::defaults() in
 * AppServiceProvider: 12+ chars, mixed case, number, symbol, not a known
 * breached password via HIBP). This never prevents typing or submission;
 * a password that "looks strong" here can still be rejected server-side
 * (e.g. the breach check, which can't run client-side), and the server's
 * validation error is always the final word.
 */
export interface PasswordStrengthCriterion {
    label: string;
    met: boolean;
}

export type PasswordStrengthLevel =
    'empty' | 'weak' | 'fair' | 'good' | 'strong';

export interface PasswordStrengthResult {
    level: PasswordStrengthLevel;
    score: number; // 0–5, count of criteria met
    criteria: PasswordStrengthCriterion[];
}

export function evaluatePasswordStrength(
    password: string,
): PasswordStrengthResult {
    const criteria: PasswordStrengthCriterion[] = [
        { label: 'At least 12 characters', met: password.length >= 12 },
        { label: 'An uppercase letter', met: /[A-Z]/.test(password) },
        { label: 'A lowercase letter', met: /[a-z]/.test(password) },
        { label: 'A number', met: /[0-9]/.test(password) },
        { label: 'A special character', met: /[^A-Za-z0-9]/.test(password) },
    ];

    const score = criteria.filter((c) => c.met).length;

    let level: PasswordStrengthLevel;
    if (password.length === 0) {
        level = 'empty';
    } else if (score <= 2) {
        level = 'weak';
    } else if (score === 3) {
        level = 'fair';
    } else if (score === 4) {
        level = 'good';
    } else {
        level = 'strong';
    }

    return { level, score, criteria };
}
