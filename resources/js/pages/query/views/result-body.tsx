import { lazy, Suspense } from 'react';

import { useTranslation } from '@/hooks/use-translation';

import type { QueryResult } from '../types';
import { CountView } from './count-view';
import { DerivedView } from './derived-view';
import { RecordView } from './record-view';
import { StatsView } from './stats-view';
import { TableView } from './table-view';
import { UnsupportedView } from './unsupported-view';

// The chart views pull in recharts (~300KB). The query page always opens on the
// composer rather than a chart, so we load these on demand — recharts only ships
// once a chart result actually renders, not on every page visit.
const BarsView = lazy(() =>
    import('./bars-view').then((m) => ({ default: m.BarsView })),
);
const StackedBarsView = lazy(() =>
    import('./stacked-bars-view').then((m) => ({ default: m.StackedBarsView })),
);
const PieView = lazy(() =>
    import('./pie-view').then((m) => ({ default: m.PieView })),
);
const HistogramView = lazy(() =>
    import('./histogram-view').then((m) => ({ default: m.HistogramView })),
);
const TimeseriesView = lazy(() =>
    import('./timeseries-view').then((m) => ({ default: m.TimeseriesView })),
);

function ChartFallback() {
    return (
        <div
            className="rdw-skel h-[220px] w-full rounded-[12px]"
            aria-hidden="true"
        />
    );
}

export function ResultBody({
    result,
    locale,
}: {
    result: QueryResult;
    locale: string;
}) {
    const { t } = useTranslation();
    const { rows, displayHint, plan } = result;

    // Unsupported is a refusal, not a data shortage — show it before the empty
    // check so an off-topic question doesn't render as "no rows matched".
    if (displayHint === 'unsupported') {
        return <UnsupportedView />;
    }

    // A derived figure (percentage / ratio / group share) is computed by the
    // engine from the steps; render the single number regardless of how many
    // rows the source query returned.
    const derived = result.presentation?.derived ?? null;

    if (derived) {
        return <DerivedView derived={derived} locale={locale} />;
    }

    if (rows.length === 0) {
        return (
            <p className="text-sm text-neutral-500">
                {t('pages.query.noRows')}
            </p>
        );
    }

    const table = <TableView rows={rows} plan={plan} locale={locale} />;

    switch (displayHint) {
        case 'count':
            return <CountView rows={rows} plan={plan} locale={locale} />;
        case 'stats':
            return <StatsView rows={rows} plan={plan} locale={locale} />;
        case 'bars':
            return (
                <Suspense fallback={<ChartFallback />}>
                    <BarsView
                        rows={rows}
                        plan={plan}
                        locale={locale}
                        fallback={table}
                    />
                </Suspense>
            );
        case 'stacked_bars':
            return (
                <Suspense fallback={<ChartFallback />}>
                    <StackedBarsView
                        rows={rows}
                        plan={plan}
                        locale={locale}
                        fallback={table}
                    />
                </Suspense>
            );
        case 'pie':
            return (
                <Suspense fallback={<ChartFallback />}>
                    <PieView
                        rows={rows}
                        plan={plan}
                        locale={locale}
                        fallback={table}
                    />
                </Suspense>
            );
        case 'histogram':
            return (
                <Suspense fallback={<ChartFallback />}>
                    <HistogramView
                        rows={rows}
                        plan={plan}
                        locale={locale}
                        fallback={table}
                    />
                </Suspense>
            );
        case 'timeseries':
            return (
                <Suspense fallback={<ChartFallback />}>
                    <TimeseriesView
                        rows={rows}
                        plan={plan}
                        locale={locale}
                        fallback={table}
                    />
                </Suspense>
            );
        case 'record':
            return <RecordView rows={rows} locale={locale} />;
        case 'table':
        default:
            return table;
    }
}
