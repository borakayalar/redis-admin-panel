<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import { __, store } from '@/composables/useSettings';
import { computed } from 'vue';

const localeOptions = [
    { value: 'tr', label: 'Turkish' },
    { value: 'en', label: 'English' },
];

const themeOptions = [
    { value: 'light', labelKey: 'theme_light', icon: '☀️' },
    { value: 'dark', labelKey: 'theme_dark', icon: '🌙' },
    { value: 'system', labelKey: 'theme_system', icon: '💻' },
];

const layoutOptions = [
    { value: 'normal', labelKey: 'layout_normal' },
    { value: 'wide', labelKey: 'layout_wide' },
];

const themeIconMap = {
    light: '☀️',
    dark: '🌙',
    system: '💻',
};

const currentThemeLabel = computed(() => __(themeOptions.find((option) => option.value === store.theme)?.labelKey ?? 'theme_system'));
const currentLayoutLabel = computed(() => __(layoutOptions.find((option) => option.value === store.layoutWidth)?.labelKey ?? 'layout_normal'));
</script>

<template>
    <Dropdown align="right" width="72" content-classes="py-2 bg-white dark:bg-gray-800">
        <template #trigger>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm transition-colors hover:bg-gray-50 dark:hover:bg-gray-700"
            >
                <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317a1 1 0 011.35-.936l1.514.607a1 1 0 00.84 0l1.515-.607a1 1 0 011.35.936v1.56a1 1 0 00.293.707l1.103 1.103a1 1 0 010 1.414l-1.103 1.103a1 1 0 00-.293.707v1.56a1 1 0 01-1.35.936l-1.515-.607a1 1 0 00-.84 0l-1.514.607a1 1 0 01-1.35-.936v-1.56a1 1 0 00-.293-.707L8.722 8.101a1 1 0 010-1.414l1.103-1.103a1 1 0 00.293-.707v-1.56z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9.75a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h1.5M4 12h1.5M4 17h1.5" />
                </svg>
                <span class="hidden md:inline">{{ __('settings_label') }}</span>
                <span class="rounded-md bg-gray-100 dark:bg-gray-900 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide">{{ store.locale }}</span>
                <span class="hidden lg:inline text-xs text-gray-500 dark:text-gray-400">{{ themeIconMap[store.theme] }} {{ currentThemeLabel }}</span>
                <span class="hidden xl:inline text-xs text-gray-500 dark:text-gray-400">{{ currentLayoutLabel }}</span>
                <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 011.08 1.04l-4.25 4.512a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
        </template>

        <template #content>
            <div class="space-y-3 px-3 py-1 text-sm text-gray-700 dark:text-gray-200">
                <section>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">{{ __('language_label') }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="option in localeOptions"
                            :key="option.value"
                            type="button"
                            @click="store.locale = option.value"
                            class="rounded-md border px-3 py-2 text-left transition-colors"
                            :class="store.locale === option.value
                                ? 'border-red-300 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300'
                                : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700'"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </section>

                <section>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">{{ __('theme_label') }}</p>
                    <div class="space-y-2">
                        <button
                            v-for="option in themeOptions"
                            :key="option.value"
                            type="button"
                            @click="store.theme = option.value"
                            class="flex w-full items-center justify-between rounded-md border px-3 py-2 text-left transition-colors"
                            :class="store.theme === option.value
                                ? 'border-red-300 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300'
                                : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700'"
                        >
                            <span>{{ option.icon }} {{ __(option.labelKey) }}</span>
                            <span v-if="store.theme === option.value" class="text-xs font-semibold uppercase">{{ __('active_label') }}</span>
                        </button>
                    </div>
                </section>

                <section>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">{{ __('layout_width_label') }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="option in layoutOptions"
                            :key="option.value"
                            type="button"
                            @click="store.layoutWidth = option.value"
                            class="rounded-md border px-3 py-2 text-left transition-colors"
                            :class="store.layoutWidth === option.value
                                ? 'border-red-300 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300'
                                : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700'"
                        >
                            {{ __(option.labelKey) }}
                        </button>
                    </div>
                </section>
            </div>
        </template>
    </Dropdown>
</template>
