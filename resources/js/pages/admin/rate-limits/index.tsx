import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { RotateCcw, Search } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import {
    index as rateLimitsIndex,
    reset as rateLimitsReset,
    update as rateLimitsUpdate,
} from '@/routes/admin/rate-limits';
import { index as statsIndex } from '@/routes/admin/stats';
import type { IpUsage, RateLimits, RateLimitUsage } from '@/types';

const LIMIT_KEYS = [
    'per_minute',
    'per_day_ip',
    'per_day_global',
    'feedback_per_minute',
] as const;

export default function AdminRateLimitsIndex({
    limits,
    globalUsage,
    ip,
    ipUsage,
}: {
    limits: RateLimits;
    globalUsage: RateLimitUsage;
    ip: string | null;
    ipUsage: IpUsage | null;
}) {
    const { t } = useTranslation();
    const [ipInput, setIpInput] = useState(ip ?? '');

    setLayoutProps({
        breadcrumbs: [
            { title: t('pages.admin.breadcrumb'), href: statsIndex() },
            {
                title: t('pages.admin.rateLimits.breadcrumb'),
                href: rateLimitsIndex(),
            },
        ],
    });

    const { data, setData, patch, processing, errors } = useForm({
        per_minute: limits.per_minute.value,
        per_day_ip: limits.per_day_ip.value,
        per_day_global: limits.per_day_global.value,
        feedback_per_minute: limits.feedback_per_minute.value,
    });

    const lookupIp = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            rateLimitsIndex.url(
                ipInput !== '' ? { query: { ip: ipInput } } : undefined,
            ),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={t('pages.admin.rateLimits.title')} />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('pages.admin.rateLimits.title')}
                </h1>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>
                            {t('pages.admin.rateLimits.globalUsage')}
                        </CardTitle>
                        <ResetDialog
                            label={t('pages.admin.rateLimits.resetGlobal')}
                            description={t(
                                'pages.admin.rateLimits.resetGlobalDescription',
                            )}
                            onConfirm={() =>
                                router.post(
                                    rateLimitsReset.url(),
                                    { scope: 'global' },
                                    { preserveScroll: true },
                                )
                            }
                        />
                    </CardHeader>
                    <CardContent>
                        <UsageBar
                            usage={globalUsage}
                            label={t('pages.admin.rateLimits.perDayGlobal')}
                            t={t}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('pages.admin.rateLimits.limits')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="flex flex-col gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                patch(rateLimitsUpdate.url(), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                {LIMIT_KEYS.map((key) => (
                                    <div
                                        key={key}
                                        className="flex flex-col gap-2"
                                    >
                                        <Label htmlFor={`limit-${key}`}>
                                            {t(
                                                `pages.admin.rateLimits.keys.${key}`,
                                            )}
                                        </Label>
                                        <Input
                                            id={`limit-${key}`}
                                            type="number"
                                            min={1}
                                            value={data[key]}
                                            onChange={(event) =>
                                                setData(
                                                    key,
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {limits[key].overridden ? (
                                                <Badge variant="secondary">
                                                    {t(
                                                        'pages.admin.rateLimits.overridden',
                                                        {
                                                            default:
                                                                limits[key]
                                                                    .default,
                                                        },
                                                    )}
                                                </Badge>
                                            ) : (
                                                t(
                                                    'pages.admin.rateLimits.fromEnv',
                                                )
                                            )}
                                        </p>
                                        {errors[key] && (
                                            <p className="text-xs text-destructive">
                                                {errors[key]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <div>
                                <Button type="submit" disabled={processing}>
                                    {t('pages.admin.rateLimits.save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('pages.admin.rateLimits.ipLookup')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <form className="flex gap-2" onSubmit={lookupIp}>
                            <Input
                                value={ipInput}
                                onChange={(event) =>
                                    setIpInput(event.target.value)
                                }
                                placeholder="203.0.113.7"
                                className="max-w-60 font-mono"
                                aria-label={t(
                                    'pages.admin.rateLimits.ipLookup',
                                )}
                            />
                            <Button type="submit" variant="outline">
                                <Search />
                                {t('pages.admin.rateLimits.lookup')}
                            </Button>
                        </form>

                        {ip !== null && ipUsage !== null && (
                            <div className="flex flex-col gap-4">
                                <UsageBar
                                    usage={ipUsage.perMinute}
                                    label={t(
                                        'pages.admin.rateLimits.keys.per_minute',
                                    )}
                                    t={t}
                                />
                                <UsageBar
                                    usage={ipUsage.perDay}
                                    label={t(
                                        'pages.admin.rateLimits.keys.per_day_ip',
                                    )}
                                    t={t}
                                />
                                <UsageBar
                                    usage={ipUsage.feedbackPerMinute}
                                    label={t(
                                        'pages.admin.rateLimits.keys.feedback_per_minute',
                                    )}
                                    t={t}
                                />
                                <div>
                                    <ResetDialog
                                        label={t(
                                            'pages.admin.rateLimits.resetIp',
                                            { ip },
                                        )}
                                        description={t(
                                            'pages.admin.rateLimits.resetIpDescription',
                                            { ip },
                                        )}
                                        onConfirm={() =>
                                            router.post(
                                                rateLimitsReset.url(),
                                                { scope: 'ip', ip },
                                                { preserveScroll: true },
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function UsageBar({
    usage,
    label,
    t,
}: {
    usage: RateLimitUsage;
    label: string;
    t: (key: string, params?: Record<string, string | number>) => string;
}) {
    const share =
        usage.limit > 0
            ? Math.min(100, Math.round((usage.used / usage.limit) * 100))
            : 0;

    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex items-baseline justify-between gap-2 text-sm">
                <span>{label}</span>
                <span className="text-muted-foreground tabular-nums">
                    {t('pages.admin.rateLimits.usedOf', {
                        used: usage.used,
                        limit: usage.limit,
                    })}
                    {usage.used > 0 &&
                        ` · ${t('pages.admin.rateLimits.resetsIn', {
                            minutes: Math.ceil(usage.resetsInSeconds / 60),
                        })}`}
                </span>
            </div>
            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuenow={usage.used}
                aria-valuemin={0}
                aria-valuemax={usage.limit}
                aria-label={label}
            >
                <div
                    className={`h-full rounded-full ${
                        share >= 90
                            ? 'bg-destructive'
                            : share >= 60
                              ? 'bg-amber-500'
                              : 'bg-primary'
                    }`}
                    style={{ width: `${share}%` }}
                />
            </div>
        </div>
    );
}

function ResetDialog({
    label,
    description,
    onConfirm,
}: {
    label: string;
    description: string;
    onConfirm: () => void;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <RotateCcw />
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{label}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="ghost" onClick={() => setOpen(false)}>
                        {t('pages.admin.rateLimits.cancel')}
                    </Button>
                    <Button
                        onClick={() => {
                            onConfirm();
                            setOpen(false);
                        }}
                    >
                        {t('pages.admin.rateLimits.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
