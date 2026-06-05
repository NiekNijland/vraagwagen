import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
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
import { index as statsIndex } from '@/routes/admin/stats';
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminUserItem, Paginated } from '@/types';

export default function AdminUsersIndex({
    users,
    anonymousQueryCount,
}: {
    users: Paginated<AdminUserItem>;
    anonymousQueryCount: number;
}) {
    const { t } = useTranslation();
    const locale = String(usePage().props.locale ?? 'nl');

    setLayoutProps({
        breadcrumbs: [
            { title: t('pages.admin.breadcrumb'), href: statsIndex() },
            { title: t('pages.admin.users.breadcrumb'), href: usersIndex() },
        ],
    });

    return (
        <>
            <Head title={t('pages.admin.users.title')} />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('pages.admin.users.title')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('pages.admin.users.anonymousNote', {
                            count: anonymousQueryCount.toLocaleString(
                                localeTag(locale),
                            ),
                        })}
                    </p>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('pages.admin.users.name')}
                                </TableHead>
                                <TableHead className="w-full">
                                    {t('pages.admin.users.email')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('pages.admin.users.queryCount')}
                                </TableHead>
                                <TableHead>
                                    {t('pages.admin.users.lastQueryAt')}
                                </TableHead>
                                <TableHead>
                                    {t('pages.admin.users.registeredAt')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-medium whitespace-nowrap">
                                        <span className="inline-flex items-center gap-2">
                                            {user.name}
                                            {user.isAdmin && (
                                                <Badge>
                                                    {t(
                                                        'pages.admin.users.admin',
                                                    )}
                                                </Badge>
                                            )}
                                            {!user.verified && (
                                                <Badge variant="outline">
                                                    {t(
                                                        'pages.admin.users.unverified',
                                                    )}
                                                </Badge>
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {user.email}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {user.queryCount.toLocaleString(
                                            localeTag(locale),
                                        )}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {user.lastQueryAt !== null
                                            ? new Date(
                                                  user.lastQueryAt,
                                              ).toLocaleString(
                                                  localeTag(locale),
                                                  {
                                                      dateStyle: 'short',
                                                      timeStyle: 'short',
                                                  },
                                              )
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {user.createdAt !== null
                                            ? new Date(
                                                  user.createdAt,
                                              ).toLocaleDateString(
                                                  localeTag(locale),
                                              )
                                            : '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <PaginationNav paginator={users} />
            </div>
        </>
    );
}
