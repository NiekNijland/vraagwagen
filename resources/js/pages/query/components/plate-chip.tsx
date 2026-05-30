import { cn } from '@/lib/utils';

import { isMotorcyclePlate, splitPlateLines } from '../plate';

// ─── Plate chip ───────────────────────────────────────────────
// Renders the yellow NL plate badge. M-series plates are motorcycles, which
// carry a square two-line plate, so the value stacks instead of running wide.
export function PlateChip({
    plate,
    className,
}: {
    plate: string;
    className?: string;
}) {
    if (isMotorcyclePlate(plate)) {
        const [top, bottom] = splitPlateLines(plate);

        return (
            <span
                className={cn('rdw-plate rdw-plate--moto', className)}
                aria-hidden="true"
            >
                <span className="rdw-plate-flag">NL</span>
                <span className="rdw-plate-text">
                    <span>{top}</span>
                    {bottom !== '' && <span>{bottom}</span>}
                </span>
            </span>
        );
    }

    return (
        <span className={cn('rdw-plate', className)} aria-hidden="true">
            <span className="rdw-plate-flag">NL</span>
            <span className="rdw-plate-text">{plate}</span>
        </span>
    );
}
