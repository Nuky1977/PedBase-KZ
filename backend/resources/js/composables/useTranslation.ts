import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import en from '../../../lang/en.json';
import kk from '../../../lang/kk.json';
import ru from '../../../lang/ru.json';

export type InterfaceLocale = 'kk' | 'ru' | 'en';

const messages: Record<InterfaceLocale, Record<string, string>> = {
    kk,
    ru,
    en,
};

export function useTranslation() {
    const page = usePage();
    const locale = computed(() => page.props.locale ?? 'kk');

    function t(
        key: string,
        replacements: Record<string, string | number> = {},
    ): string {
        const message =
            messages[locale.value]?.[key] ?? messages.kk[key] ?? key;

        return message.replace(/:([a-z_]+)/g, (placeholder, name: string) =>
            Object.hasOwn(replacements, name)
                ? String(replacements[name])
                : placeholder,
        );
    }

    return { locale, t };
}
