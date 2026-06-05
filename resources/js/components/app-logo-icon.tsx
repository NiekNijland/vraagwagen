import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type AppLogoIconProps = ImgHTMLAttributes<HTMLImageElement> & {
    /** 'dark' = witte lijnen (voor donkere achtergrond), 'light' = donkere lijnen, 'auto' = volgt theme */
    variant?: 'auto' | 'dark' | 'light';
};

export default function AppLogoIcon({
    variant = 'auto',
    className,
    alt = '',
    ...props
}: AppLogoIconProps) {
    if (variant !== 'auto') {
        return (
            <img
                src={`/images/brand/icon-${variant}.png`}
                alt={alt}
                className={className}
                {...props}
            />
        );
    }

    return (
        <>
            <img
                src="/images/brand/icon-light.png"
                alt={alt}
                className={cn('dark:hidden', className)}
                {...props}
            />
            <img
                src="/images/brand/icon-dark.png"
                alt={alt}
                className={cn('hidden dark:block', className)}
                {...props}
            />
        </>
    );
}
