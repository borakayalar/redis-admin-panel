<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import ThemeLangSelector from '@/Components/ThemeLangSelector.vue';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900 transition-colors duration-300">

        <div class="absolute top-6 right-6 z-10">
            <ThemeLangSelector />
        </div>

        <div>
            <Link href="/">
                <ApplicationLogo class="w-24 h-24 fill-current text-red-600 dark:text-red-500 drop-shadow-md" />
            </Link>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg transition-colors duration-300">
            <slot />
        </div>
    </div>
</template>
