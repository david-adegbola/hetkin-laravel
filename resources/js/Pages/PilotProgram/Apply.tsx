import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import {
    FieldError,
    Input,
    Label,
    Select,
    Textarea,
} from '@/Components/ui/Input';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const PROCESS_TYPE_LABELS: Record<string, string> = {
    COLD_STORAGE: 'Cold storage',
    METAL_TREATMENT: 'Metal / heat treatment',
    PLASTICS_MOLDING: 'Plastics injection molding',
    WOOD_PROCESSING: 'Wood processing',
    WATER_TREATMENT: 'Water treatment',
    FOOD_PROCESSING: 'Food processing',
    CHEMICAL_BATCH: 'Chemical / battery batch processing',
    OTHER: 'Other / not sure',
};

/**
 * Mirrors the Next.js app's src/app/pilot-program/apply/{page.tsx,
 * PilotApplicationForm.tsx}. Wrapped in the shared marketing chrome
 * (SiteHeader/SiteFooter), same as every other page in resources/js/Pages/
 * Marketing — v2's apply page always renders this chrome, including around
 * the post-submit success state. The success state swaps in for the form
 * in place, same as v2 — no separate confirmation page/redirect.
 */
export default function Apply({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        company_name: '',
        process_type: '',
        estimated_annual_spend_eur: '',
        current_scheduling_method: '',
        win_condition: '',
        contact_name: '',
        contact_email: '',
        contact_phone: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('pilot-program.apply.store'));
    };

    if (status === 'application-submitted') {
        return (
            <div className="flex min-h-screen flex-col">
                <Head title="Join the Pilot" />
                <SiteHeader />
                <main className="flex flex-1 items-center justify-center px-6 py-16">
                    <Card className="w-full max-w-lg text-center" role="status">
                        <div className="bg-success-subtle text-success mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full">
                            <svg
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2.5"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M20 6L9 17l-5-5" />
                            </svg>
                        </div>
                        <h3 className="font-display text-primary text-lg font-semibold">
                            Application received
                        </h3>
                        <p className="text-text-secondary mt-2 text-sm">
                            We review every pilot application individually and
                            reply within 2 business days. If it looks like a
                            fit, we'll set up a short qualification call.
                        </p>
                    </Card>
                </main>
                <SiteFooter />
            </div>
        );
    }

    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Join the Pilot" />
            <SiteHeader />
            <main className="flex-1 px-6 py-16">
                <div className="mx-auto max-w-2xl text-center">
                    <h1 className="font-display text-navy-900 text-3xl font-semibold sm:text-4xl">
                        Join the pilot
                    </h1>
                    <p className="text-ink-500 mt-3">
                        Tell us about your process and today's scheduling —
                        we'll let you know within 2 business days whether it
                        looks like a fit.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    noValidate
                    className="mx-auto mt-10 w-full max-w-2xl space-y-5"
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="company_name">Company name</Label>
                            <Input
                                id="company_name"
                                required
                                value={data.company_name}
                                onChange={(e) =>
                                    setData('company_name', e.target.value)
                                }
                            />
                            <FieldError>{errors.company_name}</FieldError>
                        </div>
                        <div>
                            <Label htmlFor="process_type">Process type</Label>
                            <Select
                                id="process_type"
                                required
                                value={data.process_type}
                                onChange={(e) =>
                                    setData('process_type', e.target.value)
                                }
                            >
                                <option value="" disabled>
                                    Select the closest match
                                </option>
                                {Object.entries(PROCESS_TYPE_LABELS).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </Select>
                            <FieldError>{errors.process_type}</FieldError>
                        </div>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="estimated_annual_spend_eur">
                                Estimated annual electricity spend (€)
                            </Label>
                            <Input
                                id="estimated_annual_spend_eur"
                                type="number"
                                min={0}
                                step={1000}
                                placeholder="e.g. 120000"
                                required
                                value={data.estimated_annual_spend_eur}
                                onChange={(e) =>
                                    setData(
                                        'estimated_annual_spend_eur',
                                        e.target.value,
                                    )
                                }
                            />
                            <FieldError>
                                {errors.estimated_annual_spend_eur}
                            </FieldError>
                        </div>
                        <div>
                            <Label htmlFor="current_scheduling_method">
                                How do you schedule production today?
                            </Label>
                            <Input
                                id="current_scheduling_method"
                                placeholder="e.g. Excel, ERP module, fixed shift pattern"
                                required
                                value={data.current_scheduling_method}
                                onChange={(e) =>
                                    setData(
                                        'current_scheduling_method',
                                        e.target.value,
                                    )
                                }
                            />
                            <FieldError>
                                {errors.current_scheduling_method}
                            </FieldError>
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="win_condition">
                            What would make this a win for you? (optional)
                        </Label>
                        <Textarea
                            id="win_condition"
                            rows={3}
                            value={data.win_condition}
                            onChange={(e) =>
                                setData('win_condition', e.target.value)
                            }
                        />
                    </div>

                    <hr className="border-border" />

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="contact_name">Your name</Label>
                            <Input
                                id="contact_name"
                                required
                                value={data.contact_name}
                                onChange={(e) =>
                                    setData('contact_name', e.target.value)
                                }
                            />
                            <FieldError>{errors.contact_name}</FieldError>
                        </div>
                        <div>
                            <Label htmlFor="contact_email">Work email</Label>
                            <Input
                                id="contact_email"
                                type="email"
                                required
                                value={data.contact_email}
                                onChange={(e) =>
                                    setData('contact_email', e.target.value)
                                }
                            />
                            <FieldError>{errors.contact_email}</FieldError>
                        </div>
                    </div>

                    <div className="max-w-xs">
                        <Label htmlFor="contact_phone">Phone (optional)</Label>
                        <Input
                            id="contact_phone"
                            type="tel"
                            value={data.contact_phone}
                            onChange={(e) =>
                                setData('contact_phone', e.target.value)
                            }
                        />
                    </div>

                    <div className="pt-2">
                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing}
                            className="w-full sm:w-auto"
                        >
                            {processing ? 'Submitting…' : 'Join the pilot'}
                        </Button>
                        <p className="text-text-secondary mt-3 text-sm">
                            No cost to apply. We review every application
                            individually — approved pilots move straight into
                            onboarding, and we'll reach out with next steps by
                            email.
                        </p>
                    </div>
                </form>
            </main>
            <SiteFooter />
        </div>
    );
}
