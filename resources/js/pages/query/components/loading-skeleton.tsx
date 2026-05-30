import { useEffect, useMemo, useState } from 'react';

import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

// Pacing for the three-stage skeleton: the first stage flips slightly faster so the user sees motion
// almost immediately, then later stages dwell long enough to read. Tuned for ~2-3s typical answers.
const LOADING_STAGE_DELAYS_MS = { first: 700, subsequent: 1100 } as const;

// ─── Loading skeleton ─────────────────────────────────────────
export function LoadingSkeleton() {
    const { t } = useTranslation();
    const stages = useMemo(
        () => [
            t('pages.query.loadingPlanning'),
            t('pages.query.loadingQuerying'),
            t('pages.query.loadingRendering'),
        ],
        [t],
    );
    const [stageIndex, setStageIndex] = useState(0);

    useEffect(() => {
        if (stageIndex >= stages.length - 1) {
            return;
        }

        const delay =
            stageIndex === 0
                ? LOADING_STAGE_DELAYS_MS.first
                : LOADING_STAGE_DELAYS_MS.subsequent;
        const timer = window.setTimeout(() => {
            setStageIndex((i) => Math.min(stages.length - 1, i + 1));
        }, delay);

        return () => window.clearTimeout(timer);
    }, [stageIndex, stages.length]);

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2.5 text-sm text-muted-foreground">
                <span className="rdw-spinner" aria-hidden="true" />
                <span key={stageIndex} className="rdw-fade-in">
                    {stages[stageIndex]}
                </span>
            </div>
            <div className="flex items-center gap-1.5 pt-0.5">
                {stages.map((_, i) => (
                    <span
                        key={i}
                        className={cn(
                            'h-1 flex-1 rounded-full transition-colors duration-300',
                            i <= stageIndex
                                ? 'bg-[var(--rdw-orange)]'
                                : 'bg-border',
                        )}
                    />
                ))}
            </div>
            <div className="space-y-2.5 pt-2">
                <div
                    className="rdw-skel h-3.5 rounded-sm"
                    style={{ width: '92%' }}
                />
                <div
                    className="rdw-skel h-3.5 rounded-sm"
                    style={{ width: '76%' }}
                />
                <div
                    className="rdw-skel h-3.5 rounded-sm"
                    style={{ width: '88%' }}
                />
                <div
                    className="rdw-skel h-3.5 rounded-sm"
                    style={{ width: '64%' }}
                />
            </div>
        </div>
    );
}
