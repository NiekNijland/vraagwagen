import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';

import {
    bucketForColumn,
    formatBucketLabel,
    formatCell,
    localeTag,
    translateColumn,
} from '../format';
import type { Plan, QueryRow } from '../types';

// Cap the rows we paint into the DOM. A normal table is already bounded by the
// plan's limit, but a chart that can't render falls back here and may hand us
// the full (capped) projection — thousands of rows. Rendering them all
// synchronously janks the page; show a header slice with a "showing N of M"
// footer instead.
const MAX_VISIBLE_ROWS = 250;

export function TableView({
    rows,
    plan,
    locale,
}: {
    rows: QueryRow[];
    plan: Plan;
    locale: string;
}) {
    const { t } = useTranslation();
    const columns = Object.keys(rows[0] ?? {});
    const visibleRows = rows.slice(0, MAX_VISIBLE_ROWS);
    const isTruncated = rows.length > visibleRows.length;

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        {columns.map((c) => (
                            <TableHead key={c} className="text-xs">
                                {translateColumn(c, t)}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {visibleRows.map((row, i) => (
                        <TableRow key={i}>
                            {columns.map((c) => {
                                const value = row[c];
                                const bucket = bucketForColumn(plan, c);
                                const formatted =
                                    bucket !== null
                                        ? formatBucketLabel(
                                              value,
                                              bucket,
                                              locale,
                                          )
                                        : formatCell(value, locale, t);

                                return (
                                    <TableCell key={c} className="text-xs">
                                        {formatted}
                                    </TableCell>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {isTruncated && (
                <p className="mt-2 px-1 text-[11.5px] text-muted-foreground">
                    {t('pages.query.tableTruncated', {
                        shown: visibleRows.length.toLocaleString(
                            localeTag(locale),
                        ),
                        total: rows.length.toLocaleString(localeTag(locale)),
                    })}
                </p>
            )}
        </div>
    );
}
