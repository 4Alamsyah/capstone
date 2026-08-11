<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import BomHierarchy, { type BomTreeNode } from '@/components/bom/BomHierarchy.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PartOption = {
    id: number;
    part_number: string;
    name: string;
    has_bom: boolean;
};

type WorkCenterOption = {
    id: number;
    name: string;
    price_per_operation: string | null;
};

type Props = {
    parts: PartOption[];
    workCenters: WorkCenterOption[];
    preselectedPartId: number | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BOM', href: '/bom' },
    { title: 'Create', href: '/bom/create' },
];

type LineItem = {
    line_type: 'part' | 'operation';
    component_part_id: number | null;
    work_center_id: number | null;
    quantity: string;
    notes: string;
};

const form = useForm({
    part_id: (props.preselectedPartId ?? '') as unknown as number,
    name: '',
    description: '',
    is_active: true,
    planning_strategy: 'order_oriented' as 'order_oriented' | 'stock_driven',
    items: [] as LineItem[],
});

const addItem = (type: 'part' | 'operation') => {
    form.items.push({
        line_type: type,
        component_part_id: null,
        work_center_id: null,
        quantity: '1',
        notes: '',
    });
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
    delete bomTrees[index];
    delete bomTreeLoading[index];
};

// ── Sub-assembly hierarchy preview ──────────────────────────────────────────
const bomTrees = reactive<Record<number, BomTreeNode | null>>({});
const bomTreeLoading = reactive<Record<number, boolean>>({});

const fetchBomTree = async (index: number, partId: number | null) => {
    delete bomTrees[index];

    if (!partId) {
        return;
    }

    const part = props.parts.find((p) => p.id === partId);

    if (!part?.has_bom) {
        return;
    }

    bomTreeLoading[index] = true;

    try {
        const response = await fetch(`/bom/tree/${partId}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        bomTrees[index] = data.tree;
    } finally {
        bomTreeLoading[index] = false;
    }
};

const totalPartItems = computed(() => form.items.filter((i) => i.line_type === 'part').length);
const totalOperationItems = computed(() => form.items.filter((i) => i.line_type === 'operation').length);

const submit = () => {
    form.post('/bom', {
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Create BOM" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <Heading title="Create BOM" description="Definisikan produk jadi dan daftar komponen / operasi yang dibutuhkan." />
                <Button variant="outline" as-child>
                    <Link href="/bom">← Back</Link>
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <!-- Header card -->
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-4 text-sm font-semibold">BOM Header</h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="part_id">Finished Product <span class="text-destructive">*</span></Label>
                            <select
                                id="part_id"
                                v-model="form.part_id"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">— pilih part —</option>
                                <option v-for="p in parts" :key="p.id" :value="p.id">
                                    {{ p.part_number }} – {{ p.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.part_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="bom-name">BOM Name <span class="text-destructive">*</span></Label>
                            <Input id="bom-name" v-model="form.name" placeholder="e.g. BOM v1.0" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="bom-desc">Description</Label>
                            <textarea
                                id="bom-desc"
                                v-model="form.description"
                                rows="2"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                placeholder="opsional"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="flex items-center gap-3">
                            <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-input" />
                            <Label for="is_active" class="cursor-pointer">Active</Label>
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="planning_strategy">Planning Strategy</Label>
                            <select
                                id="planning_strategy"
                                v-model="form.planning_strategy"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="order_oriented">Order Oriented — diproduksi inline saat MO di-report</option>
                                <option value="stock_driven">Stock Driven — konsumsi dari stock, auto-replenish kalau kurang</option>
                            </select>
                            <InputError :message="form.errors.planning_strategy" />
                        </div>
                    </div>
                </div>

                <!-- Items card -->
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold">
                            Line Items
                            <span class="ml-2 text-xs font-normal text-muted-foreground">
                                {{ totalPartItems }} part{{ totalPartItems !== 1 ? 's' : '' }},
                                {{ totalOperationItems }} operation{{ totalOperationItems !== 1 ? 's' : '' }}
                            </span>
                        </h3>
                        <div class="flex gap-2">
                            <Button type="button" size="sm" variant="outline" @click="addItem('part')">+ Part</Button>
                            <Button type="button" size="sm" variant="outline" @click="addItem('operation')">+ Operation</Button>
                        </div>
                    </div>

                    <div v-if="form.items.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        Belum ada item. Klik "+ Part" atau "+ Operation" untuk menambah.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(item, idx) in form.items"
                            :key="idx"
                            class="rounded-md border border-sidebar-border/50 p-3"
                        >
                            <div class="grid items-start gap-3 sm:grid-cols-[auto_1fr_120px_1fr_auto]">
                                <!-- Type badge -->
                                <div class="flex items-center pt-1">
                                    <span
                                        class="inline-flex rounded px-2 py-0.5 text-xs font-semibold"
                                        :class="item.line_type === 'part' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                                    >
                                        {{ item.line_type === 'part' ? 'Part' : 'Operation' }}
                                    </span>
                                </div>

                                <!-- Component select -->
                                <div class="grid gap-1">
                                    <Label class="text-xs">{{ item.line_type === 'part' ? 'Component Part' : 'Work Center' }}</Label>
                                    <select
                                        v-if="item.line_type === 'part'"
                                        v-model="item.component_part_id"
                                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                        @change="fetchBomTree(idx, item.component_part_id)"
                                    >
                                        <option :value="null">— pilih part —</option>
                                        <option v-for="p in parts" :key="p.id" :value="p.id">
                                            {{ p.part_number }} – {{ p.name }}{{ p.has_bom ? ' (sub-assembly)' : '' }}
                                        </option>
                                    </select>
                                    <select
                                        v-else
                                        v-model="item.work_center_id"
                                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option :value="null">— pilih work center —</option>
                                        <option v-for="wc in workCenters" :key="wc.id" :value="wc.id">
                                            {{ wc.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Quantity -->
                                <div class="grid gap-1">
                                    <Label class="text-xs">Qty</Label>
                                    <Input v-model="item.quantity" type="number" min="0.0001" step="any" placeholder="1" />
                                </div>

                                <!-- Notes -->
                                <div class="grid gap-1">
                                    <Label class="text-xs">Notes</Label>
                                    <Input v-model="item.notes" placeholder="opsional" />
                                </div>

                                <!-- Remove -->
                                <div class="flex items-center pt-5">
                                    <Button type="button" size="sm" variant="ghost" class="text-destructive hover:text-destructive" @click="removeItem(idx)">
                                        ✕
                                    </Button>
                                </div>
                            </div>

                            <!-- Sub-assembly hierarchy preview -->
                            <div v-if="bomTreeLoading[idx]" class="mt-2 text-xs text-muted-foreground">Memuat hirarki BOM…</div>
                            <BomHierarchy v-else-if="bomTrees[idx]" :tree="bomTrees[idx]!" class="mt-2" />
                        </div>
                    </div>

                    <InputError :message="form.errors.items" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" as-child>
                        <Link href="/bom">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Save BOM</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
