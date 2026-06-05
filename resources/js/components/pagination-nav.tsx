import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { Paginated } from '@/types';

export function PaginationNav({
    paginator,
}: {
    paginator: Paginated<unknown>;
}) {
    const { t } = useTranslation();

    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
                {t('components.paginationNav.summary', {
                    current: paginator.current_page,
                    last: paginator.last_page,
                    total: paginator.total,
                })}
            </p>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    asChild={paginator.prev_page_url !== null}
                    disabled={paginator.prev_page_url === null}
                >
                    {paginator.prev_page_url !== null ? (
                        <Link href={paginator.prev_page_url} preserveScroll>
                            {t('components.paginationNav.previous')}
                        </Link>
                    ) : (
                        <span>{t('components.paginationNav.previous')}</span>
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    asChild={paginator.next_page_url !== null}
                    disabled={paginator.next_page_url === null}
                >
                    {paginator.next_page_url !== null ? (
                        <Link href={paginator.next_page_url} preserveScroll>
                            {t('components.paginationNav.next')}
                        </Link>
                    ) : (
                        <span>{t('components.paginationNav.next')}</span>
                    )}
                </Button>
            </div>
        </div>
    );
}
