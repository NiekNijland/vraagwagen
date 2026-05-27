import { useCountUp } from '@/hooks/use-count-up';
import { useTranslation } from '@/hooks/use-translation';

import { formatNumber, localeTag } from '../format';
import type { Plan, QueryRow } from '../types';

export function CountView({
    rows,
    plan,
    locale,
}: {
    rows: QueryRow[];
    plan: Plan;
    locale: string;
}) {
    const { t } = useTranslation();
    const alias = plan.aggregates[0]?.alias ?? 'count';
    const firstRow = rows[0] ?? {};
    const raw = firstRow[alias] ?? Object.values(firstRow)[0];
    // Only treat genuine numbers and numeric strings as a count. `Number([])`
    // is 0 and `Number([42])` is 42, so without this guard a non-scalar first
    // cell would render a bogus count instead of falling back to formatNumber.
    const isNumeric =
        typeof raw === 'number' ||
        (typeof raw === 'string' &&
            raw.trim() !== '' &&
            Number.isFinite(Number(raw)));
    const target = isNumeric ? Number(raw) : 0;
    const animated = useCountUp(target, 900);

    const display = isNumeric
        ? Math.round(animated).toLocaleString(localeTag(locale))
        : formatNumber(raw, locale);

    return (
        <div className="flex flex-col items-center py-6">
            <div className="text-5xl font-semibold tracking-[-0.03em] text-[var(--rdw-orange)] tabular-nums">
                {display}
            </div>
            <div className="mt-1 text-sm text-muted-foreground">
                {t('pages.query.matchingVehicles')}
            </div>
        </div>
    );
}
