import {
    Ban,
    DatabaseZap,
    HelpCircle,
    Plus,
    ShieldAlert,
    Telescope,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { useTranslation } from '@/hooks/use-translation';

import type { Refusal, RefusalReason } from '../types';

// Each refusal reason gets its own icon so the user can tell "off-topic" from
// "the data doesn't exist" from "too broad" at a glance. The refusal *text* comes
// from the model via `presentation.explanation` and is rendered in the card
// header, so this view only adds the marker and the clickable alternatives.
const REASON_ICON: Record<RefusalReason, LucideIcon> = {
    out_of_scope: Ban,
    no_such_data: DatabaseZap,
    too_broad: Telescope,
    ambiguous: HelpCircle,
};

export function UnsupportedView({
    refusal,
    onPickSuggestion,
}: {
    refusal?: Refusal | null;
    onPickSuggestion?: (question: string) => void;
}) {
    const { t } = useTranslation();
    const Icon = (refusal && REASON_ICON[refusal.reason]) ?? ShieldAlert;
    const suggestions = refusal?.suggestions ?? [];

    return (
        <div className="flex flex-col items-center gap-4 py-2">
            <Icon className="h-8 w-8 text-neutral-400 dark:text-neutral-500" />

            {suggestions.length > 0 && onPickSuggestion && (
                <div className="flex w-full max-w-[560px] flex-col items-center gap-2">
                    <span className="text-xs text-muted-foreground">
                        {t('pages.query.refusal.alternatives')}
                    </span>
                    <div className="flex flex-wrap justify-center gap-1.5">
                        {suggestions.map((question) => (
                            <button
                                key={question}
                                type="button"
                                onClick={() => onPickSuggestion(question)}
                                className="group inline-flex items-center gap-1.5 rounded-full border bg-card/60 px-3 py-1.5 text-[12.5px] text-muted-foreground transition hover:border-[var(--rdw-orange)] hover:bg-[var(--rdw-orange-faint)] hover:text-foreground"
                            >
                                <Plus className="h-3 w-3 text-[var(--rdw-orange)]" />
                                {question}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
