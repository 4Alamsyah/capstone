<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type RowItem = {
    id: number;
    part_number: string | null;
    part_name: string | null;
    quantity: string;
    received_quantity: string;
    unit: string;
};

type Row = {
    id: number;
    po_number: string;
    status: number;
    order_date: string | null;
    expected_date: string | null;
    supplier: {
        id: number | null;
        name: string | null;
    };
    items: RowItem[];
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
    purchaseOrders: Row[];
    filters: {
        search: string;
    };
    pagination: PaginationMeta;
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'Report Arrival', href: '/purchase/po/arrivals' },
];

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const submitSearch = () => {
    searchForm.get('/purchase/po/arrivals', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    submitSearch();
};

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No open purchase orders found';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} open purchase orders`;
});
</script>

<template>
    <Head title="Report Arrival" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading title="Report Arrival" description="Pilih PO untuk melaporkan barang yang baru datang." />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">Open Purchase Orders</h3>
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search PO number or supplier..." class="w-full sm:w-80" />
                        <Button type="submit" variant="outline">Search</Button>
                        <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">PO Number</th>
                                <th class="py-2 pr-3">Supplier</th>
                                <th class="py-2 pr-3">Order Date</th>
                                <th class="py-2 pr-3">Expected Date</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 pr-3">Remaining Items</th>
                                <th class="py-2 pr-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.purchaseOrders.length === 0">
                                <td colspan="7" class="py-8 text-center text-muted-foreground">No open purchase orders found.</td>
                            </tr>
                            <tr v-for="po in props.purchaseOrders" :key="po.id" class="border-b border-sidebar-border/40 align-top last:border-0">
                                <td class="py-2 pr-3 font-mono font-medium">{{ po.po_number }}</td>
                                <td class="py-2 pr-3">{{ po.supplier.name ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ po.order_date ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ po.expected_date ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ props.statusLabels[String(po.status)] ?? po.status }}</td>
                                <td class="py-2 pr-3">
                                    <div class="space-y-1">
                                        <div v-for="item in po.items" :key="item.id" class="text-xs text-muted-foreground">
                                            {{ item.part_number }} - {{ item.part_name }} | Remaining {{ Number(item.quantity) - Number(item.received_quantity) }} {{ item.unit }}
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 pr-3 text-right">
                                    <Button size="sm" variant="outline" as-child>
                                        <Link :href="`/purchase/po/${po.id}/arrivals/report`">Open Report Form</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between gap-3 text-sm text-muted-foreground">
                    <span>{{ paginationText }}</span>
                    <div class="flex gap-2">
                        <Button v-if="props.pagination.prev_page_url" size="sm" variant="outline" as-child>
                            <Link :href="props.pagination.prev_page_url">Prev</Link>
                        </Button>
                        <Button v-if="props.pagination.next_page_url" size="sm" variant="outline" as-child>
                            <Link :href="props.pagination.next_page_url">Next</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
