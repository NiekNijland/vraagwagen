import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { localeTag } from '@/pages/query/format';
import {
    index as queriesIndex,
    show as queriesShow,
} from '@/routes/admin/queries';
import { index as statsIndex } from '@/routes/admin/stats';
import type { AdminQueryDetail } from '@/types';

export default function AdminQueriesShow({ run }: { run: AdminQueryDetail }) {
    const { t } = useTranslation();
    const locale = String(usePage().props.locale ?? 'nl');

    setLayoutProps({
        breadcrumbs: [
            { title: t('pages.admin.breadcrumb'), href: statsIndex() },
            {
                title: t('pages.admin.queries.breadcrumb'),
                href: queriesIndex(),
            },
            { title: run.slug, href: queriesShow(run.id) },
        ],
    });

    const facts: [string, string][] = [
        [
            t('pages.admin.queryDetail.created'),
            new Date(run.createdAt).toLocaleString(localeTag(locale)),
        ],
        [t('pages.admin.queryDetail.locale'), run.locale.toUpperCase()],
        [t('pages.admin.queryDetail.model'), run.model ?? '—'],
        [t('pages.admin.queryDetail.correlationId'), run.correlationId ?? '—'],
        [
            t('pages.admin.queryDetail.user'),
            run.userId ?? t('pages.admin.queryDetail.anonymous'),
        ],
        [t('pages.admin.queryDetail.displayHint'), run.displayHint],
    ];

    const tokens: [string, number | null][] = [
        [t('pages.admin.queryDetail.promptTokens'), run.promptTokens],
        [t('pages.admin.queryDetail.completionTokens'), run.completionTokens],
        [t('pages.admin.queryDetail.cacheReadTokens'), run.cacheReadTokens],
        [t('pages.admin.queryDetail.thoughtTokens'), run.thoughtTokens],
    ];

    return (
        <>
            <Head title={run.prompt} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {run.prompt}
                        </h1>
                        <a
                            href={`/${run.locale}/${run.slug}`}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-1 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                        >
                            /{run.locale}/{run.slug}
                            <ExternalLink className="size-3.5" />
                        </a>
                    </div>
                    <div className="flex items-center gap-2">
                        {run.rating === 'up' && (
                            <Badge variant="secondary">
                                👍 {t('pages.admin.queryDetail.ratedUp')}
                            </Badge>
                        )}
                        {run.rating === 'down' && (
                            <Badge variant="destructive">
                                👎 {t('pages.admin.queryDetail.ratedDown')}
                            </Badge>
                        )}
                    </div>
                </div>

                {run.comment !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('pages.admin.queryDetail.comment')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <blockquote className="border-l-2 pl-4 text-sm italic">
                                {run.comment}
                            </blockquote>
                            {run.ratedAt !== null && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {new Date(run.ratedAt).toLocaleString(
                                        localeTag(locale),
                                    )}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('pages.admin.queryDetail.metadata')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                                {facts.map(([label, value]) => (
                                    <div key={label} className="contents">
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="font-mono break-all">
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('pages.admin.queryDetail.usage')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                                {tokens.map(([label, value]) => (
                                    <div key={label} className="contents">
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {value !== null
                                                ? value.toLocaleString(
                                                      localeTag(locale),
                                                  )
                                                : '—'}
                                        </dd>
                                    </div>
                                ))}
                                <div className="contents">
                                    <dt className="text-muted-foreground">
                                        {t('pages.admin.queryDetail.cost')}
                                    </dt>
                                    <dd className="tabular-nums">
                                        {run.estimatedCost !== null
                                            ? `$${run.estimatedCost.toFixed(6)}`
                                            : '—'}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <JsonCard
                    title={t('pages.admin.queryDetail.plan')}
                    value={run.plan}
                />
                <JsonCard
                    title={t('pages.admin.queryDetail.soql')}
                    value={run.soql}
                    extra={
                        <a
                            href={run.url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-xs break-all text-muted-foreground hover:underline"
                        >
                            {run.url}
                        </a>
                    }
                />
                {run.steps !== null && run.steps.length > 0 && (
                    <JsonCard
                        title={t('pages.admin.queryDetail.steps')}
                        value={run.steps}
                    />
                )}
                <JsonCard
                    title={t('pages.admin.queryDetail.rows', {
                        shown: run.rows.length,
                        total: run.rowCount,
                    })}
                    value={run.rows}
                />
                {run.presentation !== null && (
                    <JsonCard
                        title={t('pages.admin.queryDetail.presentation')}
                        value={run.presentation}
                    />
                )}
            </div>
        </>
    );
}

function JsonCard({
    title,
    value,
    extra,
}: {
    title: string;
    value: unknown;
    extra?: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {extra}
            </CardHeader>
            <CardContent>
                <pre className="max-h-96 overflow-auto rounded-lg bg-muted p-4 text-xs">
                    {JSON.stringify(value, null, 2)}
                </pre>
            </CardContent>
        </Card>
    );
}
