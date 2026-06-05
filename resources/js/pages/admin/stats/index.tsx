import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { useTranslation } from '@/hooks/use-translation';
import { localeTag } from '@/pages/query/format';
import { index as statsIndex } from '@/routes/admin/stats';
import type { AdminStats } from '@/types';

const WINDOWS = [7, 30, 90];

export default function AdminStatsIndex({
    stats,
    days,
}: {
    stats: AdminStats;
    days: number;
}) {
    const { t } = useTranslation();
    const locale = String(usePage().props.locale ?? 'nl');

    setLayoutProps({
        breadcrumbs: [
            { title: t('pages.admin.breadcrumb'), href: statsIndex() },
            { title: t('pages.admin.stats.breadcrumb'), href: statsIndex() },
        ],
    });

    const ratedCount = stats.totals.up + stats.totals.down;
    const upShare =
        ratedCount > 0
            ? Math.round((stats.totals.up / ratedCount) * 100)
            : null;

    const queriesConfig = {
        queries: {
            label: t('pages.admin.stats.queries'),
            color: 'var(--chart-1)',
        },
    } satisfies ChartConfig;

    const costConfig = {
        cost: {
            label: t('pages.admin.stats.cost'),
            color: 'var(--chart-2)',
        },
    } satisfies ChartConfig;

    const feedbackConfig = {
        up: {
            label: t('pages.admin.stats.thumbsUp'),
            color: 'var(--chart-1)',
        },
        down: {
            label: t('pages.admin.stats.thumbsDown'),
            color: 'var(--chart-5)',
        },
    } satisfies ChartConfig;

    const tickFormatter = (value: string) =>
        new Date(value).toLocaleDateString(localeTag(locale), {
            day: 'numeric',
            month: 'short',
        });

    return (
        <>
            <Head title={t('pages.admin.stats.title')} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('pages.admin.stats.title')}
                    </h1>
                    <div className="flex gap-1">
                        {WINDOWS.map((window) => (
                            <Button
                                key={window}
                                variant={
                                    window === days ? 'default' : 'outline'
                                }
                                size="sm"
                                onClick={() =>
                                    router.get(
                                        statsIndex.url({
                                            query: { days: window },
                                        }),
                                        {},
                                        { preserveState: true, replace: true },
                                    )
                                }
                            >
                                {t('pages.admin.stats.window', {
                                    days: window,
                                })}
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label={t('pages.admin.stats.totalQueries')}
                        value={stats.totals.queries.toLocaleString(
                            localeTag(locale),
                        )}
                        hint={t('pages.admin.stats.allTime', {
                            count: stats.totals.allTimeQueries.toLocaleString(
                                localeTag(locale),
                            ),
                        })}
                    />
                    <StatCard
                        label={t('pages.admin.stats.cost')}
                        value={`$${stats.totals.cost.toFixed(4)}`}
                    />
                    <StatCard
                        label={t('pages.admin.stats.tokens')}
                        value={(
                            stats.totals.promptTokens +
                            stats.totals.completionTokens
                        ).toLocaleString(localeTag(locale))}
                        hint={t('pages.admin.stats.tokensSplit', {
                            prompt: stats.totals.promptTokens.toLocaleString(
                                localeTag(locale),
                            ),
                            completion:
                                stats.totals.completionTokens.toLocaleString(
                                    localeTag(locale),
                                ),
                        })}
                    />
                    <StatCard
                        label={t('pages.admin.stats.feedback')}
                        value={
                            upShare !== null
                                ? `${upShare}% 👍`
                                : t('pages.admin.stats.noFeedback')
                        }
                        hint={t('pages.admin.stats.feedbackSplit', {
                            up: stats.totals.up,
                            down: stats.totals.down,
                        })}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('pages.admin.stats.queriesPerDay')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ChartContainer
                            config={queriesConfig}
                            className="h-[260px] w-full"
                        >
                            <BarChart data={stats.perDay}>
                                <CartesianGrid
                                    vertical={false}
                                    strokeDasharray="3 3"
                                />
                                <XAxis
                                    dataKey="date"
                                    tickLine={false}
                                    axisLine={false}
                                    tick={{ fontSize: 11 }}
                                    tickFormatter={tickFormatter}
                                    minTickGap={16}
                                />
                                <YAxis
                                    tickLine={false}
                                    axisLine={false}
                                    width={40}
                                    tick={{ fontSize: 11 }}
                                    allowDecimals={false}
                                />
                                <ChartTooltip
                                    content={<ChartTooltipContent />}
                                />
                                <Bar
                                    dataKey="queries"
                                    fill="var(--chart-1)"
                                    radius={[4, 4, 0, 0]}
                                />
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('pages.admin.stats.costPerDay')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={costConfig}
                                className="h-[220px] w-full"
                            >
                                <BarChart data={stats.perDay}>
                                    <CartesianGrid
                                        vertical={false}
                                        strokeDasharray="3 3"
                                    />
                                    <XAxis
                                        dataKey="date"
                                        tickLine={false}
                                        axisLine={false}
                                        tick={{ fontSize: 11 }}
                                        tickFormatter={tickFormatter}
                                        minTickGap={16}
                                    />
                                    <YAxis
                                        tickLine={false}
                                        axisLine={false}
                                        width={56}
                                        tick={{ fontSize: 11 }}
                                        tickFormatter={(value) =>
                                            `$${Number(value).toFixed(3)}`
                                        }
                                    />
                                    <ChartTooltip
                                        content={
                                            <ChartTooltipContent
                                                formatter={(value) =>
                                                    `$${Number(value).toFixed(4)}`
                                                }
                                            />
                                        }
                                    />
                                    <Bar
                                        dataKey="cost"
                                        fill="var(--chart-2)"
                                        radius={[4, 4, 0, 0]}
                                    />
                                </BarChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('pages.admin.stats.feedbackPerDay')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={feedbackConfig}
                                className="h-[220px] w-full"
                            >
                                <BarChart data={stats.perDay}>
                                    <CartesianGrid
                                        vertical={false}
                                        strokeDasharray="3 3"
                                    />
                                    <XAxis
                                        dataKey="date"
                                        tickLine={false}
                                        axisLine={false}
                                        tick={{ fontSize: 11 }}
                                        tickFormatter={tickFormatter}
                                        minTickGap={16}
                                    />
                                    <YAxis
                                        tickLine={false}
                                        axisLine={false}
                                        width={40}
                                        tick={{ fontSize: 11 }}
                                        allowDecimals={false}
                                    />
                                    <ChartTooltip
                                        content={<ChartTooltipContent />}
                                    />
                                    <Bar
                                        dataKey="up"
                                        stackId="feedback"
                                        fill="var(--chart-1)"
                                    />
                                    <Bar
                                        dataKey="down"
                                        stackId="feedback"
                                        fill="var(--chart-5)"
                                        radius={[4, 4, 0, 0]}
                                    />
                                </BarChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function StatCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-semibold">{value}</p>
                {hint && (
                    <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
                )}
            </CardContent>
        </Card>
    );
}
