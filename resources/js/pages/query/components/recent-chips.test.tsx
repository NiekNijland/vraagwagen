import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { renderWithI18n } from '@/test/render';

import { RecentChips } from './recent-chips';

describe('RecentChips', () => {
    it('shows only the newest recent queries without an inner scroll area', () => {
        renderWithI18n(
            <RecentChips
                items={Array.from(
                    { length: 12 },
                    (_, index) => `Recent query ${index + 1}`,
                )}
                onPick={vi.fn()}
                onClearAll={vi.fn()}
            />,
        );

        const list = screen.getByTestId('recent-chips-list');

        expect(list.className).not.toContain('max-h-28');
        expect(list.className).not.toContain('overflow-y-auto');
        expect(screen.getByText('Recent query 1')).toBeInTheDocument();
        expect(screen.getByText('Recent query 4')).toBeInTheDocument();
        expect(screen.queryByText('Recent query 5')).not.toBeInTheDocument();
    });
});
