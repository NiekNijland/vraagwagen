import { Sparkles } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

// ─── Eyebrow chip ──────────────────────────────────────────────
export function EyebrowChip({ compact }: { compact: boolean }) {
    const { t } = useTranslation();

    return (
        <div
            className={cn(
                'inline-flex items-center gap-2 rounded-[3px] border bg-card/60 px-3.5 py-1.5 font-mono text-[11.5px] font-medium text-muted-foreground backdrop-blur',
                compact ? 'mb-2.5' : 'mb-5',
            )}
        >
            <span className="rdw-pulse" aria-hidden="true" />
            <Sparkles
                className="h-3 w-3 text-[var(--rdw-orange)]"
                aria-hidden="true"
            />
            <span>{t('pages.query.poweredBy')}</span>
        </div>
    );
}
