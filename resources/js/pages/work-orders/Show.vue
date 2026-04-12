<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type BomLineItem = {
    id: number;
    line_type: 'part' | 'operation';
    quantity: string;
    notes: string | null;
    label: string | null | undefined;
};

type WorkOrderDetail = {
    id: number;
    wo_number: string;
    status: string;
    quantity: string;
    scheduled_date: string | null;
    notes: string | null;
    created_at: string;
    purchase_order: {
        id: number | null;
        po_number: string | null;
    };
    reports_count: number;
    bom: {
        id: number;
        name: string;
        part: { id: number; part_number: string; name: string };
        items: BomLineItem[];
    };
};

type Props = {
    workOrder: WorkOrderDetail;
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Work Order', href: '/work-orders' },
    { title: props.workOrder.wo_number, href: `/work-orders/${props.workOrder.id}` },
];

const statusColors: Record<string, string> = {
    draft:       'bg-gray-100 text-gray-600',
    released:    'bg-blue-100 text-blue-700',
    in_progress: 'bg-yellow-100 text-yellow-700',
    completed:   'bg-green-100 text-green-700',
    cancelled:   'bg-red-100 text-red-500',
};

const isEditing = ref(false);

const editForm = useForm({
    status:         props.workOrder.status,
    quantity:       props.workOrder.quantity,
    scheduled_date: props.workOrder.scheduled_date ?? '',
    notes:          props.workOrder.notes ?? '',
});

const cancelEdit = () => {
    isEditing.value = false;
    editForm.status         = props.workOrder.status;
    editForm.quantity       = props.workOrder.quantity;
    editForm.scheduled_date = props.workOrder.scheduled_date ?? '';
    editForm.notes          = props.workOrder.notes ?? '';
    editForm.clearErrors();
};

const submitEdit = () => {
    editForm.put(`/work-orders/${props.workOrder.id}`, {
        onSuccess: () => {
            isEditing.value = false;
        },
    });
};

const deleteWo = () => {
    if (!window.confirm(`Hapus Work Order ${props.workOrder.wo_number}?`)) {
        return;
    }

    useForm({}).delete(`/work-orders/${props.workOrder.id}`);
};
</script>

<template>
    <Head :title="`WO – ${workOrder.wo_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    :title="workOrder.wo_number"
                    :description="`${workOrder.bom.part.part_number} – ${workOrder.bom.part.name} | BOM: ${workOrder.bom.name}`"
                />
                <div class="flex gap-2">
                    <Button v-if="!isEditing" variant="outline" as-child>
                        <Link :href="`/work-orders/${workOrder.id}/report`">Report MO</Link>
                    </Button>
                    <Button v-if="!isEditing" variant="outline" as-child>
                        <Link :href="`/work-orders/logs?search=${workOrder.wo_number}`">Log MO</Link>
                    </Button>
                    <Button v-if="!isEditing" variant="outline" @click="isEditing = true">Edit</Button>
                    <Button v-if="!isEditing" variant="destructive" @click="deleteWo">Delete</Button>
                    <Button variant="outline" as-child>
                        <Link href="/work-orders">← Back</Link>
                    </Button>
                </div>
            </div>

            <!-- View mode -->
            <template v-if="!isEditing">
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <dl class="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-muted-foreground">WO Number</dt>
                            <dd class="font-mono font-semibold">{{ workOrder.wo_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Status</dt>
                            <dd>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusColors[workOrder.status]"
                                >
                                    {{ statusLabels[workOrder.status] ?? workOrder.status }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Quantity</dt>
                            <dd>{{ workOrder.quantity }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Scheduled Date</dt>
                            <dd>{{ workOrder.scheduled_date ?? '–' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Created</dt>
                            <dd>{{ workOrder.created_at }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Source PO</dt>
                            <dd>{{ workOrder.purchase_order.po_number ?? '–' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Report Count</dt>
                            <dd>{{ workOrder.reports_count }}</dd>
                        </div>
                        <div class="sm:col-span-3">
                            <dt class="text-xs text-muted-foreground">Notes</dt>
                            <dd class="whitespace-pre-wrap">{{ workOrder.notes ?? '–' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- BOM items -->
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-4 text-sm font-semibold">
                        BOM Line Items
                        <span class="ml-2 text-xs font-normal text-muted-foreground">({{ workOrder.bom.items.length }} lines)</span>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                    <th class="py-2 pr-3">#</th>
                                    <th class="py-2 pr-3">Type</th>
                                    <th class="py-2 pr-3">Component / Operation</th>
                                    <th class="py-2 pr-3">Qty per BOM</th>
                                    <th class="py-2">Required (× {{ workOrder.quantity }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="workOrder.bom.items.length === 0">
                                    <td colspan="5" class="py-6 text-center text-muted-foreground">No BOM items.</td>
                                </tr>
                                <tr
                                    v-for="(item, idx) in workOrder.bom.items"
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
                                    <td class="py-2 font-medium">
                                        {{ (parseFloat(item.quantity) * parseFloat(workOrder.quantity)).toFixed(4).replace(/\.?0+$/, '') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <!-- Edit mode -->
            <template v-else>
                <form class="space-y-5 rounded-lg border border-sidebar-border/70 p-4" @submit.prevent="submitEdit">
                    <h3 class="text-sm font-semibold">Edit Work Order</h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit-status">Status</Label>
                            <select
                                id="edit-status"
                                v-model="editForm.status"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError :message="editForm.errors.status" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-qty">Quantity</Label>
                            <Input id="edit-qty" v-model="editForm.quantity" type="number" min="0.0001" step="any" />
                            <InputError :message="editForm.errors.quantity" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-date">Scheduled Date</Label>
                            <Input id="edit-date" v-model="editForm.scheduled_date" type="date" />
                            <InputError :message="editForm.errors.scheduled_date" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="edit-notes">Notes</Label>
                            <textarea
                                id="edit-notes"
                                v-model="editForm.notes"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            />
                            <InputError :message="editForm.errors.notes" />
                        </div>
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
