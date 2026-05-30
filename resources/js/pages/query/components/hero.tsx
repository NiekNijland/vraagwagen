import { useCountUp } from '@/hooks/use-count-up';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

// ─── Hero ──────────────────────────────────────────────────────
const HERO_ACCENT_PLACEHOLDER = '{accent}';

export function Hero({ compact }: { compact: boolean }) {
    const { t } = useTranslation();
    const template = t('pages.query.hero');
    const accent = t('pages.query.heroAccent');
    const placeholderIndex = template.indexOf(HERO_ACCENT_PLACEHOLDER);
    const before =
        placeholderIndex === -1
            ? template
            : template.slice(0, placeholderIndex);
    const after =
        placeholderIndex === -1
            ? ''
            : template.slice(placeholderIndex + HERO_ACCENT_PLACEHOLDER.length);

    return (
        <header className="flex flex-col items-center">
            <h1
                className={cn(
                    'm-0 font-bold tracking-[-0.04em] text-balance transition-[font-size,line-height] duration-300 ease-out',
                    compact
                        ? 'text-[clamp(1.4rem,2.6vw,1.85rem)] leading-[1.05]'
                        : 'text-[clamp(2.4rem,6.2vw,4.8rem)] leading-[0.96]',
                )}
            >
                {before}
                <span className="pr-[0.15em] font-bold text-[var(--rdw-orange)] italic">
                    <AnimatedAccent text={accent} />
                </span>
                {after}
            </h1>
            {!compact && (
                <p className="mt-4 max-w-[540px] text-base leading-[1.5] text-balance text-muted-foreground sm:text-[1.05rem]">
                    {t('pages.query.description')}
                </p>
            )}
        </header>
    );
}

// Counts the leading number in the hero accent up from zero on load
// ("16 miljoen" → animates the 16, keeps the word). Falls back to the raw text
// when there's no leading number to animate.
function AnimatedAccent({ text }: { text: string }) {
    const match = text.match(/^([\d.,\s]+)(.*)$/);
    const numericPart = match?.[1];
    const target = numericPart
        ? Number(numericPart.replace(/[^\d]/g, ''))
        : NaN;
    const animated = useCountUp(target, 1100, true);

    if (!Number.isFinite(target)) {
        return <>{text}</>;
    }

    const suffix = (match?.[2] ?? '').trim();

    return (
        <>
            {Math.round(animated).toLocaleString()}
            {suffix !== '' ? ` ${suffix}` : ''}
        </>
    );
}
