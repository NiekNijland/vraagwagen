import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import type { Derived } from '../types';
import { DerivedView } from './derived-view';

describe('DerivedView', () => {
    it('renders a percentage headline with its operands', () => {
        const derived: Derived = {
            op: 'percentage',
            value: 0.032,
            numerator: 320,
            denominator: 10000,
        };

        render(<DerivedView derived={derived} locale="en" />);

        expect(screen.getByText('3.2%')).toBeInTheDocument();
        expect(screen.getByText(/320/)).toBeInTheDocument();
        expect(screen.getByText(/10,000/)).toBeInTheDocument();
    });

    it('renders a ratio headline as a plain number with a slash operand', () => {
        const derived: Derived = {
            op: 'ratio',
            value: 1.5,
            numerator: 3,
            denominator: 2,
        };

        const { container } = render(
            <DerivedView derived={derived} locale="en" />,
        );

        expect(screen.getByText('1.5')).toBeInTheDocument();
        expect(container.textContent).toContain('/');
    });
});
