type AccessibleChartTableProps = {
    caption: string;
    columns: string[];
    rows: string[][];
};

export function AccessibleChartTable({
    caption,
    columns,
    rows,
}: AccessibleChartTableProps) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <div className="sr-only">
            <table>
                <caption>{caption}</caption>
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column} scope="col">
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, rowIndex) => (
                        <tr key={rowIndex}>
                            {row.map((value, valueIndex) => (
                                <td key={`${rowIndex}-${valueIndex}`}>
                                    {value}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
