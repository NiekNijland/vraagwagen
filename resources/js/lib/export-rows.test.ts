import { describe, expect, it } from 'vitest';

import { rowsToCsv } from './export-rows';

describe('rowsToCsv', () => {
    it('prefixes spreadsheet formulas to prevent CSV injection', () => {
        const csv = rowsToCsv([
            {
                Brand: '=SUM(A1:A2)',
                Comment: ' @cmd',
                Safe: 'VOLKSWAGEN',
            },
        ]);

        expect(csv).toContain("'=SUM(A1:A2)");
        expect(csv).toContain("' @cmd");
        expect(csv).toContain('VOLKSWAGEN');
    });
});
