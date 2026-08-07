import { Head } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronRight,
    Clock3,
    Database,
    Factory,
    Gauge,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';

const PILOT_URL = 'https://hetkin.app/pilot-program/apply';
const TOTAL = 14;
const SLIDE_WIDTH = 1600;
const SLIDE_HEIGHT = 900;
// Displayed slide 10 (modeled evidence) follows displayed slide 4.
const SLIDE_ORDER = [0, 1, 2, 8, 3, 4, 5, 6, 7, 9, 10, 11];

function Tag({ children }: { children: ReactNode }) {
    return (
        <span className="border-border bg-background text-text-secondary rounded-full border px-4 py-1.5 text-sm font-semibold tracking-[0.12em] uppercase">
            {children}
        </span>
    );
}

function Metric({
    value,
    label,
    tone = 'text-accent',
}: {
    value: string;
    label: string;
    tone?: string;
}) {
    return (
        <div className="min-w-0">
            <div
                className={`pitch-deck-metric tabular-nums-figure text-[clamp(4.5rem,8vw,8rem)] leading-none font-semibold tracking-[-0.07em] ${tone}`}
            >
                {value}
            </div>
            <p className="text-text-secondary mt-5 max-w-md text-lg leading-7">
                {label}
            </p>
        </div>
    );
}

function Step({
    icon,
    title,
    text,
    accent = false,
}: {
    icon: ReactNode;
    title: string;
    text: string;
    accent?: boolean;
}) {
    return (
        <div
            className={`min-w-0 flex-1 rounded-[var(--radius-card)] border p-6 ${accent ? 'border-accent bg-accent-subtle' : 'border-border bg-background'}`}
        >
            <div
                className={`mb-7 flex size-12 items-center justify-center rounded-[10px] ${accent ? 'bg-accent text-white' : 'bg-surface text-primary'}`}
            >
                {icon}
            </div>
            <h3 className="text-xl font-semibold">{title}</h3>
            <p className="text-text-secondary mt-3 text-base leading-6">
                {text}
            </p>
        </div>
    );
}

function SlideFrame({
    kicker,
    title,
    children,
    className = '',
}: {
    kicker?: string;
    title: ReactNode;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`flex h-full flex-col px-[7%] pt-[7%] pb-[10%] ${className}`}
        >
            {kicker && <Tag>{kicker}</Tag>}
            <h1 className="pitch-deck-title text-primary mt-7 max-w-6xl text-[clamp(3.25rem,5.8vw,6.5rem)] leading-[1.02] font-semibold tracking-[-0.06em]">
                {title}
            </h1>
            {children}
        </div>
    );
}

function DeckSlide({ index }: { index: number }) {
    if (index === 0) {
        return (
            <div className="pitch-deck-cover-inner flex h-full flex-col items-center justify-center bg-primary px-[8%] text-white">
                <img
                    src="/images/hetkin-cover-mark.png"
                    alt="Hetkin logomark"
                    className="pitch-deck-cover-mark size-[clamp(5.5rem,7vw,7rem)] object-contain brightness-0 invert"
                />
                <p className="pitch-deck-cover-wordmark mt-[clamp(2rem,3vw,3rem)] font-display text-[clamp(8.75rem,10vw,11.25rem)] leading-none font-semibold tracking-[-0.07em]">
                    Hetkin
                </p>
            </div>
        );
    }

    switch (SLIDE_ORDER[index - 1]) {
        case 0:
            return (
                <SlideFrame
                    kicker="Every Hour Has a Price"
                    title={
                        <>
                            Same production.
                            <br />
                            <span className="text-accent">
                                Lower electricity bill.
                            </span>
                        </>
                    }
                    className="justify-between"
                >
                    <div className="border-border text-text-secondary border-t pt-7 text-2xl leading-9">
                        For Finnish manufacturing companies with spot-price
                        electricity.
                    </div>
                </SlideFrame>
            );
        case 1:
            return (
                <SlideFrame
                    kicker="The problem"
                    title={
                        <>
                            Electricity is Hendrik’s
                            <br />
                            <span className="text-accent">
                                #2 operating cost.
                            </span>
                        </>
                    }
                >
                    <div className="border-border mt-auto grid grid-cols-2 gap-6 border-t pt-8">
                        <Metric
                            value="100-150"
                            label="employees at Hendrik’s steel production business"
                            tone="text-primary"
                        />
                        <p className="text-text-secondary text-2xl leading-9">
                            Payroll comes first. Some production is fixed; some
                            can shift. No one can see which flexible work should
                            run in which hour.
                        </p>
                    </div>
                </SlideFrame>
            );
        case 2:
            return (
                <SlideFrame
                    kicker="Validation"
                    title="The problem was heard before it was built."
                >
                    <div className="mt-auto grid grid-cols-3 gap-5">
                        <Metric
                            value="30"
                            label="companies called"
                            tone="text-primary"
                        />
                        <Metric
                            value="16"
                            label="companies reached"
                            tone="text-primary"
                        />
                        <Metric
                            value="~11"
                            label="unprompted: electricity is a top-3 operating cost"
                        />
                    </div>
                    <p className="text-text-secondary mt-7 text-lg">
                        A VAMK student research report, based on interviews with
                        comparable Finnish companies, found the same concern.
                    </p>
                </SlideFrame>
            );
        case 3:
            return (
                <SlideFrame
                    kicker="The solution"
                    title="One safe production move, ready for review."
                >
                    <div className="mt-auto flex items-center justify-between gap-3">
                        <Step
                            icon={<Database size={24} />}
                            title="Meter data"
                            text="Hourly electricity use"
                        />
                        <ChevronRight
                            size={28}
                            className="text-text-secondary shrink-0"
                        />
                        <Step
                            icon={<Clock3 size={24} />}
                            title="Production schedule"
                            text="Fixed work stays untouched"
                        />
                        <ChevronRight
                            size={28}
                            className="text-text-secondary shrink-0"
                        />
                        <Step
                            icon={<Gauge size={24} />}
                            title="Tomorrow’s spot prices"
                            text="See the cost of each hour"
                        />
                        <ChevronRight
                            size={28}
                            className="text-accent shrink-0"
                        />
                        <Step
                            accent
                            icon={<ArrowRight size={24} />}
                            title="Recommendation"
                            text="Which flexible machine moves, and when"
                        />
                    </div>
                    <p className="text-text-secondary mt-7 text-lg">
                        Any manufacturer can start with a free, automatically
                        generated savings report showing what it could have
                        saved.
                    </p>
                </SlideFrame>
            );
        case 4:
            return (
                <div className="bg-surface flex h-full flex-col px-[7%] pt-[7%] pb-[10%]">
                    <div className="shrink-0">
                        <p className="text-text-secondary text-sm font-semibold tracking-[0.12em] uppercase">
                            The recommendation, in practice
                        </p>
                        <p className="text-primary mt-1 text-xl font-semibold">
                            Flexible window → lower-cost hour
                        </p>
                    </div>
                    <div className="mt-8 flex min-h-0 flex-1 items-center justify-center">
                        <div className="border-border bg-background aspect-video max-h-full w-full overflow-hidden rounded-[var(--radius-card)] border shadow-[0_16px_42px_rgba(15,23,42,0.16)]">
                            <video
                                autoPlay
                                loop
                                muted
                                playsInline
                                preload="metadata"
                                poster="/images/dashboard-walkthrough-poster.png"
                                className="size-full object-contain"
                                aria-label="Hetkin dashboard walkthrough showing a lower-cost production shift and estimated savings"
                            >
                                <source
                                    src="/videos/dashboard-explaineri.mp4"
                                    type="video/mp4"
                                />
                            </video>
                        </div>
                    </div>
                </div>
            );
        case 5:
            return (
                <SlideFrame
                    kicker="The market"
                    title={
                        <>
                            A focused Finnish market.
                            <br />
                            <span className="text-accent">
                                1,200-1,500 SMEs.
                            </span>
                        </>
                    }
                >
                    <div className="mt-auto grid grid-cols-2 gap-6">
                        <p className="text-text-secondary text-2xl leading-9">
                            The fit is not company size or industry. It is
                            flexibility: production that can shift a few hours
                            without breaking delivery.
                        </p>
                        <div className="border-border bg-surface rounded-[var(--radius-card)] border p-7">
                            <p className="text-xl font-semibold">
                                Next markets
                            </p>
                            <p className="text-text-secondary mt-5 text-2xl leading-9">
                                Sweden · Denmark · Norway
                            </p>
                            <p className="text-text-secondary mt-4 text-lg leading-7">
                                The same meter-data and spot-price mechanism
                                applies.
                            </p>
                        </div>
                    </div>
                </SlideFrame>
            );
        case 6:
            return (
                <SlideFrame
                    kicker="The competitive gap"
                    title="SMEs are left between two extremes."
                >
                    <div className="mt-auto grid grid-cols-3 gap-5">
                        <Step
                            icon={<Factory size={24} />}
                            title="Large enterprise tools"
                            text="ABB, Honeywell and similar systems serve big accounts with big teams."
                        />
                        <Step
                            icon={<Gauge size={24} />}
                            title="Free consumer apps"
                            text="Useful for households, not production decisions."
                        />
                        <Step
                            accent
                            icon={<ArrowRight size={24} />}
                            title="Hetkin"
                            text="For SMEs with no dedicated energy team — often not even one person assigned to it."
                        />
                    </div>
                </SlideFrame>
            );
        case 7:
            return (
                <SlideFrame
                    kicker="The business model"
                    title="Small base fee. Shared, verified upside."
                >
                    <div className="mt-14 grid grid-cols-2 gap-5">
                        <div className="border-border bg-surface rounded-[var(--radius-card)] border p-8">
                            <p className="tabular-nums-figure text-text-secondary text-sm font-semibold tracking-[0.14em] uppercase">
                                01 · Base fee
                            </p>
                            <h2 className="mt-5 text-4xl font-semibold tracking-[-0.04em]">
                                Small and predictable
                            </h2>
                            <p className="text-text-secondary mt-5 text-lg leading-7">
                                A straightforward subscription for using Hetkin.
                            </p>
                        </div>
                        <div className="border-accent bg-accent-subtle rounded-[var(--radius-card)] border p-8">
                            <p className="tabular-nums-figure text-accent text-sm font-semibold tracking-[0.14em] uppercase">
                                02 · Verified savings share
                            </p>
                            <h2 className="mt-5 text-4xl font-semibold tracking-[-0.04em]">
                                Only after savings happen
                            </h2>
                            <p className="text-text-secondary mt-5 text-lg leading-7">
                                Accept, reject, or partly accept recommendations
                                so monthly savings can be tracked.
                            </p>
                        </div>
                    </div>
                    <p className="border-border text-text-secondary mt-8 border-t pt-6 text-xl leading-8">
                        Guarantee: if a customer follows a recommendation and
                        misses the predicted savings, Hetkin returns that
                        shortfall at the end of the subscription period.
                    </p>
                </SlideFrame>
            );
        case 8:
            return (
                <SlideFrame
                    kicker="Modeled evidence"
                    title="The result depends on the load."
                >
                    <div className="mt-8 grid grid-cols-2 gap-6">
                        <article className="pitch-deck-evidence-card border-accent bg-accent-subtle rounded-[var(--radius-card)] border p-7">
                            <p className="text-accent text-sm font-semibold tracking-[0.14em] uppercase">
                                Flexible production
                            </p>
                            <Metric
                                value="15.3%"
                                label="Savings when batches can move to a lower-cost hour"
                                tone="text-accent"
                            />
                        </article>
                        <article className="pitch-deck-evidence-card border-border bg-surface rounded-[var(--radius-card)] border p-7">
                            <p className="text-text-secondary text-sm font-semibold tracking-[0.14em] uppercase">
                                Continuous load
                            </p>
                            <Metric
                                value="~0%"
                                label="Savings when production cannot move"
                                tone="text-primary"
                            />
                        </article>
                    </div>
                    <p className="border-border text-text-secondary mt-6 border-t pt-5 text-base leading-6">
                        Modeled with real winter spot prices and historical
                        meter data.
                    </p>
                </SlideFrame>
            );
        case 9:
            return (
                <SlideFrame
                    kicker="The vision"
                    title="Two near-term commitments."
                >
                    <div className="mt-14 grid grid-cols-2 gap-6">
                        <div className="border-border bg-surface rounded-[var(--radius-card)] border p-8">
                            <p className="text-text-secondary text-sm font-semibold tracking-[0.14em] uppercase">
                                By September
                            </p>
                            <Metric
                                value="100"
                                label="companies contacted"
                                tone="text-primary"
                            />
                        </div>
                        <div className="border-accent bg-accent-subtle rounded-[var(--radius-card)] border p-8">
                            <p className="text-accent text-sm font-semibold tracking-[0.14em] uppercase">
                                By October
                            </p>
                            <Metric value="5-6" label="signed pilots" />
                        </div>
                    </div>
                </SlideFrame>
            );
        case 10:
            return (
                <SlideFrame
                    kicker="The ask"
                    title="Help put the next proof on the factory floor."
                >
                    <div className="mt-auto grid grid-cols-2 gap-5">
                        <Step
                            icon={<Factory size={28} />}
                            title="Introductions to real customers"
                            text="Production managers and plant decision-makers at target companies."
                        />
                        <Step
                            icon={<Gauge size={28} />}
                            title="An energy-sector mentor"
                            text="Someone with existing relationships into target companies."
                        />
                    </div>
                    <p className="text-text-secondary mt-7 text-lg">
                        Equity is not divided or decided yet. That conversation
                        happens at incorporation.
                    </p>
                </SlideFrame>
            );
        case 11:
            return (
                <SlideFrame
                    kicker="The team"
                    title="The people building Hetkin."
                >
                    <div className="mt-auto grid grid-cols-3 gap-4">
                        <article className="border-border bg-surface rounded-[var(--radius-card)] border p-6">
                            <h2 className="text-2xl font-semibold">
                                David Adegbola
                            </h2>
                            <p className="text-accent mt-1 text-lg font-medium">
                                Technical Lead
                            </p>
                            <p className="text-text-secondary mt-5 text-sm leading-6">
                                BSc in Information Technology at Vaasa
                                Univeristy of Applied Sciences (ongoing).
                            </p>
                            <p className="text-text-secondary mt-4 text-sm leading-6">
                                Software product development, data analytics,
                                and practical industrial experience at UPM
                                Kaukas Pulp Mill.
                            </p>
                        </article>
                        <article className="border-border bg-surface rounded-[var(--radius-card)] border p-6">
                            <h2 className="text-2xl font-semibold">
                                Khadiza Khan Mim(Diza)
                            </h2>
                            <p className="text-accent mt-1 text-lg font-medium">
                                CEO
                            </p>
                            <p className="text-text-secondary mt-5 text-sm leading-6">
                                MSc in Strategic Business Development at the
                                University of Vaasa (ongoing).
                            </p>
                            <p className="text-text-secondary mt-4 text-sm leading-6">
                                CA (ICAB); apprenticeship at KPMG; former
                                Finance Controller at Thomson Reuters, LSEG, and
                                S.F. Ahmed &amp; Co.
                            </p>
                        </article>
                        <article className="border-border bg-surface rounded-[var(--radius-card)] border p-6">
                            <h2 className="text-2xl font-semibold">
                                Desmond Olaniyi
                            </h2>
                            <p className="text-accent mt-1 text-lg font-medium">
                                Market Analyst
                            </p>
                            <p className="text-text-secondary mt-5 text-sm leading-6">
                                BSc in International Business at Vaasa
                                Univeristy of Applied Sciences (ongoing).
                            </p>
                            <p className="text-text-secondary mt-4 text-sm leading-6">
                                Marketing Intern at Room for Cultures; service
                                design for Elder State Communications;
                                presentation and speech at Step to Success;
                                prototyping sprint project at Fennorica.
                            </p>
                        </article>
                    </div>
                </SlideFrame>
            );
        default:
            return (
                <SlideFrame
                    kicker="Thank you"
                    title={
                        <>
                            Start the
                            <br />
                            <span className="text-accent">conversation.</span>
                        </>
                    }
                >
                    <div className="border-border mt-auto flex items-end justify-between border-t pt-8">
                        <div>
                            <p className="text-text-secondary mb-4 text-2xl">
                                Questions?
                            </p>
                            <a
                                href={PILOT_URL}
                                className="tabular-nums-figure text-accent focus-visible:ring-accent inline-block text-2xl font-semibold underline decoration-1 underline-offset-4 focus-visible:rounded focus-visible:ring-2"
                            >
                                hetkin.app/pilot-program/apply
                            </a>
                        </div>
                        <img
                            id="pilot-qr"
                            src="/images/pilot-program-qr.png"
                            alt="QR code linking to hetkin.app/pilot-program/apply"
                            width="140"
                            height="140"
                            className="border-border size-[140px] rounded-[10px] border bg-white p-2"
                        />
                    </div>
                </SlideFrame>
            );
    }
}

export default function PitchDeck() {
    const [current, setCurrent] = useState(0);
    const [slideScale, setSlideScale] = useState(1);
    const deckRef = useRef<HTMLElement>(null);
    const slideRef = useRef<HTMLElement>(null);
    const go = useCallback(
        (direction: number) =>
            setCurrent((slide) =>
                Math.min(TOTAL - 1, Math.max(0, slide + direction)),
            ),
        [],
    );
    const toggleFullscreen = useCallback(async () => {
        if (!deckRef.current) return;
        if (document.fullscreenElement) {
            await document.exitFullscreen();
        } else {
            await deckRef.current.requestFullscreen();
        }
    }, []);
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'ArrowRight' || event.key === ' ') {
                event.preventDefault();
                go(1);
            }
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                go(-1);
            }
            if (
                event.key.toLowerCase() === 'f' &&
                !event.metaKey &&
                !event.ctrlKey &&
                !event.altKey
            ) {
                event.preventDefault();
                void toggleFullscreen();
            }
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [go, toggleFullscreen]);
    useLayoutEffect(() => {
        const slide = slideRef.current;
        if (!slide) return;
        const updateScale = () => {
            const { width, height } = slide.getBoundingClientRect();
            setSlideScale(Math.min(width / SLIDE_WIDTH, height / SLIDE_HEIGHT));
        };
        updateScale();
        const observer = new ResizeObserver(updateScale);
        observer.observe(slide);
        return () => observer.disconnect();
    }, []);
    return (
        <>
            <Head title="Every Hour Has a Price">
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <main
                ref={deckRef}
                className="pitch-deck-viewer flex min-h-dvh items-center justify-center bg-slate-100 p-3 sm:p-6"
            >
                <section
                    ref={slideRef}
                    aria-label={`Slide ${current + 1} of ${TOTAL}`}
                    className={`pitch-deck-slide bg-background relative aspect-video w-full max-w-[min(177.78vh,1600px)] overflow-hidden rounded-[var(--radius-card)] shadow-sm ${current === 0 ? 'pitch-deck-cover' : ''}`}
                >
                    <div
                        className="pitch-deck-canvas"
                        style={{
                            width: SLIDE_WIDTH,
                            height: SLIDE_HEIGHT,
                            transform: `scale(${slideScale})`,
                        }}
                    >
                        <div key={current} className="h-full">
                            <DeckSlide index={current} />
                        </div>
                        <div className="tabular-nums-figure text-text-secondary absolute right-[4%] bottom-[4%] text-sm">
                            {String(current + 1).padStart(2, '0')} / {TOTAL}
                        </div>
                        <div
                            className="absolute bottom-[4%] left-[7%] flex items-center gap-2"
                            aria-label="Slide navigation"
                        >
                            {Array.from({ length: TOTAL }, (_, slide) => (
                                <button
                                    key={slide}
                                    onClick={() => setCurrent(slide)}
                                    aria-label={`Go to slide ${slide + 1}`}
                                    aria-current={
                                        slide === current ? 'true' : undefined
                                    }
                                    className={`focus-visible:outline-accent size-3 rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-3 ${slide === current ? 'bg-primary' : 'bg-border hover:bg-text-secondary'}`}
                                />
                            ))}
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}
