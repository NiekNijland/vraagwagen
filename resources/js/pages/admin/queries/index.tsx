import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { Download, MessageSquareText } from 'lucide-react';
import { useState } from 'react';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    exportMethod as queriesExport,
    index as queriesIndex,
    show as queriesShow,
} from '@/routes/admin/queries';
import { index as statsIndex } from '@/routes/admin/stats';
import type { AdminQueryFilters, AdminQueryListItem, Paginated } from '@/types';

const ANY = '_any';

export default function AdminQueriesIndex({
    runs,
    filters,
}: {
    runs: Paginated<AdminQueryListItem>;
    filters: AdminQueryFilters;
}) {
    const { t } = useTranslation();
    const locale = String(usePage().props.locale ?? 'nl');
    const [search, setSearch] = useState(filters.search ?? '');

    setLayoutProps({
        breadcrumbs: [
            { title: t('pages.admin.breadcrumb'), href: statsIndex() },
            {
                title: t('pages.admin.queries.breadcrumb'),
                href: queriesIndex(),
            },
        ],
    });

    const activeFilters = (overrides: Partial<AdminQueryFilters>) => {
        const merged = { ...filters, search, ...overrides };

        return Object.fromEntries(
            Object.entries(merged).filter(
                ([, value]) => value !== null && value !== '',
            ),
        );
    };

    const applyFilters = (overrides: Partial<AdminQueryFilters> = {}) => {
        router.get(
            queriesIndex.url({ query: activeFilters(overrides) }),
            {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title={t('pages.admin.queries.title')} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('pages.admin.queries.title')}
                    </h1>
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={queriesExport.url({
                                query: activeFilters({}),
                            })}
                        >
                            <Download />
                            {t('pages.admin.exportCsv')}
                        </a>
                    </Button>
                </div>

                <div className="flex flex-wrap items-end gap-2">
                    <form
                        className="min-w-48 flex-1"
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilters({ search });
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t(
                                'pages.admin.queries.searchPlaceholder',
                            )}
                            aria-label={t(
                                'pages.admin.queries.searchPlaceholder',
                            )}
                        />
                    </form>
                    <Select
                        value={filters.rating ?? ANY}
                        onValueChange={(value) =>
                            applyFilters({
                                rating: value === ANY ? null : value,
                            })
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
                    <Select
                        value={filters.locale ?? ANY}
                        onValueChange={(value) =>
                            applyFilters({
                                locale: value === ANY ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>
                                {t('pages.admin.queries.anyLocale')}
                            </SelectItem>
                            <SelectItem value="nl">NL</SelectItem>
                            <SelectItem value="en">EN</SelectItem>
                        </SelectContent>
                    </Select>
                    <Input
                        type="date"
                        className="w-40"
                        value={filters.from ?? ''}
                        onChange={(event) =>
                            applyFilters({ from: event.target.value || null })
                        }
                        aria-label={t('pages.admin.queries.from')}
                    />
                    <Input
                        type="date"
                        className="w-40"
                        value={filters.to ?? ''}
                        onChange={(event) =>
                            applyFilters({ to: event.target.value || null })
                        }
                        aria-label={t('pages.admin.queries.to')}
                    />
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('pages.admin.queries.when')}
                                </TableHead>
                                <TableHead className="w-full">
                                    {t('pages.admin.queries.prompt')}
                                </TableHead>
                                <TableHead>
                                    {t('pages.admin.queries.locale')}
                                </TableHead>
                                <TableHead>
                                    {t('pages.admin.queries.rating')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('pages.admin.queries.tokens')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('pages.admin.queries.cost')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {runs.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        {t('pages.admin.queries.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {runs.data.map((run) => (
                                <TableRow key={run.id}>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {new Date(run.createdAt).toLocaleString(
                                            localeTag(locale),
                                            {
                                                dateStyle: 'short',
                                                timeStyle: 'short',
                                            },
                                        )}
                                    </TableCell>
                                    <TableCell className="max-w-0">
                                        <Link
                                            href={queriesShow(run.id)}
                                            className="block truncate font-medium hover:underline"
                                            prefetch
                                        >
                                            {run.prompt}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground uppercase">
                                        {run.locale}
                                    </TableCell>
                                    <TableCell>
                                        <span className="inline-flex items-center gap-1">
                                            {run.rating === 'up' && (
                                                <Badge variant="secondary">
                                                    👍
                                                </Badge>
                                            )}
                                            {run.rating === 'down' && (
                                                <Badge variant="destructive">
                                                    👎
                                                </Badge>
                                            )}
                                            {run.hasComment && (
                                                <MessageSquareText className="size-4 text-muted-foreground" />
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {run.totalTokens.toLocaleString(
                                            localeTag(locale),
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {run.estimatedCost !== null
                                            ? `$${run.estimatedCost.toFixed(4)}`
                                            : '—'}
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
