import { Github } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

import { localeTag } from '../format';
import type { PlatformStats } from '../types';

// ─── Stat strip (bottom) ─────────────────────────────────────
export function StatStrip({
    stats,
    locale,
}: {
    /** Deferred Inertia prop: undefined until the follow-up request lands. */
    stats: PlatformStats | undefined;
    locale: string;
}) {
    const { t } = useTranslation();
    const nf = new Intl.NumberFormat(localeTag(locale));

    return (
        <footer
            className={cn(
                'absolute right-0 bottom-0 left-0 z-10',
                'flex flex-wrap items-center justify-between gap-x-6 gap-y-1.5 border-t px-6 py-3 text-xs text-muted-foreground md:px-8',
                'bg-gradient-to-t from-background via-background/95 to-transparent',
            )}
        >
            <div className="inline-flex flex-wrap items-center gap-5">
                {stats ? (
                    <>
                        {stats.vehicles !== null && (
                            <StatItem
                                value={nf.format(stats.vehicles)}
                                label={t('pages.query.statsVehicles')}
                            />
                        )}
                        {stats.datasets > 0 && (
                            <StatItem
                                value={nf.format(stats.datasets)}
                                label={t('pages.query.statsDatasets')}
                            />
                        )}
                        <StatItem
                            value={t('pages.query.statsRefreshValue')}
                            label={t('pages.query.statsRefreshLabel')}
                        />
                        {stats.queriesAnswered > 0 && (
                            <StatItem
                                value={nf.format(stats.queriesAnswered)}
                                label={t('pages.query.statsQueriesAnswered')}
                            />
                        )}
                    </>
                ) : (
                    <StatStripSkeleton />
                )}
            </div>
            <div className="inline-flex items-center gap-2 font-mono text-[11.5px] tracking-wide">
                <span className="text-muted-foreground/60">
                    {t('pages.query.sourceLabel')}
                </span>
                <a
                    href="https://opendata.rdw.nl"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-muted-foreground underline-offset-2 transition-colors hover:text-foreground hover:underline"
                >
                    opendata.rdw.nl
                </a>
                <span className="text-muted-foreground/40">·</span>
                <a
                    href="https://github.com/NiekNijland/vraagwagen"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="GitHub"
                    className="inline-flex items-center gap-1 text-muted-foreground underline-offset-2 transition-colors hover:text-foreground hover:underline"
                >
                    <Github className="h-3 w-3" />
                    <span>GitHub</span>
                </a>
                <span className="text-muted-foreground/40">·</span>
                <a
                    href={`/${locale}/privacy`}
                    className="text-muted-foreground underline-offset-2 transition-colors hover:text-foreground hover:underline"
                >
                    {t('pages.privacy.title')}
                </a>
            </div>
        </footer>
    );
}

function StatItem({ value, label }: { value: string; label: string }) {
    return (
        <span className="inline-flex items-baseline gap-1.5 whitespace-nowrap">
            <span className="font-mono text-[12.5px] font-semibold text-foreground tabular-nums">
                {value}
            </span>
            <span className="text-[11.5px] text-muted-foreground/70">
                {label}
            </span>
        </span>
    );
}

function StatStripSkeleton() {
    return (
        <span
            className="inline-flex items-center gap-5"
            data-testid="stat-strip-skeleton"
            aria-hidden="true"
        >
            {['w-20', 'w-14', 'w-24'].map((width) => (
                <span
                    key={width}
                    className={cn(
                        'h-3 animate-pulse rounded bg-muted-foreground/15',
                        width,
                    )}
                />
            ))}
        </span>
    );
}
