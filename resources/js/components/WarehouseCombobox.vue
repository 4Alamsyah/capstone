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

type WarehouseOption = {
    id: number;
    code: string;
    name: string;
    location: string | null;
};

const props = defineProps<{
    id?: string;
    modelValue: number;
    warehouses: WarehouseOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [id: number];
    'create-requested': [name: string];
}>();

const searchTerm = ref('');
const isOpen = ref(false);

const filteredWarehouses = computed(() => {
    const query = searchTerm.value.trim().toLowerCase();

    if (query === '') {
        return props.warehouses;
    }

    return props.warehouses.filter(
        (warehouse) => warehouse.name.toLowerCase().includes(query) || warehouse.code.toLowerCase().includes(query),
    );
});

const displayValue = (value: unknown) => {
    const warehouse = props.warehouses.find((item) => item.id === value);

    return warehouse ? `${warehouse.code} - ${warehouse.name}` : '';
};

// Keeps the input's shown text in sync whenever `modelValue` changes from
// outside this component (e.g. auto-selected after a quick-create), since
// reka-ui only refreshes the display text on its own select/blur events.
watch(
    () => props.modelValue,
    (value) => {
        if (!isOpen.value) {
            searchTerm.value = displayValue(value);
        }
    },
    { immediate: true },
);

// Clearing on the combobox's own open transition (rather than on raw focus)
// avoids wiping the text when focus programmatically returns to this input
// after the quick-create Dialog closes.
watch(isOpen, (open) => {
    searchTerm.value = open ? '' : displayValue(props.modelValue);
});

const requestCreate = () => {
    const typed = searchTerm.value.trim();

    if (typed !== '') {
        emit('create-requested', typed);
    }
};

const handleEnter = () => {
    if (filteredWarehouses.value.length === 0) {
        requestCreate();
    }
};
</script>

<template>
    <ComboboxRoot
        :model-value="modelValue"
        v-model:open="isOpen"
        :ignore-filter="true"
        open-on-click
        class="relative"
        @update:model-value="(value) => emit('update:modelValue', value as number)"
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
                placeholder="Cari atau ketik nama warehouse baru"
                class="w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                @keydown.enter="handleEnter"
            />
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                class="z-50 max-h-60 min-w-[var(--reka-combobox-trigger-width)] overflow-y-auto rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <ComboboxViewport class="p-1">
                    <ComboboxItem
                        v-for="warehouse in filteredWarehouses"
                        :key="warehouse.id"
                        :value="warehouse.id"
                        :text-value="`${warehouse.code} ${warehouse.name}`"
                        class="cursor-default rounded-sm px-2 py-1.5 text-sm outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                    >
                        {{ warehouse.code }} - {{ warehouse.name }}
                    </ComboboxItem>

                    <div v-if="filteredWarehouses.length === 0" class="px-1 py-1 text-sm">
                        <button
                            type="button"
                            class="w-full rounded-sm px-2 py-1.5 text-left text-primary hover:bg-accent hover:text-accent-foreground"
                            @mousedown.prevent="requestCreate"
                        >
                            + Tambah "{{ searchTerm }}" sebagai warehouse baru
                        </button>
                    </div>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
