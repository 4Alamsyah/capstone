import { createI18n } from 'vue-i18n';
import en from '@/lang/en.json';
import id from '@/lang/id.json';

export const i18n = createI18n({
    legacy: false,
    locale: 'id',
    fallbackLocale: 'en',
    messages: { en, id },
});
