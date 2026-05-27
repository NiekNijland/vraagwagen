import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { formatResponseBody, SoQLHighlight } from './soql-highlight';

describe('formatResponseBody', () => {
    it('pretty-prints valid JSON', () => {
        expect(formatResponseBody('{"a":1}')).toBe('{\n  "a": 1\n}');
    });

    it('returns the original text when it is not JSON', () => {
        expect(formatResponseBody('not json')).toBe('not json');
    });
});

describe('SoQLHighlight', () => {
    it('preserves the full query text across the highlighted spans', () => {
        const query = "SELECT merk WHERE bouwjaar = '2020'";
        const { container } = render(<SoQLHighlight value={query} />);

        expect(container.textContent).toBe(query);
    });

    it('wraps keywords in an accent-colored span', () => {
        render(<SoQLHighlight value="SELECT merk" />);

        const keyword = screen.getByText('SELECT');
        expect(keyword.className).toContain('--rdw-orange');
    });

    it('preserves newlines in multi-line queries', () => {
        const query = 'SELECT merk\nWHERE jaar = 2020';
        const { container } = render(<SoQLHighlight value={query} />);

        expect(container.textContent).toBe(query);
    });
});
