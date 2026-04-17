import { reactive, watch } from 'vue';
import { translations } from '@/locales/translations';

const defaultSettings = {
    locale: 'en',
    theme: 'system',
    layoutWidth: 'normal',
    availableLocales: ['tr', 'en'],
    availableThemes: ['light', 'dark', 'system'],
    availableLayoutWidths: ['normal', 'wide'],
};

export const store = reactive({
    ...defaultSettings,
});

let systemThemeMediaQuery = null;
let systemThemeListenerBound = false;

const isBrowser = typeof window !== 'undefined';

const getCookie = (name) => {
    if (!isBrowser) {
        return null;
    }

    const match = document.cookie
        .split('; ')
        .find((item) => item.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : null;
};

const setCookie = (name, value) => {
    if (!isBrowser) {
        return;
    }

    document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=31536000; samesite=lax`;
};

const getStorageItem = (key) => {
    if (!isBrowser) {
        return null;
    }

    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
};

const setStorageItem = (key, value) => {
    if (!isBrowser) {
        return;
    }

    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Ignore storage write failures.
    }
};

const resolvePreference = (value, supported, fallback) => (
    supported.includes(value) ? value : fallback
);

const handleSystemThemeChange = () => {
    if (store.theme === 'system') {
        applyTheme();
    }
};

const bindSystemThemeListener = () => {
    if (!isBrowser || systemThemeListenerBound || !window.matchMedia) {
        return;
    }

    systemThemeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    if (typeof systemThemeMediaQuery.addEventListener === 'function') {
        systemThemeMediaQuery.addEventListener('change', handleSystemThemeChange);
    } else if (typeof systemThemeMediaQuery.addListener === 'function') {
        systemThemeMediaQuery.addListener(handleSystemThemeChange);
    }

    systemThemeListenerBound = true;
};

export const applyTheme = () => {
    if (!isBrowser) {
        return;
    }

    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
    const isDark = store.theme === 'dark' || (store.theme === 'system' && prefersDark);

    document.documentElement.classList.toggle('dark', isDark);
};

export const initializeSettings = (initialSettings = {}) => {
    const settings = {
        ...defaultSettings,
        ...initialSettings,
    };

    store.availableLocales = Array.isArray(settings.availableLocales) && settings.availableLocales.length > 0
        ? settings.availableLocales
        : defaultSettings.availableLocales;
    store.availableThemes = Array.isArray(settings.availableThemes) && settings.availableThemes.length > 0
        ? settings.availableThemes
        : defaultSettings.availableThemes;
    store.availableLayoutWidths = Array.isArray(settings.availableLayoutWidths) && settings.availableLayoutWidths.length > 0
        ? settings.availableLayoutWidths
        : defaultSettings.availableLayoutWidths;

    const preferredLocale = getCookie('locale') ?? getStorageItem('locale') ?? settings.locale;
    const preferredTheme = getCookie('theme') ?? getStorageItem('theme') ?? settings.theme;
    const preferredLayoutWidth = getCookie('layout_width') ?? getStorageItem('layout_width') ?? settings.layoutWidth;

    store.locale = resolvePreference(preferredLocale, store.availableLocales, defaultSettings.locale);
    store.theme = resolvePreference(preferredTheme, store.availableThemes, defaultSettings.theme);
    store.layoutWidth = resolvePreference(preferredLayoutWidth, store.availableLayoutWidths, defaultSettings.layoutWidth);

    document.documentElement.lang = store.locale;
    setCookie('locale', store.locale);
    setCookie('theme', store.theme);
    setCookie('layout_width', store.layoutWidth);
    setStorageItem('locale', store.locale);
    setStorageItem('theme', store.theme);
    setStorageItem('layout_width', store.layoutWidth);

    bindSystemThemeListener();
    applyTheme();
};

watch(() => store.theme, (val) => {
    if (!store.availableThemes.includes(val)) {
        return;
    }

    setCookie('theme', val);
    setStorageItem('theme', val);
    applyTheme();
});

watch(() => store.locale, (val) => {
    if (!store.availableLocales.includes(val)) {
        return;
    }

    setCookie('locale', val);
    setStorageItem('locale', val);

    if (isBrowser) {
        document.documentElement.lang = val;
    }
});

watch(() => store.layoutWidth, (val) => {
    if (!store.availableLayoutWidths.includes(val)) {
        return;
    }

    setCookie('layout_width', val);
    setStorageItem('layout_width', val);
});

export const __ = (key, ...args) => {
    let text = translations[store.locale]?.[key] || key;
    args.forEach((arg, i) => {
        text = text.replace(`{${i}}`, arg);
    });
    return text;
};
