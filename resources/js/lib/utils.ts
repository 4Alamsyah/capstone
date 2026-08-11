import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/**
 * Formats a decimal quantity string (e.g. Laravel `decimal:4` casts like "5.0000")
 * by stripping trailing zeros, so "5.0000" renders as "5" and "5.2500" as "5.25".
 */
export function formatQty(value: string | number | null | undefined) {
    if (value === null || value === undefined) {
        return '';
    }

    const num = parseFloat(String(value));

    if (Number.isNaN(num)) {
        return String(value);
    }

    return num.toFixed(4).replace(/\.?0+$/, '');
}
