<script setup>
import { ref } from 'vue';

defineProps({ nodes: { type: Object, required: true } });

const openNodes = ref({});
const toggle = (key) => openNodes.value[key] = !openNodes.value[key];
const isOpen = (key) => !!openNodes.value[key];
const selectKey = (keyName) => emit('keySelected', keyName);
const emit = defineEmits(['keySelected']);
</script>

<template>
    <ul class="pl-4 border-l border-gray-200 dark:border-gray-700 ml-2 mt-1 space-y-1">
        <li v-for="(value, key) in nodes" :key="key">
            <div v-if="value.__key__" @click="selectKey(value.__key__)" class="flex items-center space-x-2 py-1 px-2 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded cursor-pointer group transition-colors">
                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 group-hover:text-blue-600 dark:group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-blue-700 dark:group-hover:text-blue-400 break-all">{{ key }}</span>
            </div>
            <div v-else>
                <div @click="toggle(key)" class="flex items-center space-x-2 py-1 px-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded cursor-pointer transition-colors">
                    <svg v-if="isOpen(key)" class="w-4 h-4 text-yellow-500 dark:text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"></path></svg>
                    <svg v-else class="w-4 h-4 text-yellow-500 dark:text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ key }}</span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">({{ Object.keys(value).length }})</span>
                </div>
                <div v-show="isOpen(key)">
                    <TreeNode :nodes="value" @keySelected="(k) => emit('keySelected', k)" />
                </div>
            </div>
        </li>
    </ul>
</template>
