import type { Ref } from 'vue';
import { ref } from 'vue';
import { i18n } from '@/lib/i18n';
import type { Locale } from '@/types';

export type UseLocaleReturn = {
    locale: Ref<Locale>;
    updateLocale: (value: Locale) => void;
};

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredLocale = (): Locale | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = localStorage.getItem('locale');

    return stored === 'id' || stored === 'en' ? stored : null;
};

const locale = ref<Locale>('id');

export function initializeLocale(): void {
    const initial = getStoredLocale() ?? 'id';

    locale.value = initial;
    i18n.global.locale.value = initial;
}

export function useLocale(): UseLocaleReturn {
    function updateLocale(value: Locale): void {
        locale.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('locale', value);

        // Store in cookie so the backend can read it too...
        setCookie('locale', value);

        i18n.global.locale.value = value;
    }

    return {
        locale,
        updateLocale,
    };
}
