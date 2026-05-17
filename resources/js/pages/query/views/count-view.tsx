import { useTranslation } from '@/hooks/use-translation';

import { formatNumber } from '../format';
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
    const value = firstRow[alias] ?? Object.values(firstRow)[0];

    return (
        <div className="flex flex-col items-center py-6">
            <div className="text-5xl font-semibold tabular-nums">
                {formatNumber(value, locale)}
            </div>
            <div className="mt-1 text-sm text-neutral-500">
                {t('pages.query.matchingVehicles')}
            </div>
        </div>
    );
}
