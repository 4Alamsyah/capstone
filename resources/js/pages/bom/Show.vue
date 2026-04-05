<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
};

type WorkCenterOption = {
    id: number;
    name: string;
    price_per_operation: string | null;
};

type BomLineItem = {
    id: number;
    line_type: 'part' | 'operation';
    component_part_id: number | null;
    work_center_id: number | null;
    quantity: string;
    notes: string | null;
    sort_order: number;
    label: string | null | undefined;
};

type BomDetail = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    part: { id: number; part_number: string; name: string };
    items: BomLineItem[];
    created_at: string;
};

type Props = {
    bom: BomDetail;
    parts: PartOption[];
    workCenters: WorkCenterOption[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BOM', href: '/bom' },
    { title: props.bom.name, href: `/bom/${props.bom.id}` },
];

// ── View / Edit toggle ────────────────────────────────────────────────────────
const isEditing = ref(false);

type LineItem = {
    line_type: 'part' | 'operation';
    component_part_id: number | null;
    work_center_id: number | null;
    quantity: string;
    notes: string;
    sort_order: number;
};

const editForm = useForm({
    part_id: props.bom.part.id as unknown as number,
    name: props.bom.name,
    description: props.bom.description ?? '',
    is_active: props.bom.is_active,
    items: props.bom.items.map((item) => ({
        line_type: item.line_type,
        component_part_id: item.component_part_id,
        work_center_id: item.work_center_id,
        quantity: item.quantity,
        notes: item.notes ?? '',
        sort_order: item.sort_order,
    })) as LineItem[],
});

const startEdit = () => {
    isEditing.value = true;
};

const cancelEdit = () => {
    isEditing.value = false;
    editForm.reset();
    editForm.part_id = props.bom.part.id as unknown as number;
    editForm.name = props.bom.name;
    editForm.description = props.bom.description ?? '';
    editForm.is_active = props.bom.is_active;
    editForm.items = props.bom.items.map((item) => ({
        line_type: item.line_type,
        component_part_id: item.component_part_id,
        work_center_id: item.work_center_id,
        quantity: item.quantity,
        notes: item.notes ?? '',
        sort_order: item.sort_order,
    }));
    editForm.clearErrors();
};

const submitEdit = () => {
    editForm.put(`/bom/${props.bom.id}`, {
        onSuccess: () => {
            isEditing.value = false;
        },
    });
};

const addItem = (type: 'part' | 'operation') => {
    editForm.items.push({
        line_type: type,
        component_part_id: null,
        work_center_id: null,
        quantity: '1',
        notes: '',
        sort_order: editForm.items.length,
    });
};

const removeItem = (index: number) => {
    editForm.items.splice(index, 1);
};

const totalPartItems = computed(() => editForm.items.filter((i) => i.line_type === 'part').length);
const totalOperationItems = computed(() => editForm.items.filter((i) => i.line_type === 'operation').length);

const deleteBom = () => {
    if (!window.confirm(`Hapus BOM "${props.bom.name}" beserta semua item-nya?`)) {
        return;
    }

    useForm({}).delete(`/bom/${props.bom.id}`);
};
</script>

<template>
    <Head :title="`BOM – ${bom.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading :title="bom.name" :description="`Product: ${bom.part.part_number} – ${bom.part.name}`" />
                <div class="flex gap-2">
                    <Button v-if="!isEditing" variant="outline" @click="startEdit">Edit</Button>
                    <Button v-if="!isEditing" variant="destructive" @click="deleteBom">Delete</Button>
                    <Button variant="outline" as-child>
                        <Link href="/bom">← Back</Link>
                    </Button>
                </div>
            </div>

            <!-- ── View mode ── -->
            <template v-if="!isEditing">
                <!-- Header card -->
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-3 text-sm font-semibold">BOM Info</h3>
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">BOM Name</dt>
                            <dd class="font-medium">{{ bom.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Status</dt>
                            <dd>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="bom.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ bom.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">Description</dt>
                            <dd>{{ bom.description ?? '–' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Items table -->
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-4 text-sm font-semibold">
                        Line Items
                        <span class="ml-2 text-xs font-normal text-muted-foreground">({{ bom.items.length }} total)</span>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                    <th class="py-2 pr-3">#</th>
                                    <th class="py-2 pr-3">Type</th>
                                    <th class="py-2 pr-3">Component / Operation</th>
                                    <th class="py-2 pr-3">Qty</th>
                                    <th class="py-2">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="bom.items.length === 0">
                                    <td colspan="5" class="py-6 text-center text-muted-foreground">No items.</td>
                                </tr>
                                <tr
                                    v-for="(item, idx) in bom.items"
                                    :key="item.id"
                                    class="border-b border-sidebar-border/40 last:border-0"
                                >
                                    <td class="py-2 pr-3 text-muted-foreground">{{ idx + 1 }}</td>
                                    <td class="py-2 pr-3">
                                        <span
                                            class="inline-flex rounded px-2 py-0.5 text-xs font-semibold"
                                            :class="item.line_type === 'part' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                                        >
                                            {{ item.line_type === 'part' ? 'Part' : 'Operation' }}
                                        </span>
                                    </td>
                                    <td class="py-2 pr-3">{{ item.label ?? '–' }}</td>
                                    <td class="py-2 pr-3">{{ item.quantity }}</td>
                                    <td class="py-2">{{ item.notes ?? '–' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <!-- ── Edit mode ── -->
            <template v-else>
                <form class="space-y-6" @submit.prevent="submitEdit">
                    <!-- Header card -->
                    <div class="rounded-lg border border-sidebar-border/70 p-4">
                        <h3 class="mb-4 text-sm font-semibold">Edit BOM Header</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="edit-part">Finished Product <span class="text-destructive">*</span></Label>
                                <select
                                    id="edit-part"
                                    v-model="editForm.part_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">— pilih part —</option>
                                    <option v-for="p in parts" :key="p.id" :value="p.id">
                                        {{ p.part_number }} – {{ p.name }}
                                    </option>
                                </select>
                                <InputError :message="editForm.errors.part_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-name">BOM Name <span class="text-destructive">*</span></Label>
                                <Input id="edit-name" v-model="editForm.name" />
                                <InputError :message="editForm.errors.name" />
                            </div>

                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="edit-desc">Description</Label>
                                <textarea
                                    id="edit-desc"
                                    v-model="editForm.description"
                                    rows="2"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                />
                                <InputError :message="editForm.errors.description" />
                            </div>

                            <div class="flex items-center gap-3">
                                <input id="edit-active" v-model="editForm.is_active" type="checkbox" class="h-4 w-4 rounded border-input" />
                                <Label for="edit-active" class="cursor-pointer">Active</Label>
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

                        <div v-if="editForm.items.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                            Belum ada item.
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="(item, idx) in editForm.items"
                                :key="idx"
                                class="grid items-start gap-3 rounded-md border border-sidebar-border/50 p-3 sm:grid-cols-[auto_1fr_120px_1fr_auto]"
                            >
                                <div class="flex items-center pt-1">
                                    <span
                                        class="inline-flex rounded px-2 py-0.5 text-xs font-semibold"
                                        :class="item.line_type === 'part' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                                    >
                                        {{ item.line_type === 'part' ? 'Part' : 'Operation' }}
                                    </span>
                                </div>

                                <div class="grid gap-1">
                                    <Label class="text-xs">{{ item.line_type === 'part' ? 'Component Part' : 'Work Center' }}</Label>
                                    <select
                                        v-if="item.line_type === 'part'"
                                        v-model="item.component_part_id"
                                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option :value="null">— pilih part —</option>
                                        <option v-for="p in parts" :key="p.id" :value="p.id">
                                            {{ p.part_number }} – {{ p.name }}
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

                                <div class="grid gap-1">
                                    <Label class="text-xs">Qty</Label>
                                    <Input v-model="item.quantity" type="number" min="0.0001" step="any" placeholder="1" />
                                </div>

                                <div class="grid gap-1">
                                    <Label class="text-xs">Notes</Label>
                                    <Input v-model="item.notes" placeholder="opsional" />
                                </div>

                                <div class="flex items-center pt-5">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="text-destructive hover:text-destructive"
                                        @click="removeItem(idx)"
                                    >
                                        ✕
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <InputError :message="editForm.errors.items" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="cancelEdit">Cancel</Button>
                        <Button type="submit" :disabled="editForm.processing">Save Changes</Button>
                    </div>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
