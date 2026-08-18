<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import BomHierarchy, { type BomTreeNode } from '@/components/bom/BomHierarchy.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type PartOption = {
    id: number;
    part_number: string;
    name: string;
    has_bom: boolean;
    default_uom_id: number | null;
};

type WorkCenterOption = {
    id: number;
    name: string;
    price_per_operation: string | null;
};

type UomOption = {
    id: number;
    code: string;
    name: string;
};

type EditableBomLineItem = {
    id: number;
    line_type: 'part' | 'operation';
    component_part_id: number | null;
    work_center_id: number | null;
    uom_id: number | null;
    uom_code: string | null | undefined;
    quantity: string | null;
    notes: string | null;
    sort_order: number;
    label: string | null | undefined;
    has_sub_bom: boolean;
};

type EditableBomNode = {
    code: string;
    bom_id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    planning_strategy: 'order_oriented' | 'stock_driven';
    part: { id: number; part_number: string; name: string };
    items: EditableBomLineItem[];
    children: EditableBomNode[];
};

type Props = {
    bom: EditableBomNode;
    parts: PartOption[];
    workCenters: WorkCenterOption[];
    uoms: UomOption[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BOM', href: '/bom' },
    { title: props.bom.name, href: `/bom/${props.bom.bom_id}` },
];

// ── Tree navigation ──────────────────────────────────────────────────────────
const findNodeByCode = (node: EditableBomNode, code: string): EditableBomNode | null => {
    if (node.code === code) {
        return node;
    }
    for (const child of node.children) {
        const found = findNodeByCode(child, code);
        if (found) {
            return found;
        }
    }
    return null;
};

const initialSelectedCode = (): string => {
    const fromUrl = new URLSearchParams(window.location.search).get('selected');
    return fromUrl !== null && findNodeByCode(props.bom, fromUrl) ? fromUrl : '';
};

const selectedCode = ref(initialSelectedCode());

const selectedNode = computed<EditableBomNode>(() => findNodeByCode(props.bom, selectedCode.value) ?? props.bom);

const selectNode = (code: string) => {
    selectedCode.value = code;
    isEditing.value = false;

    const url = new URL(window.location.href);
    if (code === '') {
        url.searchParams.delete('selected');
    } else {
        url.searchParams.set('selected', code);
    }
    window.history.replaceState(null, '', url);
};

type TreeRow = {
    key: string;
    depth: number;
    code: string;
    label: string;
    lineType: 'part' | 'operation';
    isActive: boolean;
    selectableCode: string | null;
};

const flattenNode = (node: EditableBomNode, depth: number, rows: TreeRow[]): void => {
    rows.push({
        key: `node-${node.bom_id}`,
        depth,
        code: node.code === '' ? node.part.part_number : node.code,
        label: `${node.part.part_number} – ${node.part.name}`,
        lineType: 'part',
        isActive: node.is_active,
        selectableCode: node.code,
    });

    let childIdx = 0;
    node.items.forEach((item, i) => {
        if (item.has_sub_bom) {
            const child = node.children[childIdx];
            childIdx++;
            if (child) {
                flattenNode(child, depth + 1, rows);
                return;
            }
        }

        rows.push({
            key: `item-${item.id}`,
            depth: depth + 1,
            code: node.code === '' ? `${i + 1}` : `${node.code}.${i + 1}`,
            label: item.label ?? '–',
            lineType: item.line_type,
            isActive: true,
            selectableCode: null,
        });
    });
};

const treeRows = computed<TreeRow[]>(() => {
    const rows: TreeRow[] = [];
    flattenNode(props.bom, 0, rows);
    return rows;
});

// Map from an item id (in the selected node) to the child node it expands to,
// so view/edit rows can offer a "jump into this sub-assembly" action.
const selectedNodeChildByItemId = computed<Record<number, EditableBomNode>>(() => {
    const node = selectedNode.value;
    const map: Record<number, EditableBomNode> = {};
    let childIdx = 0;
    node.items.forEach((item) => {
        if (item.has_sub_bom) {
            const child = node.children[childIdx];
            childIdx++;
            if (child) {
                map[item.id] = child;
            }
        }
    });
    return map;
});

// ── View / Edit toggle (scoped to whichever node is selected) ────────────────
const isEditing = ref(false);

type LineItem = {
    line_type: 'part' | 'operation';
    component_part_id: number | null;
    work_center_id: number | null;
    uom_id: number | null;
    quantity: string;
    notes: string;
    sort_order: number;
};

const editForm = useForm({
    part_id: selectedNode.value.part.id as unknown as number,
    name: selectedNode.value.name,
    description: selectedNode.value.description ?? '',
    is_active: selectedNode.value.is_active,
    planning_strategy: selectedNode.value.planning_strategy,
    items: [] as LineItem[],
});

const seedEditForm = (node: EditableBomNode) => {
    editForm.part_id = node.part.id as unknown as number;
    editForm.name = node.name;
    editForm.description = node.description ?? '';
    editForm.is_active = node.is_active;
    editForm.planning_strategy = node.planning_strategy;
    editForm.items = node.items.map((item) => ({
        line_type: item.line_type,
        component_part_id: item.component_part_id,
        work_center_id: item.work_center_id,
        uom_id: item.uom_id,
        quantity: item.quantity ?? (item.line_type === 'part' ? '1' : ''),
        notes: item.notes ?? '',
        sort_order: item.sort_order,
    }));
    editForm.clearErrors();
    Object.keys(bomTrees).forEach((key) => delete bomTrees[Number(key)]);
    Object.keys(bomTreeLoading).forEach((key) => delete bomTreeLoading[Number(key)]);
};

// Leaving edit mode when the user picks a different tree node avoids editing
// one node's items while the form still points at another node's bom_id.
watch(selectedCode, () => {
    isEditing.value = false;
});

const startEdit = () => {
    seedEditForm(selectedNode.value);
    isEditing.value = true;
    editForm.items.forEach((item, idx) => {
        if (item.line_type === 'part') {
            fetchBomTree(idx, item.component_part_id);
        }
    });
};

const cancelEdit = () => {
    isEditing.value = false;
    seedEditForm(selectedNode.value);
};

const submitEdit = () => {
    editForm.put(`/bom/${selectedNode.value.bom_id}`, {
        preserveScroll: true,
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
        uom_id: null,
        quantity: type === 'part' ? '1' : '',
        notes: '',
        sort_order: editForm.items.length,
    });
};

// When a component part is picked on a material line, default its UOM from
// the part's own default UOM (still editable per line afterwards).
const onComponentPartSelected = (item: LineItem, partId: number | null) => {
    if (!item.uom_id) {
        item.uom_id = props.parts.find((p) => p.id === partId)?.default_uom_id ?? null;
    }
};

const removeItem = (index: number) => {
    editForm.items.splice(index, 1);
    delete bomTrees[index];
    delete bomTreeLoading[index];
};

// ── Sub-assembly hierarchy preview (read-only, for parts not yet its own tree node) ──
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

const totalPartItems = computed(() => editForm.items.filter((i) => i.line_type === 'part').length);
const totalOperationItems = computed(() => editForm.items.filter((i) => i.line_type === 'operation').length);

const deleteBom = () => {
    if (!window.confirm(`Hapus BOM "${props.bom.name}" beserta semua item-nya?`)) {
        return;
    }

    useForm({}).delete(`/bom/${props.bom.bom_id}`, {
        onError: (errors) => {
            if (errors.bom) {
                window.alert(errors.bom);
            }
        },
    });
};
</script>

<template>
    <Head :title="`BOM – ${bom.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    :title="selectedNode.name"
                    :description="`Product: ${selectedNode.part.part_number} – ${selectedNode.part.name}`"
                />
                <div class="flex gap-2">
                    <Button v-if="!isEditing" variant="outline" @click="startEdit">Edit</Button>
                    <Button v-if="!isEditing && selectedCode === ''" variant="destructive" @click="deleteBom">Delete</Button>
                    <Button variant="outline" as-child>
                        <Link href="/bom">← Back</Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
                <!-- ── Navigation tree ── -->
                <div class="rounded-lg border border-sidebar-border/70 p-3">
                    <h3 class="mb-3 px-1 text-sm font-semibold">Navigation</h3>
                    <div class="max-h-[70vh] space-y-0.5 overflow-y-auto">
                        <button
                            v-for="row in treeRows"
                            :key="row.key"
                            type="button"
                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm"
                            :class="[
                                row.selectableCode !== null ? 'cursor-pointer hover:bg-sidebar-accent' : 'cursor-default opacity-70',
                                row.selectableCode !== null && row.selectableCode === selectedCode ? 'bg-sidebar-accent font-medium' : '',
                            ]"
                            :style="{ paddingLeft: `${row.depth * 14 + 8}px` }"
                            :disabled="row.selectableCode === null"
                            @click="row.selectableCode !== null && selectNode(row.selectableCode)"
                        >
                            <span
                                v-if="row.lineType === 'operation'"
                                class="inline-flex shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold bg-purple-100 text-purple-700"
                            >
                                Op
                            </span>
                            <span class="font-mono text-xs text-muted-foreground">{{ row.code }}</span>
                            <span class="truncate" :class="!row.isActive ? 'text-muted-foreground line-through' : ''">{{ row.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- ── Detail panel ── -->
                <div class="flex flex-col gap-6">
                    <!-- ── View mode ── -->
                    <template v-if="!isEditing">
                        <!-- Header card -->
                        <div class="rounded-lg border border-sidebar-border/70 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-semibold">BOM Info</h3>
                                <Link
                                    v-if="selectedCode !== ''"
                                    :href="`/bom/${selectedNode.bom_id}`"
                                    class="text-xs text-muted-foreground underline hover:text-foreground"
                                >
                                    Buka sebagai halaman sendiri →
                                </Link>
                            </div>
                            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs text-muted-foreground">BOM Name</dt>
                                    <dd class="font-medium">{{ selectedNode.name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Status</dt>
                                    <dd>
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="selectedNode.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                        >
                                            {{ selectedNode.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Planning Strategy</dt>
                                    <dd>
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="selectedNode.planning_strategy === 'stock_driven' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'"
                                        >
                                            {{ selectedNode.planning_strategy === 'stock_driven' ? 'Stock Driven' : 'Order Oriented' }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-muted-foreground">Description</dt>
                                    <dd>{{ selectedNode.description ?? '–' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Items table -->
                        <div class="rounded-lg border border-sidebar-border/70 p-4">
                            <h3 class="mb-4 text-sm font-semibold">
                                Line Items
                                <span class="ml-2 text-xs font-normal text-muted-foreground">({{ selectedNode.items.length }} total)</span>
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                            <th class="py-2 pr-3">#</th>
                                            <th class="py-2 pr-3">Type</th>
                                            <th class="py-2 pr-3">Component / Operation</th>
                                            <th class="py-2 pr-3">Qty</th>
                                            <th class="py-2 pr-3">UOM</th>
                                            <th class="py-2 pr-3">Notes</th>
                                            <th class="py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="selectedNode.items.length === 0">
                                            <td colspan="7" class="py-6 text-center text-muted-foreground">No items.</td>
                                        </tr>
                                        <tr
                                            v-for="(item, idx) in selectedNode.items"
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
                                            <td class="py-2 pr-3">{{ item.line_type === 'part' ? formatQty(item.quantity ?? '0') : '–' }}</td>
                                            <td class="py-2 pr-3">{{ item.line_type === 'part' ? (item.uom_code ?? '–') : '–' }}</td>
                                            <td class="py-2 pr-3">{{ item.notes ?? '–' }}</td>
                                            <td class="py-2">
                                                <Button
                                                    v-if="selectedNodeChildByItemId[item.id]"
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="selectNode(selectedNodeChildByItemId[item.id].code)"
                                                >
                                                    Lihat sub-BOM →
                                                </Button>
                                            </td>
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
                                <h3 class="mb-4 text-sm font-semibold">Edit BOM Header{{ selectedCode !== '' ? ` (${selectedNode.part.part_number})` : '' }}</h3>
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

                                    <div class="grid gap-2 sm:col-span-2">
                                        <Label for="edit-planning-strategy">Planning Strategy</Label>
                                        <select
                                            id="edit-planning-strategy"
                                            v-model="editForm.planning_strategy"
                                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                        >
                                            <option value="order_oriented">Order Oriented — diproduksi inline saat MO di-report</option>
                                            <option value="stock_driven">Stock Driven — konsumsi dari stock, auto-replenish kalau kurang</option>
                                        </select>
                                        <InputError :message="editForm.errors.planning_strategy" />
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
                                        class="rounded-md border border-sidebar-border/50 p-3"
                                    >
                                        <div class="grid items-start gap-3 sm:grid-cols-[auto_1fr_190px_1fr_auto]">
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
                                                    @change="fetchBomTree(idx, item.component_part_id); onComponentPartSelected(item, item.component_part_id)"
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

                                            <div v-if="item.line_type === 'part'" class="grid gap-1">
                                                <Label class="text-xs">Qty / UOM</Label>
                                                <div class="flex gap-1">
                                                    <Input v-model="item.quantity" type="number" min="0.0001" step="any" placeholder="1" class="w-20" />
                                                    <select
                                                        v-model="item.uom_id"
                                                        class="w-full rounded-md border border-input bg-transparent px-2 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                                    >
                                                        <option :value="null">— satuan —</option>
                                                        <option v-for="u in uoms" :key="u.id" :value="u.id">{{ u.code }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div v-else />

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

                                        <!-- Sub-assembly hierarchy preview -->
                                        <div v-if="bomTreeLoading[idx]" class="mt-2 text-xs text-muted-foreground">Memuat hirarki BOM…</div>
                                        <BomHierarchy v-else-if="bomTrees[idx]" :tree="bomTrees[idx]!" class="mt-2" />
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
            </div>
        </div>
    </AppLayout>
</template>
