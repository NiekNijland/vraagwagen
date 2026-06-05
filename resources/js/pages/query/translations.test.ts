import { describe, expect, it } from 'vitest';

const langFiles = import.meta.glob('/lang/*.json', { eager: true }) as Record<
    string,
    Record<string, string>
>;

describe('query column translations', () => {
    it('keeps English and Dutch page column labels in parity', () => {
        const english = langFiles['/lang/en.json'] ?? {};
        const dutch = langFiles['/lang/nl.json'] ?? {};

        const englishKeys = Object.keys(english)
            .filter((key) => key.startsWith('pages.query.columns.'))
            .sort();
        const dutchKeys = Object.keys(dutch)
            .filter((key) => key.startsWith('pages.query.columns.'))
            .sort();

        expect(englishKeys).toEqual(dutchKeys);
    });
});
