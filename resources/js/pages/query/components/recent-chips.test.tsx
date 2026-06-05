import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { renderWithI18n } from '@/test/render';

import { RecentChips } from './recent-chips';

describe('RecentChips', () => {
    it('keeps long recent-query lists inside a scrollable area', () => {
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

        expect(list.className).toContain('max-h-28');
        expect(list.className).toContain('overflow-y-auto');
    });
});
