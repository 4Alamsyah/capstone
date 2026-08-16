<script setup lang="ts">
import { useLocale } from '@/composables/useLocale';

const { locale, updateLocale } = useLocale();

const tabs = [
    { value: 'id', label: 'ID' },
    { value: 'en', label: 'EN' },
] as const;
</script>

<template>
    <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
        <button
            v-for="{ value, label } in tabs"
            :key="value"
            @click="updateLocale(value)"
            :class="[
                'flex items-center rounded-md px-3 py-1.5 text-sm transition-colors',
                locale === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            {{ label }}
        </button>
    </div>
</template>
