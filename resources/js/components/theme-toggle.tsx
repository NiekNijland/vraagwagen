import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/hooks/use-translation';

// The toggle cycles light → dark → system → light. The icon reflects the
// currently selected mode and the tooltip announces what the next click does.
const NEXT_APPEARANCE: Record<Appearance, Appearance> = {
    light: 'dark',
    dark: 'system',
    system: 'light',
};

const APPEARANCE_ICON: Record<Appearance, LucideIcon> = {
    light: Sun,
    dark: Moon,
    system: Monitor,
};

const NEXT_LABEL_KEY: Record<Appearance, string> = {
    light: 'components.themeToggle.toDark',
    dark: 'components.themeToggle.toSystem',
    system: 'components.themeToggle.toLight',
};

export function ThemeToggle() {
    const { appearance, updateAppearance } = useAppearance();
    const { t } = useTranslation();

    const Icon = APPEARANCE_ICON[appearance];
    const label = t(NEXT_LABEL_KEY[appearance]);

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-9 w-9 cursor-pointer"
                    onClick={() =>
                        updateAppearance(NEXT_APPEARANCE[appearance])
                    }
                >
                    <Icon className="size-5 opacity-80" />
                    <span className="sr-only">{label}</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>{label}</p>
            </TooltipContent>
        </Tooltip>
    );
}
