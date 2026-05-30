import { ArrowRight, CornerDownLeft, X } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { usePrefersReducedMotion } from '@/hooks/use-reduced-motion';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

import { extractPlateFromText } from '../plate';
import { useTypewriterPlaceholder } from '../use-typewriter-placeholder';
import { PlateChip } from './plate-chip';

// Fallback prompt bounds for when the page doesn't pass server-config values (e.g. component
// tests). In the app these are overridden by `promptMinLength` / `promptMaxLength` page props,
// which carry the authoritative values from config/rdwai.php so the two can't drift.
export const MIN_PROMPT_LENGTH = 3;
export const PROMPT_MAX_LENGTH = 2000;

// ─── Composer ──────────────────────────────────────────────────
export function ComposerCard({
    taRef,
    value,
    setValue,
    onSubmit,
    onClear,
    busy,
    compact,
    placeholderSuggestions,
    minLength = MIN_PROMPT_LENGTH,
    maxLength = PROMPT_MAX_LENGTH,
}: {
    taRef: React.RefObject<HTMLTextAreaElement | null>;
    value: string;
    setValue: (v: string) => void;
    onSubmit: () => void;
    onClear?: () => void;
    busy: boolean;
    compact: boolean;
    placeholderSuggestions: readonly string[];
    minLength?: number;
    maxLength?: number;
}) {
    const { t } = useTranslation();
    const staticPlaceholder = t('pages.query.placeholder');
    const [focused, setFocused] = useState(false);
    const reducedMotion = usePrefersReducedMotion();
    // Hold the static placeholder still for users who opt out of motion.
    const animate = !compact && !focused && value === '' && !reducedMotion;
    const typed = useTypewriterPlaceholder(
        placeholderSuggestions,
        animate,
        staticPlaceholder,
    );

    const handleKey = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            onSubmit();
        }
    };

    // Scan the whole question for a plate rather than requiring the field to be
    // a bare plate — users type it inside a sentence ("… op de weg? 1-ZTZ-08?").
    const plate = extractPlateFromText(value);

    return (
        <div
            className={cn(
                'rdw-composer-wrap relative w-full max-w-[780px]',
                compact ? 'mt-3' : 'mt-7',
            )}
        >
            <div className="rdw-composer-glow" aria-hidden="true" />
            <div className="rdw-composer-shell">
                <div className="rdw-composer-inner relative flex flex-col px-5 pt-4 pb-3">
                    <Textarea
                        ref={taRef}
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        onFocus={() => setFocused(true)}
                        onBlur={() => setFocused(false)}
                        onKeyDown={handleKey}
                        placeholder={animate ? typed : staticPlaceholder}
                        rows={1}
                        // Authoritative cap comes from config/rdwai.php via page props. The browser
                        // stops accepting input at the cap so the user can't paste a wall of text
                        // and watch the request 422 a second later.
                        maxLength={maxLength}
                        aria-label={staticPlaceholder}
                        className={cn(
                            'min-h-0 resize-none border-0 bg-transparent p-0 leading-relaxed shadow-none focus-visible:ring-0 focus-visible:ring-offset-0 dark:bg-transparent',
                            compact
                                ? 'text-[15.5px]'
                                : 'text-[17px] md:text-[18px]',
                        )}
                    />
                    <div className="mt-3 flex items-center justify-between gap-3 border-t pt-3">
                        <div className="inline-flex items-center gap-2 text-xs whitespace-nowrap text-muted-foreground">
                            {plate !== null ? (
                                <PlateChip
                                    plate={plate}
                                    className="rdw-fade-in"
                                />
                            ) : (
                                <>
                                    <span className="inline-flex items-center gap-1">
                                        <kbd className="rdw-kbd">⌘</kbd>
                                        <kbd className="rdw-kbd">
                                            <CornerDownLeft className="h-3 w-3" />
                                        </kbd>
                                    </span>
                                    <span className="hidden sm:inline">
                                        {t('pages.query.submitAction')}
                                    </span>
                                </>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            {onClear && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={onClear}
                                    disabled={busy}
                                    className="text-muted-foreground"
                                >
                                    <X className="h-3.5 w-3.5" />
                                    <span className="hidden sm:inline">
                                        {t('pages.query.clearAll')}
                                    </span>
                                </Button>
                            )}
                            <Button
                                type="button"
                                onClick={onSubmit}
                                disabled={
                                    busy || value.trim().length < minLength
                                }
                                className={cn(
                                    'gap-2 rounded-[12px] bg-[var(--rdw-orange)] text-white hover:bg-[#ff6c37]',
                                    'shadow-[0_1px_0_rgba(255,255,255,0.18)_inset,0_-1px_0_rgba(0,0,0,0.12)_inset,0_8px_20px_-6px_var(--rdw-orange-glow)]',
                                    'focus-visible:ring-[var(--rdw-orange)]/40',
                                )}
                            >
                                {busy ? (
                                    <>
                                        <span
                                            className="rdw-spinner"
                                            style={{
                                                width: 14,
                                                height: 14,
                                                borderWidth: 2,
                                            }}
                                            aria-hidden="true"
                                        />
                                        {t('pages.query.thinking')}
                                    </>
                                ) : (
                                    <>
                                        {t('pages.query.ask')}
                                        <ArrowRight className="h-3.5 w-3.5" />
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
