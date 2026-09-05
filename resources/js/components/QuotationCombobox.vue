<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxViewport,
} from 'reka-ui';
import { cn } from '@/lib/utils';

type QuotationOption = {
    quotation_number: string;
    items_label: string;
};

const props = defineProps<{
    id?: string;
    modelValue: string;
    quotations: QuotationOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const searchTerm = ref(props.modelValue);
const isOpen = ref(false);

const filteredQuotations = computed(() => {
    const query = searchTerm.value.trim().toLowerCase();

    if (query === '') {
        return props.quotations;
    }

    return props.quotations.filter(
        (quotation) =>
            quotation.quotation_number.toLowerCase().includes(query) ||
            quotation.items_label.toLowerCase().includes(query),
    );
});

const displayValue = (value: unknown) => (typeof value === 'string' ? value : '');

// Keeps the input text in sync when `modelValue` changes from outside this
// component (e.g. form reset after submit), without discarding whatever the
// user is currently typing/searching.
watch(
    () => props.modelValue,
    (value) => {
        if (!isOpen.value) {
            searchTerm.value = value;
        }
    },
    { immediate: true },
);

// Quo No stays a free-text column (no FK to quotations), so unlike a strict
// select we still propagate whatever text the user typed even if they never
// pick a suggestion from the list.
watch(isOpen, (open) => {
    if (!open) {
        emit('update:modelValue', searchTerm.value);
    }
});

const handleSelect = (value: unknown) => {
    searchTerm.value = String(value ?? '');
};
</script>

<template>
    <ComboboxRoot
        :model-value="modelValue"
        v-model:open="isOpen"
        :ignore-filter="true"
        open-on-click
        class="relative"
        @update:model-value="handleSelect"
    >
        <ComboboxAnchor
            :class="
                cn(
                    'flex h-9 w-full items-center rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs',
                    'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                )
            "
        >
            <ComboboxInput
                :id="id"
                v-model="searchTerm"
                :display-value="displayValue"
                placeholder="Cari no quotation / nama barang"
                class="w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            />
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                class="z-50 max-h-60 min-w-[var(--reka-combobox-trigger-width)] overflow-y-auto rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <ComboboxViewport class="p-1">
                    <ComboboxItem
                        v-for="quotation in filteredQuotations"
                        :key="quotation.quotation_number"
                        :value="quotation.quotation_number"
                        :text-value="`${quotation.quotation_number} ${quotation.items_label}`"
                        class="cursor-default rounded-sm px-2 py-1.5 text-sm outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                    >
                        <div class="flex flex-col">
                            <span class="font-medium">{{ quotation.quotation_number }}</span>
                            <span v-if="quotation.items_label" class="text-xs text-muted-foreground">{{ quotation.items_label }}</span>
                        </div>
                    </ComboboxItem>

                    <div v-if="filteredQuotations.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                        Tidak ada quotation yang cocok.
                    </div>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
