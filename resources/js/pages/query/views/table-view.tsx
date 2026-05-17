import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';

import { formatCell, humanizePascalCase } from '../format';
import type { QueryRow } from '../types';

export function TableView({
    rows,
    locale,
}: {
    rows: QueryRow[];
    locale: string;
}) {
    const { t } = useTranslation();
    const columns = Object.keys(rows[0] ?? {});

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        {columns.map((c) => (
                            <TableHead key={c} className="text-xs">
                                {humanizePascalCase(c)}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row, i) => (
                        <TableRow key={i}>
                            {columns.map((c) => (
                                <TableCell key={c} className="text-xs">
                                    {formatCell(row[c], locale, t)}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
