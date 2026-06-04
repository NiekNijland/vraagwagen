import { MousePointerClick } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

import type { DiscoverItem, DiscoverViz } from '../examples';
import { extractPlateFromText } from '../plate';
import { PlateChip } from './plate-chip';

// ─── Discover cards (idle) ────────────────────────────────────
export function DiscoverCards({
    items,
    onPick,
}: {
    items: DiscoverItem[];
    onPick: (q: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="mt-6 flex w-full max-w-[880px] flex-col gap-2.5">
            <div className="flex items-baseline justify-between gap-3 px-0.5">
                <span className="font-mono text-[11px] font-semibold tracking-[0.18em] text-[var(--rdw-orange)] uppercase">
                    {t('pages.query.popular')}
                </span>
                <span className="inline-flex items-center gap-1.5 text-[11.5px] whitespace-nowrap text-muted-foreground/70">
                    <MousePointerClick
                        className="h-3.5 w-3.5 text-[var(--rdw-orange)]"
                        aria-hidden="true"
                    />
                    {t('pages.query.popularHint')}
                </span>
            </div>
            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                {items.map((item) => (
                    <DiscoverCard
                        key={item.question}
                        question={item.question}
                        viz={item.viz}
                        onPick={onPick}
                    />
                ))}
            </div>
        </div>
    );
}

const VIZ_LABEL_KEYS: Record<DiscoverViz, string> = {
    kpi: 'pages.query.viz.kpi',
    bars: 'pages.query.viz.bars',
    spark: 'pages.query.viz.spark',
    plate: 'pages.query.viz.plate',
};

function DiscoverCard({
    question,
    viz,
    onPick,
}: {
    question: string;
    viz: DiscoverViz;
    onPick: (q: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <button
            type="button"
            onClick={() => onPick(question)}
            className={cn(
                'group text-left',
                'flex min-h-[132px] flex-col gap-2.5 rounded-[4px] border bg-card px-3.5 pt-3.5 pb-3 text-card-foreground',
                'transition-all duration-200',
                'hover:-translate-y-0.5 hover:border-[var(--rdw-orange)] hover:bg-card/80 hover:shadow-[0_10px_24px_-14px_var(--rdw-orange-glow)]',
            )}
        >
            <div className="flex h-12 items-center">
                {viz === 'kpi' && (
                    <div className="flex items-baseline gap-1">
                        <span className="font-mono text-[24px] font-bold tracking-tight text-[var(--rdw-orange)] tabular-nums">
                            72.184
                        </span>
                    </div>
                )}
                {viz === 'bars' && (
                    <div className="flex w-full flex-col gap-1">
                        <span className="h-1.5 [width:92%] bg-[var(--rdw-orange)]" />
                        <span className="h-1.5 [width:64%] bg-[var(--rdw-orange)] opacity-[0.78]" />
                        <span className="h-1.5 [width:44%] bg-[var(--rdw-orange)] opacity-[0.56]" />
                        <span className="h-1.5 [width:28%] bg-[var(--rdw-orange)] opacity-[0.35]" />
                    </div>
                )}
                {viz === 'spark' && (
                    <svg
                        viewBox="0 0 100 40"
                        width="100%"
                        height="40"
                        preserveAspectRatio="none"
                        aria-hidden="true"
                    >
                        <path
                            d="M 0 32 L 14 28 L 28 22 L 42 24 L 56 16 L 70 12 L 84 6 L 100 4"
                            fill="none"
                            stroke="var(--rdw-orange)"
                            strokeWidth="2"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                    </svg>
                )}
                {viz === 'plate' && (
                    <PlateChip
                        plate={extractPlateFromText(question) ?? 'GT-486-N'}
                    />
                )}
            </div>
            <span className="line-clamp-2 text-[13px] leading-snug font-medium text-foreground">
                {question}
            </span>
            <span className="mt-auto font-mono text-[10.5px] tracking-[0.08em] text-muted-foreground/80 uppercase">
                {t(VIZ_LABEL_KEYS[viz])}
            </span>
        </button>
    );
}
