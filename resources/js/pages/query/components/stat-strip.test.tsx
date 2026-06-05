import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { renderWithI18n } from '@/test/render';

import { StatStrip } from './stat-strip';

describe('StatStrip', () => {
    it('renders the platform figures with locale-aware formatting', () => {
        renderWithI18n(
            <StatStrip
                stats={{
                    vehicles: 16247892,
                    datasets: 10,
                    queriesAnswered: 1234,
                }}
                locale="nl"
            />,
            { locale: 'nl' },
        );

        expect(screen.getByText('16.247.892')).toBeInTheDocument();
        expect(screen.getByText('voertuigen')).toBeInTheDocument();
        expect(screen.getByText('10')).toBeInTheDocument();
        expect(screen.getByText('datasets')).toBeInTheDocument();
        expect(screen.getByText('dagelijks')).toBeInTheDocument();
        expect(screen.getByText('ververst')).toBeInTheDocument();
        expect(screen.getByText('1.234')).toBeInTheDocument();
        expect(screen.getByText('vragen beantwoord')).toBeInTheDocument();
    });

    it('hides the vehicle figure when unavailable and the answered count when zero', () => {
        renderWithI18n(
            <StatStrip
                stats={{ vehicles: null, datasets: 10, queriesAnswered: 0 }}
                locale="en"
            />,
        );

        expect(screen.queryByText('vehicles')).not.toBeInTheDocument();
        expect(
            screen.queryByText('questions answered'),
        ).not.toBeInTheDocument();
        expect(screen.getByText('datasets')).toBeInTheDocument();
    });

    it('shows a pulsing skeleton while the deferred stats are loading', () => {
        renderWithI18n(<StatStrip stats={undefined} locale="en" />);

        expect(screen.getByTestId('stat-strip-skeleton')).toBeInTheDocument();
        // The source links render regardless of the deferred stats.
        expect(screen.getByText('opendata.rdw.nl')).toBeInTheDocument();
    });
});
