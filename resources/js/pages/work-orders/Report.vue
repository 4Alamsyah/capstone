<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type ReportWorkOrderItem = {
    id: number;
    wo_number: string;
    status: string;
    quantity: string;
    reports_count: number;
    bom: {
        id: number;
        name: string;
        part: {
            part_number: string;
            name: string;
        };
    };
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
    workOrders: ReportWorkOrderItem[];
    filters: { search: string; status: string };
    pagination: PaginationMeta;
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Report MO', href: '/work-orders/report' },
];

const searchForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const submitSearch = () => {
    searchForm.get('/work-orders/report', {
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

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No manufacture orders available for reporting';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} manufacture orders`;
});
</script>

<template>
    <Head title="Report MO" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading
                title="Report MO"
                description="Ubah status MO dan masuk ke halaman pelaporan hasil produksi per manufacture order."
            />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">Manufacture Order To Report</h3>
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search MO or product..." class="w-full sm:w-64" />
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

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">MO Number</th>
                                <th class="py-2 pr-3">Product</th>
                                <th class="py-2 pr-3">BOM</th>
                                <th class="py-2 pr-3">Qty</th>
                                <th class="py-2 pr-3">Reports</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="workOrders.length === 0">
                                <td colspan="7" class="py-8 text-center text-muted-foreground">Tidak ada MO untuk ditampilkan.</td>
                            </tr>
                            <tr v-for="workOrder in workOrders" :key="workOrder.id" class="border-b border-sidebar-border/40 last:border-0">
                                <td class="py-2 pr-3 font-mono font-medium">{{ workOrder.wo_number }}</td>
                                <td class="py-2 pr-3">
                                    <span class="text-xs text-muted-foreground">{{ workOrder.bom.part.part_number }}</span><br />
                                    {{ workOrder.bom.part.name }}
                                </td>
                                <td class="py-2 pr-3">{{ workOrder.bom.name }}</td>
                                <td class="py-2 pr-3">{{ formatQty(workOrder.quantity) }}</td>
                                <td class="py-2 pr-3">{{ workOrder.reports_count }}</td>
                                <td class="py-2 pr-3">{{ statusLabels[workOrder.status] ?? workOrder.status }}</td>
                                <td class="py-2 text-right">
                                    <Button size="sm" as-child>
                                        <Link :href="`/work-orders/${workOrder.id}/report`">Report</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

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
