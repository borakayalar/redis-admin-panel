<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import TreeNode from '@/Components/TreeNode.vue';
import JsonViewer from '@/Components/JsonViewer.vue';
import { ref, onUnmounted, computed } from 'vue';
import { __, store } from '@/composables/useSettings';
import { formatValue, isComplex } from '@/utils/redisParser';

const props = defineProps({
    tree: { type: Object, default: () => ({}) },
    serverInfo: { type: Object, default: () => ({}) },
    currentDb: { type: Number, default: 0 },
    databases: { type: Number, default: 16 },
    keyspace: { type: Object, default: () => ({}) }
});

const selectedDb = ref(props.currentDb);
const selectedKeyData = ref(null);
const isLoadingKey = ref(false);
const keyError = ref(null);

const currentTtl = ref(null);
let ttlInterval = null;

const showTtlModal = ref(false);
const newTtlInput = ref('');
const showRenameModal = ref(false);
const newKeyNameInput = ref('');
const searchQuery = ref('');

const showBulkDeleteModal = ref(false);
const bulkSearchQuery = ref('');
const bulkSelectedKeys = ref([]);

const confirmDialog = ref({
    show: false, title: '', message: '', action: null, confirmText: __('confirm'), confirmColor: 'bg-red-600 hover:bg-red-700 focus:ring-red-500'
});

const customConfirm = (title, message, action, confirmText = __('delete_key'), confirmColor = 'bg-red-600 hover:bg-red-700 focus:ring-red-500') => {
    confirmDialog.value = { show: true, title, message, action, confirmText, confirmColor };
};

const executeConfirm = async () => {
    if (confirmDialog.value.action) await confirmDialog.value.action();
    confirmDialog.value.show = false;
};

const editValueModal = ref({
    show: false, type: '', fieldOrIndex: null, score: null, oldValue: '', newValue: ''
});

const openEditValue = (type, fieldOrIndex, value, score = null) => {
    editValueModal.value = { show: true, type, fieldOrIndex, score, oldValue: value, newValue: value };
};

const saveEditedValue = async () => {
    try {
        await axios.post(route('redis.key.value.update'), {
            key: selectedKeyData.value.key, db: props.currentDb, type: editValueModal.value.type,
            field: editValueModal.value.fieldOrIndex, score: editValueModal.value.score,
            old_value: editValueModal.value.oldValue, new_value: editValueModal.value.newValue
        });
        editValueModal.value.show = false;
        loadKeyData(selectedKeyData.value.key);
    } catch (e) {
        alert(e.response?.data?.error || __('error_generic'));
    }
};

const deleteItemValue = (type, fieldOrIndex, value) => {
    customConfirm(__('value_delete_title'), __('value_delete_msg'), async () => {
        try {
            await axios.post(route('redis.key.value.delete'), {
                key: selectedKeyData.value.key, db: props.currentDb, type: type, field: fieldOrIndex, value: value
            });
            loadKeyData(selectedKeyData.value.key);
        } catch (e) {
            alert(e.response?.data?.error || __('error_generic'));
        }
    });
};

const flattenKeys = (nodes) => {
    let keys = [];
    for (const key in nodes) {
        if (key === '__key__') keys.push(nodes[key]);
        else if (typeof nodes[key] === 'object' && nodes[key] !== null) keys = keys.concat(flattenKeys(nodes[key]));
    }
    return keys;
};

const allKeys = computed(() => flattenKeys(props.tree));

const searchResults = computed(() => {
    if (!searchQuery.value.trim()) return [];
    const lowerQuery = searchQuery.value.trim().toLowerCase();
    return allKeys.value.filter(k => k.toLowerCase().includes(lowerQuery));
});

const filteredBulkKeys = computed(() => {
    if (!bulkSearchQuery.value.trim()) return allKeys.value;
    const lower = bulkSearchQuery.value.trim().toLowerCase();
    return allKeys.value.filter(k => k.toLowerCase().includes(lower));
});

const switchDatabase = () => {
    searchQuery.value = '';
    router.get(route('dashboard'), { db: selectedDb.value });
};

const hasData = (dbIndex) => props.keyspace && props.keyspace[`db${dbIndex}`] !== undefined;

const getKeyCount = (dbIndex) => {
    if (hasData(dbIndex)) return props.keyspace[`db${dbIndex}`].keys;
    return 0;
};

const startTtlTimer = () => {
    if (ttlInterval) clearInterval(ttlInterval);
    if (currentTtl.value !== null && currentTtl.value > 0) {
        ttlInterval = setInterval(() => {
            currentTtl.value--;
            if (currentTtl.value <= 0) {
                clearInterval(ttlInterval);
                currentTtl.value = -2;
            }
        }, 1000);
    }
};

onUnmounted(() => { if (ttlInterval) clearInterval(ttlInterval); });

const loadKeyData = async (keyName) => {
    isLoadingKey.value = true;
    keyError.value = null;
    selectedKeyData.value = null;
    currentTtl.value = null;
    if (ttlInterval) clearInterval(ttlInterval);

    try {
        const response = await axios.get(route('redis.key.show'), { params: { key: keyName, db: props.currentDb } });
        selectedKeyData.value = response.data;
        currentTtl.value = response.data.ttl;
        startTtlTimer();
    } catch (error) {
        keyError.value = error.response?.data?.error || __('error_generic');
    } finally {
        isLoadingKey.value = false;
    }
};

const deleteKey = () => {
    customConfirm(__('delete_key'), __('delete_confirm', selectedKeyData.value.key), async () => {
        try {
            await axios.post(route('redis.key.delete'), { key: selectedKeyData.value.key, db: props.currentDb });
            selectedKeyData.value = null;
            if (ttlInterval) clearInterval(ttlInterval);
            router.get(route('dashboard'), { db: props.currentDb });
        } catch (e) {
            alert(e.response?.data?.error || __('error_generic'));
        }
    });
};

const openTtlModal = () => { newTtlInput.value = currentTtl.value; showTtlModal.value = true; };

const saveTtl = async () => {
    try {
        await axios.post(route('redis.key.ttl'), { key: selectedKeyData.value.key, db: props.currentDb, ttl: newTtlInput.value });
        showTtlModal.value = false;
        loadKeyData(selectedKeyData.value.key);
    } catch (e) { alert(e.response?.data?.error || __('error_generic')); }
};

const openRenameModal = () => { newKeyNameInput.value = selectedKeyData.value.key; showRenameModal.value = true; };

const saveRename = async () => {
    if (!newKeyNameInput.value || newKeyNameInput.value === selectedKeyData.value.key) { showRenameModal.value = false; return; }
    try {
        await axios.post(route('redis.key.rename'), { key: selectedKeyData.value.key, new_key: newKeyNameInput.value, db: props.currentDb });
        showRenameModal.value = false;
        router.get(route('dashboard'), { db: props.currentDb }, { onSuccess: () => loadKeyData(newKeyNameInput.value) });
    } catch (e) { alert(e.response?.data?.error || __('error_generic')); }
};

const flushDatabase = () => {
    customConfirm(
        __('flush_confirm_title', props.currentDb),
        __('flush_confirm_msg', props.currentDb),
        async () => {
            try {
                await axios.post(route('redis.db.flush'), { db: props.currentDb });
                selectedKeyData.value = null;
                router.get(route('dashboard'), { db: props.currentDb });
            } catch (e) { alert(e.response?.data?.error || __('error_generic')); }
        },
        __('delete_all'),
        'bg-red-700 hover:bg-red-800 focus:ring-red-500'
    );
};

const openBulkDeleteModal = () => {
    bulkSearchQuery.value = '';
    bulkSelectedKeys.value = [];
    showBulkDeleteModal.value = true;
};

const toggleSelectAllBulk = () => {
    if (bulkSelectedKeys.value.length === filteredBulkKeys.value.length) bulkSelectedKeys.value = [];
    else bulkSelectedKeys.value = [...filteredBulkKeys.value];
};

const executeBulkDelete = () => {
    if (bulkSelectedKeys.value.length === 0) return;
    customConfirm(
        __('bulk_delete'),
        __('bulk_delete_confirm', bulkSelectedKeys.value.length),
        async () => {
            try {
                await axios.post(route('redis.key.bulk-delete'), { keys: bulkSelectedKeys.value, db: props.currentDb });
                showBulkDeleteModal.value = false;
                selectedKeyData.value = null;
                router.get(route('dashboard'), { db: props.currentDb });
            } catch (e) { alert(e.response?.data?.error || __('error_generic')); }
        },
        __('delete_selected')
    );
};

const formatTTL = (ttl) => {
    if (ttl === null || ttl === undefined) return '-';
    if (ttl === -1) return __('infinite');
    if (ttl === -2 || ttl <= 0) return __('expired');
    if (ttl < 60) return `${ttl} ${__('sec')}`;
    const mins = Math.floor(ttl / 60);
    if (mins < 60) return `${mins} ${__('min')} ${ttl % 60} ${__('sec')}`;
    const hours = Math.floor(mins / 60);
    return `${hours} ${__('hour')} ${mins % 60} ${__('min')}`;
};

const dashboardContainerClass = computed(() => (
    store.layoutWidth === 'wide'
        ? 'w-full px-4 sm:px-6 lg:px-8'
        : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'
));
</script>

<template>
    <Head :title="__('dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('dashboard') }}</h2>
        </template>

        <div class="py-4">
            <div :class="dashboardContainerClass">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg flex flex-col md:flex-row min-h-[85vh] transition-colors duration-300">

                    <div class="w-full md:w-1/3 lg:w-1/4 xl:w-1/5 border-r border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 p-4 overflow-y-auto max-h-[85vh]">

                        <div class="mb-4 bg-white dark:bg-gray-800 p-3 rounded-lg border dark:border-gray-700 shadow-sm transition-colors duration-300">
                            <label for="db_selector" class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                {{ __('db_select') }}
                            </label>
                            <select
                                id="db_selector"
                                v-model="selectedDb"
                                @change="switchDatabase"
                                class="w-full text-sm bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500"
                            >
                                <option
                                    v-for="n in databases"
                                    :key="n-1"
                                    :value="n-1"
                                    :class="{'font-bold text-red-600 dark:text-red-400': hasData(n - 1), 'text-gray-400 dark:text-gray-600': !hasData(n - 1)}"
                                >
                                    DB {{ n - 1 }} {{ hasData(n - 1) ? `(${getKeyCount(n - 1)} ${__('keys_count')})` : `(${__('empty')})` }}
                                </option>
                            </select>
                        </div>

                        <div class="mb-4 flex space-x-2">
                            <div class="relative flex-1">
                                <input
                                    type="text"
                                    v-model="searchQuery"
                                    :placeholder="__('search_placeholder')"
                                    class="w-full text-sm bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 pl-10"
                                />
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <button @click="openBulkDeleteModal" :title="__('bulk_delete')" class="shrink-0 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 border border-red-200 dark:border-red-800 rounded-md px-3 flex items-center transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>

                        <div v-if="searchQuery.trim()">
                            <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('search_results') }} ({{ searchResults.length }})</h3>
                            <ul class="space-y-1">
                                <li v-for="key in searchResults" :key="key">
                                    <div @click="loadKeyData(key)" class="flex items-center space-x-2 py-1 px-2 hover:bg-red-50 dark:hover:bg-red-900/30 rounded cursor-pointer group transition-colors" :class="{'bg-red-100 dark:bg-red-900/50': selectedKeyData?.key === key}">
                                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 group-hover:text-red-600 dark:group-hover:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                        <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-red-700 dark:group-hover:text-red-400 break-all">{{ key }}</span>
                                    </div>
                                </li>
                                <li v-if="searchResults.length === 0" class="text-sm text-gray-500 dark:text-gray-400 italic py-2 px-2">
                                    {{ __('no_match') }}
                                </li>
                            </ul>
                        </div>

                        <div v-else>
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('redis_keys') }}</h3>
                            </div>
                            <div v-if="Object.keys(tree).length === 0" class="text-sm text-gray-500 dark:text-gray-400 italic">
                                {{ __('empty_db') }}
                            </div>
                            <TreeNode v-else :nodes="tree" class="-ml-4" @keySelected="loadKeyData" />
                        </div>
                    </div>

                    <div class="w-full md:w-2/3 lg:w-3/4 xl:w-4/5 p-6 bg-white dark:bg-gray-800 overflow-y-auto max-h-[85vh] relative text-gray-900 dark:text-gray-100 transition-colors duration-300">
                        <div v-if="selectedKeyData || isLoadingKey || keyError">
                            <div class="flex flex-col xl:flex-row xl:items-center justify-between border-b dark:border-gray-700 pb-4 mb-4 gap-4">
                                <h3 class="text-2xl font-bold break-all">
                                    {{ selectedKeyData?.key || __('loading') }}
                                </h3>
                                <div class="flex space-x-2 shrink-0 overflow-x-auto pb-1" v-if="selectedKeyData">
                                    <button @click="openRenameModal" class="bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-400 px-3 py-1 rounded text-sm transition-colors border border-indigo-200 dark:border-indigo-800 whitespace-nowrap">{{ __('rename') }}</button>
                                    <button @click="openTtlModal" class="bg-amber-50 dark:bg-amber-900/30 hover:bg-amber-100 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-400 px-3 py-1 rounded text-sm transition-colors border border-amber-200 dark:border-amber-800 whitespace-nowrap">{{ __('change_ttl') }}</button>
                                    <button @click="deleteKey" class="bg-red-50 dark:bg-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/50 text-red-700 dark:text-red-400 px-3 py-1 rounded text-sm transition-colors border border-red-200 dark:border-red-800 whitespace-nowrap">{{ __('delete_key') }}</button>
                                    <button @click="loadKeyData(selectedKeyData.key)" class="bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 px-3 py-1 rounded text-sm transition-colors border border-gray-300 dark:border-gray-600 whitespace-nowrap">{{ __('refresh') }}</button>
                                </div>
                            </div>

                            <div v-if="isLoadingKey" class="flex justify-center items-center py-12">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600"></div>
                                <span class="ml-2 text-gray-500 dark:text-gray-400">{{ __('key_loading') }}</span>
                            </div>

                            <div v-else-if="keyError" class="bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 p-4 rounded-md border border-red-200 dark:border-red-800">
                                {{ keyError }}
                            </div>

                            <div v-else-if="selectedKeyData">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                                    <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded border dark:border-gray-700">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 font-bold uppercase">{{ __('type') }}</span>
                                        <p class="text-lg font-semibold capitalize text-gray-800 dark:text-gray-200">{{ selectedKeyData.type }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded border dark:border-gray-700">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 font-bold uppercase">{{ __('size') }}</span>
                                        <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                                            {{ selectedKeyData.size }} {{ selectedKeyData.type === 'string' ? __('char') : __('item') }}
                                        </p>
                                    </div>
                                    <div class="bg-red-50 dark:bg-red-900/10 p-3 rounded border border-red-100 dark:border-red-900">
                                        <span class="text-xs text-red-500 font-bold uppercase">{{ __('ttl') }}</span>
                                        <p class="text-lg font-semibold text-red-900 dark:text-red-400" :class="{'text-red-600 animate-pulse': currentTtl > 0 && currentTtl < 10}">
                                            {{ formatTTL(currentTtl) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase">{{ __('data_content') }}</h4>
                                        <button v-if="selectedKeyData.type === 'string'" @click="openEditValue('string', null, selectedKeyData.data)" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 flex items-center text-sm">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            {{ __('edit_value') }}
                                        </button>
                                    </div>

                                    <div v-if="selectedKeyData.type === 'string'" class="border dark:border-gray-700 rounded-lg overflow-hidden text-sm">
                                        <div v-if="isComplex(selectedKeyData.data)" class="bg-gray-800 dark:bg-gray-900 p-4 overflow-x-auto shadow-inner">
                                            <JsonViewer :data="formatValue(selectedKeyData.data)" />
                                        </div>
                                        <div v-else class="bg-gray-50 dark:bg-gray-900 p-4 break-all whitespace-pre-wrap font-mono text-gray-800 dark:text-gray-200">
                                            {{ selectedKeyData.data }}
                                        </div>
                                    </div>

                                    <div v-else class="overflow-x-auto border dark:border-gray-700 rounded-lg">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                            <thead class="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th v-if="selectedKeyData.type === 'hash'" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-1/4">{{ __('field') }}</th>
                                                    <th v-else-if="selectedKeyData.type === 'zset'" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-1/4">{{ __('score') }}</th>
                                                    <th v-else-if="selectedKeyData.type === 'list'" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">{{ __('index') }}</th>
                                                    <th v-else-if="selectedKeyData.type === 'set'" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">-</th>

                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('value') }}</th>
                                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">{{ __('actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 font-mono">
                                                <template v-if="selectedKeyData.type === 'hash' || selectedKeyData.type === 'zset'">
                                                    <tr v-for="(value, field) in selectedKeyData.data" :key="field" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 bg-gray-50/50 dark:bg-gray-900/50 break-all border-r dark:border-gray-700 align-top">{{ field }}</td>
                                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-200 align-top">
                                                            <div v-if="isComplex(value)" class="bg-gray-800 dark:bg-gray-900 p-3 rounded-lg overflow-x-auto shadow-inner max-h-96"><JsonViewer :data="formatValue(value)" /></div>
                                                            <span v-else class="break-all">{{ value }}</span>
                                                        </td>
                                                        <td class="px-4 py-3 text-right whitespace-nowrap align-top space-x-3">
                                                            <button @click="openEditValue(selectedKeyData.type, field, value, selectedKeyData.type === 'zset' ? field : null)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                                                            <button @click="deleteItemValue(selectedKeyData.type, field, value)" class="text-red-600 dark:text-red-400 hover:text-red-900"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                                        </td>
                                                    </tr>
                                                </template>
                                                <template v-else>
                                                    <tr v-for="(value, index) in selectedKeyData.data" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 bg-gray-50/50 dark:bg-gray-900/50 border-r dark:border-gray-700 align-top">{{ selectedKeyData.type === 'list' ? index : '-' }}</td>
                                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-200 align-top">
                                                            <div v-if="isComplex(value)" class="bg-gray-800 dark:bg-gray-900 p-3 rounded-lg overflow-x-auto shadow-inner max-h-96"><JsonViewer :data="formatValue(value)" /></div>
                                                            <span v-else class="break-all">{{ value }}</span>
                                                        </td>
                                                        <td class="px-4 py-3 text-right whitespace-nowrap align-top space-x-3">
                                                            <button @click="openEditValue(selectedKeyData.type, selectedKeyData.type === 'list' ? index : value, value)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                                                            <button @click="deleteItemValue(selectedKeyData.type, selectedKeyData.type === 'list' ? index : value, value)" class="text-red-600 dark:text-red-400 hover:text-red-900"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div v-if="selectedKeyData.type !== 'string' && selectedKeyData.size === 0" class="text-center py-4 text-gray-500 dark:text-gray-400 italic bg-gray-50 dark:bg-gray-900 rounded border dark:border-gray-700">
                                        {{ __('empty_collection') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else>
                            <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200 border-b dark:border-gray-700 pb-2">{{ __('server_info') }} (DB: {{ currentDb }})</h3>
                            <div v-if="serverInfo.error" class="bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 p-4 rounded-md border border-red-200 dark:border-red-800">{{ serverInfo.error }}</div>
                            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-900">
                                    <p class="text-xs text-blue-500 font-semibold uppercase">{{ __('redis_version') }}</p>
                                    <p class="text-lg font-bold text-blue-900 dark:text-blue-400 mt-1">{{ serverInfo.version }}</p>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-100 dark:border-green-900">
                                    <p class="text-xs text-green-500 font-semibold uppercase">{{ __('total_keys') }}</p>
                                    <p class="text-lg font-bold text-green-900 dark:text-green-400 mt-1">{{ serverInfo.keys }}</p>
                                </div>
                                <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg border border-purple-100 dark:border-purple-900">
                                    <p class="text-xs text-purple-500 font-semibold uppercase">{{ __('used_memory') }}</p>
                                    <p class="text-lg font-bold text-purple-900 dark:text-purple-400 mt-1">{{ serverInfo.memory }}</p>
                                </div>
                                <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-100 dark:border-orange-900">
                                    <p class="text-xs text-orange-500 font-semibold uppercase">{{ __('uptime') }}</p>
                                    <p class="text-lg font-bold text-orange-900 dark:text-orange-400 mt-1">{{ serverInfo.uptime }} {{ __('days') }}</p>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end border-t dark:border-gray-700 pt-4">
                                <button @click="flushDatabase" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md shadow-sm transition-colors flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    {{ __('flush_db') }} (DB {{ currentDb }})
                                </button>
                            </div>

                            <div class="mt-10 border-t dark:border-gray-700 pt-6">
                                <div class="text-center text-gray-400 dark:text-gray-500 py-10">
                                    <svg class="mx-auto h-12 w-12 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-300">{{ __('no_key_selected') }}</h3>
                                    <p class="mt-1 text-sm">{{ __('select_key_desc') }}</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div v-if="showBulkDeleteModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-2xl w-[600px] max-w-[95vw] flex flex-col max-h-[90vh]">
                <h4 class="text-lg font-bold mb-2 text-gray-800 dark:text-gray-200 border-b dark:border-gray-700 pb-2">{{ __('bulk_delete') }}</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('filter_keys_bulk') }}</p>

                <div class="mb-4 relative">
                    <input
                        type="text" v-model="bulkSearchQuery" :placeholder="__('search_placeholder')"
                        class="w-full text-sm bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 pl-10"
                    />
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('total_found', filteredBulkKeys.length, bulkSelectedKeys.length) }}</span>
                    <button @click="toggleSelectAllBulk" class="text-sm text-red-600 dark:text-red-400 hover:underline font-medium">
                        {{ bulkSelectedKeys.length === filteredBulkKeys.length ? __('clear_selection') : __('select_all') }}
                    </button>
                </div>

                <div class="flex-grow flex flex-col min-h-[200px] max-h-[400px] mb-4 border dark:border-gray-700 rounded-md overflow-y-auto p-2 bg-gray-50 dark:bg-gray-900">
                    <label v-for="key in filteredBulkKeys" :key="key" class="flex items-center space-x-2 p-1.5 hover:bg-gray-200 dark:hover:bg-gray-800 cursor-pointer rounded transition-colors">
                        <input type="checkbox" :value="key" v-model="bulkSelectedKeys" class="rounded bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-600 text-red-600 focus:ring-red-500 w-4 h-4">
                        <span class="text-sm text-gray-800 dark:text-gray-300 break-all font-mono">{{ key }}</span>
                    </label>
                    <div v-if="filteredBulkKeys.length === 0" class="text-gray-500 text-sm italic p-2 text-center">{{ __('no_match') }}</div>
                </div>

                <div class="flex justify-end space-x-3 shrink-0 pt-4 border-t dark:border-gray-700">
                    <button @click="showBulkDeleteModal = false" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors font-medium">{{ __('cancel') }}</button>
                    <button @click="executeBulkDelete" :disabled="bulkSelectedKeys.length === 0" class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-md transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        {{ __('delete_selected') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="confirmDialog.show" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[100] transition-opacity">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-2xl w-96 max-w-[90vw] transform transition-all">
                <div class="flex items-center space-x-3 mb-4 text-red-600 dark:text-red-500">
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <h4 class="text-xl font-bold">{{ confirmDialog.title }}</h4>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">{{ confirmDialog.message }}</p>
                <div class="flex justify-end space-x-3">
                    <button @click="confirmDialog.show = false" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors font-medium">{{ __('cancel') }}</button>
                    <button @click="executeConfirm" :class="confirmDialog.confirmColor" class="px-4 py-2 text-white rounded-md transition-colors font-medium focus:ring-2 focus:outline-none focus:ring-offset-2">
                        {{ confirmDialog.confirmText }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="editValueModal.show" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-2xl w-[800px] max-w-[95vw] flex flex-col max-h-[90vh]">
                <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200 border-b dark:border-gray-700 pb-2">{{ __('edit_value') }} ({{ editValueModal.type }})</h4>

                <div class="mb-4 flex gap-4 text-sm bg-gray-50 dark:bg-gray-900 p-3 rounded border dark:border-gray-700">
                    <div v-if="editValueModal.type === 'hash'" class="w-full">
                        <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">{{ __('field') }}</span>
                        <input type="text" v-model="editValueModal.fieldOrIndex" disabled class="w-full bg-gray-100 dark:bg-gray-800 border-gray-300 dark:border-gray-600 rounded text-gray-600 dark:text-gray-400 cursor-not-allowed">
                    </div>
                    <div v-else-if="editValueModal.type === 'list'" class="w-full">
                        <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">{{ __('index') }}</span>
                        <input type="text" v-model="editValueModal.fieldOrIndex" disabled class="w-full bg-gray-100 dark:bg-gray-800 border-gray-300 dark:border-gray-600 rounded text-gray-600 dark:text-gray-400 cursor-not-allowed">
                    </div>
                    <div v-else-if="editValueModal.type === 'zset'" class="w-full">
                        <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">{{ __('score') }}</span>
                        <input type="number" v-model="editValueModal.score" class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded focus:ring-red-500 focus:border-red-500">
                    </div>
                </div>

                <div class="flex-grow flex flex-col min-h-0 mb-4">
                    <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">{{ __('value') }}</span>
                    <textarea
                        v-model="editValueModal.newValue"
                        class="w-full h-64 bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded shadow-sm focus:ring-red-500 focus:border-red-500 font-mono text-sm resize-y"
                    ></textarea>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 italic">{{ __('new_value_info') }}</p>
                </div>

                <div class="flex justify-end space-x-3 shrink-0 pt-4 border-t dark:border-gray-700">
                    <button @click="editValueModal.show = false" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors font-medium">{{ __('cancel') }}</button>
                    <button @click="saveEditedValue" class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-md transition-colors font-medium">{{ __('save_changes') }}</button>
                </div>
            </div>
        </div>

        <div v-if="showTtlModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl w-96 max-w-[90vw]">
                <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">{{ __('change_ttl') }}</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">{{ __('ttl_modal_msg') }}</p>
                <input type="number" v-model="newTtlInput" class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-md shadow-sm mb-4 focus:ring-red-500 focus:border-red-500" />
                <div class="flex justify-end space-x-2">
                    <button @click="showTtlModal = false" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors">{{ __('cancel') }}</button>
                    <button @click="saveTtl" class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-md transition-colors">{{ __('save') }}</button>
                </div>
            </div>
        </div>

        <div v-if="showRenameModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl w-96 max-w-[90vw]">
                <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">{{ __('rename') }}</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">{{ __('rename_modal_msg') }}</p>
                <input type="text" v-model="newKeyNameInput" class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 rounded-md shadow-sm mb-4 focus:ring-red-500 focus:border-red-500" />
                <div class="flex justify-end space-x-2">
                    <button @click="showRenameModal = false" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors">{{ __('cancel') }}</button>
                    <button @click="saveRename" class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-md transition-colors">{{ __('save') }}</button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
