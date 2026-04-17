<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { __ } from '@/composables/useSettings';
import ThemeLangSelector from '@/Components/ThemeLangSelector.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
});
</script>

<template>
    <Head :title="__('welcome_page_title')" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white flex flex-col justify-center items-center relative overflow-hidden selection:bg-red-500 selection:text-white transition-colors duration-300">

        <div class="absolute top-6 right-6 z-10">
            <ThemeLangSelector />
        </div>

        <div class="relative w-full max-w-2xl px-6 lg:max-w-4xl flex flex-col items-center text-center z-10">
            <div class="mb-8 flex justify-center">
                <div class="relative rounded-[2rem] border border-red-100 bg-white/90 p-6 shadow-2xl shadow-red-200/40 backdrop-blur dark:border-red-900/60 dark:bg-gray-800/90 dark:shadow-black/30">
                    <ApplicationLogo class="h-28 w-28 text-red-600 dark:text-red-500 drop-shadow-lg" />
                    <div class="absolute -bottom-2 -right-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-bold px-2 py-1 rounded-md shadow-xl">v2.0</div>
                </div>
            </div>

            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">
                phpRedisAdmin
            </h1>

            <p class="text-lg md:text-xl text-gray-600 dark:text-gray-400 mb-10 max-w-2xl">
                {{ __('welcome_title') }}<br>
                <span class="text-base md:text-lg opacity-80 mt-2 block">{{ __('welcome_desc') }}</span>
            </p>

            <div v-if="canLogin" class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="rounded-md px-8 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold shadow-lg transition-all text-center ring-1 ring-transparent focus:outline-none focus-visible:ring-[#FF2D20]"
                >
                    {{ __('go_dashboard') }}
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="rounded-md px-8 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold shadow-lg transition-all text-center ring-1 ring-transparent focus:outline-none focus-visible:ring-[#FF2D20]"
                    >
                        {{ __('login') }}
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-md px-8 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-semibold shadow-sm transition-all text-center focus:outline-none focus-visible:ring-[#FF2D20]"
                    >
                        {{ __('register') }}
                    </Link>
                </template>
            </div>
        </div>

        <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none opacity-30 dark:opacity-20">
            <div class="absolute -top-[20%] -left-[10%] w-[50%] h-[50%] rounded-full bg-gradient-to-br from-red-200 to-transparent blur-3xl"></div>
            <div class="absolute top-[60%] -right-[10%] w-[40%] h-[60%] rounded-full bg-gradient-to-tl from-red-300 to-transparent blur-3xl"></div>
        </div>
    </div>
</template>
