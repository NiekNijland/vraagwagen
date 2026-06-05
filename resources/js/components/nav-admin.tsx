import { Link } from '@inertiajs/react';
import {
    ChartLine,
    Gauge,
    MessagesSquare,
    ScrollText,
    Users,
} from 'lucide-react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/hooks/use-translation';
import { index as feedbackIndex } from '@/routes/admin/feedback';
import { index as queriesIndex } from '@/routes/admin/queries';
import { index as rateLimitsIndex } from '@/routes/admin/rate-limits';
import { index as statsIndex } from '@/routes/admin/stats';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavItem } from '@/types';

export function NavAdmin() {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslation();

    const items: NavItem[] = [
        {
            title: t('components.navAdmin.stats'),
            href: statsIndex(),
            icon: ChartLine,
        },
        {
            title: t('components.navAdmin.queries'),
            href: queriesIndex(),
            icon: ScrollText,
        },
        {
            title: t('components.navAdmin.feedback'),
            href: feedbackIndex(),
            icon: MessagesSquare,
        },
        {
            title: t('components.navAdmin.users'),
            href: usersIndex(),
            icon: Users,
        },
        {
            title: t('components.navAdmin.rateLimits'),
            href: rateLimitsIndex(),
            icon: Gauge,
        },
    ];

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>
                {t('components.navAdmin.label')}
            </SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentOrParentUrl(item.href)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
