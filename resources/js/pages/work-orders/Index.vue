<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type WoBom = {
    id: number;
    name: string;
    part: { id: number; part_number: string; name: string };
};

type WorkOrderItem = {
    id: number;
    wo_number: string;
    status: string;
    quantity: string;
    scheduled_date: string | null;
    created_at: string;
    purchase_order: {
        id: number | null;
        po_number: string | null;
    };
    bom: WoBom;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    workOrders: WorkOrderItem[];
    filters: { search: string; status: string };
    pagination: PaginationMeta;
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Work Order', href: '/work-orders' },
];

const statusColors: Record<string, string> = {
    draft:       'bg-gray-100 text-gray-600',
    released:    'bg-blue-100 text-blue-700',
    in_progress: 'bg-yellow-100 text-yellow-700',
    completed:   'bg-green-100 text-green-700',
    cancelled:   'bg-red-100 text-red-500',
};

const searchForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const submitSearch = () => {
    searchForm.get('/work-orders', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    searchForm.status = '';
    submitSearch();
};

const deleteWo = (wo: WorkOrderItem) => {
    if (!window.confirm(`Hapus Work Order ${wo.wo_number}?`)) {
        return;
    }

    useForm({}).delete(`/work-orders/${wo.id}`);
};

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No work orders found';
    }

    return `Showing ${props.pagination.from}–${props.pagination.to} of ${props.pagination.total} work orders`;
});
</script>

<template>
    <Head title="Work Orders" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Work Orders" description="Buat dan pantau Work Order produksi." />
                <Button as-child>
                    <Link href="/work-orders/create">+ Create WO</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <!-- Search / filter bar -->
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">Work Order List</h3>
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search WO or product..." class="w-full sm:w-64" />
                        <select
                            v-model="searchForm.status"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All Status</option>
                            <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <Button type="submit" variant="outline">Filter</Button>
                        <Button v-if="searchForm.search || searchForm.status" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                    </form>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">WO Number</th>
                                <th class="py-2 pr-3">Source PO</th>
                                <th class="py-2 pr-3">Product</th>
                                <th class="py-2 pr-3">BOM</th>
                                <th class="py-2 pr-3">Qty</th>
                                <th class="py-2 pr-3">Scheduled</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="workOrders.length === 0">
                                <td colspan="8" class="py-8 text-center text-muted-foreground">No work orders found.</td>
                            </tr>
                            <tr
                                v-for="wo in workOrders"
                                :key="wo.id"
                                class="border-b border-sidebar-border/40 last:border-0"
                            >
                                <td class="py-2 pr-3 font-mono font-medium">{{ wo.wo_number }}</td>
                                <td class="py-2 pr-3 font-mono text-xs text-muted-foreground">
                                    {{ wo.purchase_order.po_number ?? '-' }}
                                </td>
                                <td class="py-2 pr-3">
                                    <span class="text-xs text-muted-foreground">{{ wo.bom.part.part_number }}</span><br />
                                    {{ wo.bom.part.name }}
                                </td>
                                <td class="py-2 pr-3 text-muted-foreground">{{ wo.bom.name }}</td>
                                <td class="py-2 pr-3">{{ wo.quantity }}</td>
                                <td class="py-2 pr-3">{{ wo.scheduled_date ?? '–' }}</td>
                                <td class="py-2 pr-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="statusColors[wo.status]"
                                    >
                                        {{ statusLabels[wo.status] ?? wo.status }}
                                    </span>
                                </td>
                                <td class="py-2 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="outline" as-child>
                                            <Link :href="`/work-orders/${wo.id}/report`">Report</Link>
                                        </Button>
                                        <Button size="sm" variant="outline" as-child>
                                            <Link :href="`/work-orders/${wo.id}`">View</Link>
                                        </Button>
                                        <Button size="sm" variant="destructive" @click="deleteWo(wo)">Delete</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4 flex items-center justify-between gap-3 text-sm text-muted-foreground">
                    <span>{{ paginationText }}</span>
                    <div class="flex gap-2">
                        <Button v-if="pagination.prev_page_url" size="sm" variant="outline" as-child>
                            <Link :href="pagination.prev_page_url">Prev</Link>
                        </Button>
                        <Button v-if="pagination.next_page_url" size="sm" variant="outline" as-child>
                            <Link :href="pagination.next_page_url">Next</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
