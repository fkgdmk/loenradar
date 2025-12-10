import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    return toUrl(urlToCheck) === currentUrl;
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function formatTimeAgo(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    
    const rtf = new Intl.RelativeTimeFormat('da-DK', { numeric: 'auto' });
    
    if (Math.abs(diffInSeconds) < 60) {
        return 'lige nu';
    }
    
    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (Math.abs(diffInMinutes) < 60) {
        return rtf.format(-diffInMinutes, 'minute');
    }
    
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (Math.abs(diffInHours) < 24) {
        return rtf.format(-diffInHours, 'hour');
    }
    
    const diffInDays = Math.floor(diffInHours / 24);
    if (Math.abs(diffInDays) < 7) {
        return rtf.format(-diffInDays, 'day');
    }
    
    const diffInWeeks = Math.floor(diffInDays / 7);
    if (Math.abs(diffInWeeks) < 4) {
        return rtf.format(-diffInWeeks, 'week');
    }
    
    const diffInMonths = Math.floor(diffInDays / 30);
    if (Math.abs(diffInMonths) < 12) {
        return rtf.format(-diffInMonths, 'month');
    }
    
    const diffInYears = Math.floor(diffInDays / 365);
    return rtf.format(-diffInYears, 'year');
}
