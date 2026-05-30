import {
    Bar,
    BarChart,
    CartesianGrid,
    LabelList,
    XAxis,
    YAxis,
} from 'recharts';

import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { useCountUp } from '@/hooks/use-count-up';

import { findNumericKey, formatBucketLabel, formatNumber } from '../format';
import type { Bucket, Plan, QueryRow } from '../types';

// Each bar reads comfortably at this row height; the plot height scales with the
// bar count so a handful of categories doesn't sit in a half-empty 360px box.
const ROW_HEIGHT = 44;
const MIN_CHART_HEIGHT = 160;
const MAX_CHART_HEIGHT = 560;

export function BarsView({
    rows,
    plan,
    locale,
    fallback,
}: {
    rows: QueryRow[];
    plan: Plan;
    locale: string;
    fallback: React.ReactNode;
}) {
    const firstRow = rows[0] ?? {};
    const groupKey =
        plan.groupBy[0]?.field ??
        Object.keys(firstRow).find((k) => typeof firstRow[k] === 'string') ??
        Object.keys(firstRow)[0];
    const bucket: Bucket = plan.groupBy[0]?.bucket ?? 'none';
    const valueKey =
        plan.aggregates[0]?.alias ?? findNumericKey(firstRow, groupKey);

    if (
        groupKey === undefined ||
        valueKey === undefined ||
        valueKey === groupKey
    ) {
        return <>{fallback}</>;
    }

    const data = rows
        .map((r) => ({
            label: formatBucketLabel(r[groupKey], bucket, locale),
            value: Number(r[valueKey] ?? 0),
        }))
        .filter((d) => Number.isFinite(d.value))
        .sort((a, b) => b.value - a.value)
        .slice(0, 25);

    // A single category has nothing to compare against, so a lone bar in a tall
    // plot just reads as empty space. Show it as a headline figure instead.
    if (data.length === 1) {
        const sole = data[0]!;

        return (
            <SingleValue
                label={sole.label}
                value={sole.value}
                locale={locale}
            />
        );
    }

    const config = {
        value: {
            label: plan.aggregates[0]?.alias ?? 'count',
            color: 'var(--rdw-orange, var(--chart-1))',
        },
    } satisfies ChartConfig;

    const chartHeight = Math.min(
        MAX_CHART_HEIGHT,
        Math.max(MIN_CHART_HEIGHT, data.length * ROW_HEIGHT),
    );

    return (
        <ChartContainer
            config={config}
            className="w-full"
            style={{ height: chartHeight }}
        >
            <BarChart
                data={data}
                layout="vertical"
                margin={{ left: 12, right: 48 }}
            >
                <CartesianGrid horizontal={false} />
                <XAxis type="number" hide />
                <YAxis
                    dataKey="label"
                    type="category"
                    tickLine={false}
                    axisLine={false}
                    width={120}
                    tick={{ fontSize: 12 }}
                />
                <ChartTooltip
                    cursor={false}
                    content={<ChartTooltipContent indicator="line" />}
                />
                <Bar
                    dataKey="value"
                    fill="var(--rdw-orange, var(--chart-1))"
                    radius={4}
                >
                    <LabelList
                        dataKey="value"
                        position="right"
                        className="fill-foreground text-xs"
                        formatter={(v) => formatNumber(v, locale)}
                    />
                </Bar>
            </BarChart>
        </ChartContainer>
    );
}

function SingleValue({
    label,
    value,
    locale,
}: {
    label: string;
    value: number;
    locale: string;
}) {
    const animated = useCountUp(value, 900, true);

    return (
        <div className="flex flex-col items-center py-6 text-center">
            <div className="text-5xl font-semibold tracking-[-0.03em] text-[var(--rdw-orange)] tabular-nums">
                {formatNumber(Math.round(animated), locale)}
            </div>
            <div className="mt-1 text-sm text-muted-foreground">{label}</div>
        </div>
    );
}
