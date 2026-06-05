import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { localeTag } from '@/pages/query/format';
import {
    exportMethod as feedbackExport,
    index as feedbackIndex,
} from '@/routes/admin/feedback';
import { show as queriesShow } from '@/routes/admin/queries';
import { index as statsIndex } from '@/routes/admin/stats';
import type { AdminFeedbackItem, Paginated } from '@/types';

const ANY = '_any';

export default function AdminFeedbackIndex({
    runs,
    filters,
}: {
    runs: Paginated<AdminFeedbackItem>;
    filters: { rating: string | null };
}) {
    const { t } = useTranslation();
    const locale = String(usePage().props.locale ?? 'nl');

    setLayoutProps({
        breadcrumbs: [
            { title: t('pages.admin.breadcrumb'), href: statsIndex() },
            {
                title: t('pages.admin.feedback.breadcrumb'),
                href: feedbackIndex(),
            },
        ],
    });

    return (
        <>
            <Head title={t('pages.admin.feedback.title')} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('pages.admin.feedback.title')}
                    </h1>
                    <div className="flex gap-2">
                        <Select
                            value={filters.rating ?? ANY}
                            onValueChange={(value) =>
                                router.get(
                                    feedbackIndex.url({
                                        query:
                                            value === ANY
                                                ? {}
                                                : { rating: value },
                                    }),
                                    {},
                                    { preserveState: true, replace: true },
                                )
                            }
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>
                                    {t('pages.admin.queries.anyRating')}
                                </SelectItem>
                                <SelectItem value="up">👍</SelectItem>
                                <SelectItem value="down">👎</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={feedbackExport.url(
                                    filters.rating !== null
                                        ? {
                                              query: {
                                                  rating: filters.rating,
                                              },
                                          }
                                        : undefined,
                                )}
                            >
                                <Download />
                                {t('pages.admin.exportCsv')}
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('pages.admin.feedback.ratedAt')}
                                </TableHead>
                                <TableHead>
                                    {t('pages.admin.queries.rating')}
                                </TableHead>
                                <TableHead>
                                    {t('pages.admin.queries.prompt')}
                                </TableHead>
                                <TableHead className="w-full">
                                    {t('pages.admin.feedback.comment')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {runs.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        {t('pages.admin.feedback.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {runs.data.map((run) => (
                                <TableRow key={run.id}>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {run.ratedAt !== null
                                            ? new Date(
                                                  run.ratedAt,
                                              ).toLocaleString(
                                                  localeTag(locale),
                                                  {
                                                      dateStyle: 'short',
                                                      timeStyle: 'short',
                                                  },
                                              )
                                            : '—'}
                                    </TableCell>
                                    <TableCell>
                                        {run.rating === 'up' ? (
                                            <Badge variant="secondary">
                                                👍
                                            </Badge>
                                        ) : (
                                            <Badge variant="destructive">
                                                👎
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="max-w-72">
                                        <Link
                                            href={queriesShow(run.id)}
                                            className="block truncate font-medium hover:underline"
                                            prefetch
                                        >
                                            {run.prompt}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {run.comment ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <PaginationNav paginator={runs} />
            </div>
        </>
    );
}
