import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';

/**
 * Reusable password field with a show/hide toggle — used by Login,
 * ResetPassword, and ChangePassword (Settings/Security) per the Account
 * Security brief: "Create a single reusable component."
 *
 * Accessibility: the toggle is a real <button type="button"> (keyboard
 * focusable and operable with Enter/Space without any extra handling),
 * carries `aria-pressed` reflecting visibility state, and an `aria-label`
 * that changes with it ("Show password" / "Hide password") so a screen
 * reader announces the actual action, not a static label. The underlying
 * <input> keeps its `name`/`autoComplete` untouched when toggled — only
 * `type` changes — which is what password managers key off to keep
 * recognizing the field.
 */
const FIELD_BASE =
    'rounded-control border border-border bg-background text-sm text-primary placeholder:text-text-secondary/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:border-accent disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-error aria-invalid:ring-error/30';

export const PasswordInput = React.forwardRef<
    HTMLInputElement,
    Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'>
>(({ className, ...props }, ref) => {
    const [visible, setVisible] = React.useState(false);

    return (
        <div className="relative">
            <input
                ref={ref}
                type={visible ? 'text' : 'password'}
                className={cn(
                    'h-11 w-full py-0 pr-11 pl-3.5',
                    FIELD_BASE,
                    className,
                )}
                {...props}
            />
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                aria-pressed={visible}
                aria-label={visible ? 'Hide password' : 'Show password'}
                className="text-text-secondary hover:text-primary focus-visible:ring-accent absolute inset-y-0 right-0 flex w-11 items-center justify-center transition-colors duration-150 ease-out focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
            >
                {visible ? (
                    <EyeOff className="h-4 w-4" aria-hidden="true" />
                ) : (
                    <Eye className="h-4 w-4" aria-hidden="true" />
                )}
            </button>
        </div>
    );
});
PasswordInput.displayName = 'PasswordInput';
