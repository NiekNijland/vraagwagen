import AppLogoIcon from '@/components/app-logo-icon';
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
            {!compact && (
                <AppLogoIcon
                    className="mb-6 h-24 w-auto sm:h-28"
                    aria-hidden="true"
                />
            )}
            <h1
                className={cn(
                    'm-0 font-bold tracking-[-0.04em] text-balance transition-[font-size,line-height] duration-300 ease-out',
                    compact
                        ? 'text-[clamp(1.4rem,2.6vw,1.85rem)] leading-[1.05]'
                        : 'text-[clamp(2.4rem,6.2vw,4.8rem)] leading-[0.96]',
                )}
            >
                {before}
                <span className="font-mono font-bold tracking-[-0.06em] text-[var(--rdw-orange)]">
                    {accent}
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
