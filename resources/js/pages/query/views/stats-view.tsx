import { cn } from '@/lib/utils';

import { formatNumber, humanizeSnakeCase } from '../format';
import type { Plan, QueryRow } from '../types';

export function StatsView({
    rows,
    plan,
    locale,
}: {
    rows: QueryRow[];
    plan: Plan;
    locale: string;
}) {
    const row = rows[0] ?? {};
    const tiles = plan.aggregates
        .map((agg) => ({
            alias: agg.alias,
            label: humanizeSnakeCase(agg.alias),
            value: row[agg.alias],
        }))
        .filter(
            (tile): tile is { alias: string; label: string; value: unknown } =>
                tile.value !== undefined,
        );

    if (tiles.length === 0) {
        // Backend returned columns that don't match any aggregate alias.
        // Surface the row anyway, but warn so the mismatch is visible.
        if (plan.aggregates.length > 0) {
            console.warn(
                'StatsView: no row column matched an aggregate alias',
                {
                    expected: plan.aggregates.map((a) => a.alias),
                    received: Object.keys(row),
                },
            );
        }

        const fallback = Object.entries(row).map(([k, v]) => ({
            alias: k,
            label: humanizeSnakeCase(k),
            value: v,
        }));

        return <StatsGrid tiles={fallback} locale={locale} />;
    }

    return <StatsGrid tiles={tiles} locale={locale} />;
}

function StatsGrid({
    tiles,
    locale,
}: {
    tiles: Array<{ alias: string; label: string; value: unknown }>;
    locale: string;
}) {
    const columns = Math.min(tiles.length, 4);

    return (
        <div
            className={cn(
                'grid gap-3',
                columns === 1 && 'grid-cols-1',
                columns === 2 && 'grid-cols-1 sm:grid-cols-2',
                columns === 3 && 'grid-cols-1 sm:grid-cols-3',
                columns >= 4 && 'grid-cols-2 sm:grid-cols-4',
            )}
        >
            {tiles.map((tile) => (
                <div
                    key={tile.alias}
                    className="flex flex-col gap-1 rounded-lg border border-neutral-200 bg-gradient-to-br from-white to-neutral-50 p-4 dark:border-neutral-800 dark:from-neutral-950 dark:to-neutral-900"
                >
                    <div className="text-[11px] tracking-wide text-neutral-500 uppercase dark:text-neutral-400">
                        {tile.label}
                    </div>
                    <div className="text-2xl font-semibold tabular-nums sm:text-3xl">
                        {formatNumber(tile.value, locale)}
                    </div>
                </div>
            ))}
        </div>
    );
}
