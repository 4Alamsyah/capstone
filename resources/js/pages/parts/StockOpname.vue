<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PartRow = {
    id: number;
    part_number: string;
    name: string;
    category: 'purchase' | 'manufacture';
    inventory_type: 'material' | 'tool';
    uom_code: string | null;
    quantity: number;
};

type FlashProps = {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

type CategoryFilter = '' | 'purchase' | 'manufacture';

type Props = {
    parts: PartRow[];
    filters: {
        search: string;
        category: CategoryFilter;
    };
};

const props = defineProps<Props>();
const page = usePage<FlashProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Part List', href: '/parts' },
    { title: 'Stock Opname', href: '/parts/stock-opname' },
];

const searchForm = useForm({
    search: props.filters.search ?? '',
    category: props.filters.category ?? '',
});

const categoryLabel = (category: CategoryFilter): string => {
    if (category === 'purchase') {
        return 'part purchase';
    }

    if (category === 'manufacture') {
        return 'part manufacture';
    }

    return 'semua part';
};

// Draft quantities keyed by part id, seeded from the server and re-seeded
// whenever a fresh part list arrives (search, or after a save reloads props).
const draftQuantities = reactive<Record<number, number>>({});

const seedDrafts = (parts: PartRow[]): void => {
    for (const key of Object.keys(draftQuantities)) {
        delete draftQuantities[Number(key)];
    }

    for (const part of parts) {
        draftQuantities[part.id] = part.quantity;
    }
};

watch(() => props.parts, seedDrafts, { immediate: true });

const isDirty = (part: PartRow): boolean => draftQuantities[part.id] !== part.quantity;

const dirtyCount = computed(() => props.parts.filter(isDirty).length);

const bulkUpdateForm = useForm<{ updates: Array<{ part_id: number; quantity: number }> }>({
    updates: [],
});

const zeroOutForm = useForm<{ category: CategoryFilter }>({
    category: '',
});

const saving = computed(() => bulkUpdateForm.processing || zeroOutForm.processing);

const submitSearch = () => {
    searchForm.get('/parts/stock-opname', { preserveState: true });
};

const clearSearch = () => {
    searchForm.search = '';
    searchForm.get('/parts/stock-opname', { preserveState: true });
};

// Category select applies immediately, same as clicking Search.
watch(
    () => searchForm.category,
    () => searchForm.get('/parts/stock-opname', { preserveState: true }),
);

const saveChanges = () => {
    const updates = props.parts
        .filter(isDirty)
        .map((part) => ({ part_id: part.id, quantity: draftQuantities[part.id] }));

    if (updates.length === 0) {
        return;
    }

    bulkUpdateForm.updates = updates;
    bulkUpdateForm.post('/parts/stock-opname', { preserveScroll: true });
};

const zeroAllStock = () => {
    const scope = categoryLabel(searchForm.category as CategoryFilter);

    if (!window.confirm(`Pemutihan akan menolkan SEMUA stock ${scope}. Tindakan ini tidak bisa dibatalkan. Lanjutkan?`)) {
        return;
    }

    zeroOutForm.category = searchForm.category as CategoryFilter;
    zeroOutForm.post('/parts/stock-opname/zero', { preserveScroll: true });
};
</script>

<template>
    <Head title="Stock Opname" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Stock Opname" description="Sesuaikan qty stock hasil perhitungan fisik langsung di tabel." />
                <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                    <Input v-model="searchForm.search" placeholder="Cari part..." class="w-full sm:w-72" />
                    <select
                        v-model="searchForm.category"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option value="">Semua Kategori</option>
                        <option value="purchase">Purchase</option>
                        <option value="manufacture">Manufacture</option>
                    </select>
                    <Button type="submit" variant="outline">Search</Button>
                    <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                </form>
            </div>

            <div v-if="page.props.flash?.success" class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ page.props.flash.error }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 bg-muted/30 p-4">
                <p class="text-sm text-muted-foreground">
                    {{ dirtyCount > 0 ? `${dirtyCount} part belum disimpan.` : 'Ubah qty di tabel lalu klik Save Changes.' }}
                </p>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="destructive" :disabled="saving" @click="zeroAllStock">
                        Pemutihan ({{ categoryLabel(searchForm.category as CategoryFilter) }})
                    </Button>
                    <Button type="button" :disabled="dirtyCount === 0 || saving" @click="saveChanges">
                        {{ saving ? 'Menyimpan...' : 'Save Changes' }}
                    </Button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-sidebar-border/70">
                <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                    <thead class="bg-sidebar-accent/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Part Number</th>
                            <th class="px-4 py-3 font-medium">Part Name</th>
                            <th class="px-4 py-3 font-medium">Category</th>
                            <th class="px-4 py-3 font-medium">UOM</th>
                            <th class="px-4 py-3 font-medium">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="part in parts" :key="part.id" :class="['align-middle', isDirty(part) ? 'bg-amber-50' : '']">
                            <td class="px-4 py-3 font-medium">{{ part.part_number }}</td>
                            <td class="px-4 py-3">{{ part.name }}</td>
                            <td class="px-4 py-3 capitalize text-muted-foreground">{{ part.category }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ part.uom_code ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <Input
                                    v-model.number="draftQuantities[part.id]"
                                    type="number"
                                    min="0"
                                    step="1"
                                    class="w-28"
                                />
                            </td>
                        </tr>

                        <tr v-if="parts.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">Tidak ada part ditemukan.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
