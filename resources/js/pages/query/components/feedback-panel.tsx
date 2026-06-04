import { ThumbsDown, ThumbsUp } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';

import { postJson } from '../api';
import type { Rating } from '../types';

export function FeedbackPanel({
    slug,
    rating,
    comment,
    onChange,
}: {
    slug: string;
    rating: Rating | null;
    comment: string | null;
    onChange: (next: { rating: Rating | null; comment: string | null }) => void;
}) {
    const { t } = useTranslation();
    const [commentDraft, setCommentDraft] = useState(comment ?? '');
    const [submitting, setSubmitting] = useState(false);

    const submitFeedback = async (
        nextRating: Rating,
        nextComment: string | null,
    ): Promise<void> => {
        setSubmitting(true);

        try {
            const response = await postJson(`/api/query/${slug}/feedback`, {
                rating: nextRating,
                comment: nextComment,
            });

            if (!response.ok) {
                throw new Error('feedback failed');
            }

            onChange({ rating: nextRating, comment: nextComment });
            toast.success(t('pages.query.feedbackThanks'));
        } catch {
            toast.error(t('pages.query.feedbackFailed'));
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="flex flex-col gap-2 rounded-[4px] border bg-card/40 p-3 text-xs">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-muted-foreground">
                    {t('pages.query.feedbackPrompt')}
                </span>
                <div className="flex items-center gap-1.5">
                    <Button
                        type="button"
                        variant={rating === 'up' ? 'default' : 'outline'}
                        size="icon"
                        className="h-8 w-8"
                        disabled={submitting}
                        onClick={() =>
                            void submitFeedback('up', commentDraft || null)
                        }
                        aria-label={t('pages.query.feedbackHelpful')}
                    >
                        <ThumbsUp className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant={rating === 'down' ? 'default' : 'outline'}
                        size="icon"
                        className="h-8 w-8"
                        disabled={submitting}
                        onClick={() =>
                            void submitFeedback('down', commentDraft || null)
                        }
                        aria-label={t('pages.query.feedbackNotHelpful')}
                    >
                        <ThumbsDown className="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>

            {rating !== null && (
                <div className="flex flex-col gap-2">
                    <Textarea
                        value={commentDraft}
                        onChange={(e) => setCommentDraft(e.target.value)}
                        placeholder={t(
                            rating === 'up'
                                ? 'pages.query.feedbackCommentPlaceholderPositive'
                                : 'pages.query.feedbackCommentPlaceholderNegative',
                        )}
                        rows={2}
                        className="resize-none text-xs"
                    />
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={
                                submitting || commentDraft === (comment ?? '')
                            }
                            onClick={() =>
                                void submitFeedback(
                                    rating,
                                    commentDraft || null,
                                )
                            }
                        >
                            {t('pages.query.feedbackSubmit')}
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
