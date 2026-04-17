<script setup>
import { computed } from 'vue';
import { __ } from '@/composables/useSettings';

const props = defineProps({
    data: { required: true },
    nodeKey: { type: [String, Number], default: null },
    isLast: { type: Boolean, default: true }
});

const isObject = computed(() => props.data !== null && typeof props.data === 'object');
const isArray = computed(() => Array.isArray(props.data));
const getKeys = computed(() => isObject.value ? Object.keys(props.data) : []);
</script>

<template>
    <div class="font-mono text-sm leading-relaxed text-gray-800 dark:text-gray-200">
        <div v-if="isObject">
            <details open class="group">
                <summary class="cursor-pointer select-none outline-none inline-flex items-start hover:opacity-80">
                    <svg class="w-3 h-3 text-gray-500 mr-1.5 mt-1 transition-transform group-open:rotate-90 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    <div>
                        <span v-if="nodeKey !== null" class="text-blue-600 dark:text-blue-400 mr-1">"{{ nodeKey }}":</span>
                        <span class="text-gray-500 dark:text-gray-400 group-open:hidden">
                            {{ isArray ? '[...]' : '{...}' }}
                            <span class="text-gray-400 dark:text-gray-500 text-xs italic ml-1">{{ getKeys.length }} {{ __('item') }}</span>
                            <span v-if="!isLast" class="text-gray-500 dark:text-gray-400">,</span>
                        </span>
                        <span class="text-gray-500 dark:text-gray-400 hidden group-open:inline">
                            {{ isArray ? '[' : '{' }}
                        </span>
                    </div>
                </summary>
                <div class="pl-4 border-l border-gray-300 dark:border-gray-600 ml-1.5 mt-1">
                    <JsonViewer v-for="(key, index) in getKeys" :key="key" :nodeKey="isArray ? null : key" :data="data[key]" :is-last="index === getKeys.length - 1" />
                </div>
                <div class="text-gray-500 dark:text-gray-400 ml-[18px]">
                    {{ isArray ? ']' : '}' }}<span v-if="!isLast">,</span>
                </div>
            </details>
        </div>

        <div v-else class="py-0.5 flex items-start pl-4">
            <div class="shrink-0 w-3 mr-1.5"></div>
            <div>
                <span v-if="nodeKey !== null" class="text-blue-600 dark:text-blue-400 mr-1">"{{ nodeKey }}":</span>
                <span v-if="typeof data === 'string'" class="text-green-600 dark:text-green-400 break-all">"{{ data }}"</span>
                <span v-else-if="typeof data === 'number'" class="text-orange-600 dark:text-orange-400">{{ data }}</span>
                <span v-else-if="typeof data === 'boolean'" class="text-pink-600 dark:text-pink-400">{{ data }}</span>
                <span v-else-if="data === null" class="text-gray-400 dark:text-gray-500 italic">null</span>
                <span v-else class="text-green-600 dark:text-green-400">{{ data }}</span>
                <span v-if="!isLast" class="text-gray-500 dark:text-gray-400">,</span>
            </div>
        </div>
    </div>
</template>
