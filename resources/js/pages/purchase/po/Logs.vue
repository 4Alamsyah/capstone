<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type LogItem = {
    id: number;
    arrival_date: string | null;
    notes: string | null;
    reported_by: string | null;
    purchase_order: {
        id: number | null;
        po_number: string | null;
        supplier_name: string | null;
    };
    items: Array<{
        id: number;
        part_number: string | null;
        part_name: string | null;
        warehouse_code: string | null;
        warehouse_name: string | null;
        quantity: string;
        notes: string | null;
    }>;
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
    logs: LogItem[];
    filters: {
        search: string;
    };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'Log Report', href: '/purchase/po/arrivals/logs' },
];

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const submitSearch = () => {
    searchForm.get('/purchase/po/arrivals/logs', {
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
        return 'No arrival logs found';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} arrival logs`;
});
</script>

<template>
    <Head title="Log Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading title="Log Report" description="Riwayat report barang datang dari supplier." />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">Arrival History</h3>
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search PO number or supplier..." class="w-full sm:w-80" />
                        <Button type="submit" variant="outline">Search</Button>
                        <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                    </form>
                </div>

                <div class="space-y-3">
                    <div v-if="props.logs.length === 0" class="rounded-md border border-dashed border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground">
                        Belum ada report arrival.
                    </div>

                    <div v-for="log in props.logs" :key="log.id" class="rounded-md border border-sidebar-border/50 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ log.purchase_order.po_number }} - {{ log.purchase_order.supplier_name }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Arrival date: {{ log.arrival_date ?? '-' }}
                                    <span v-if="log.reported_by"> | by {{ log.reported_by }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button v-if="log.purchase_order.id" size="sm" variant="outline" as-child>
                                    <Link :href="`/purchase/po/${log.purchase_order.id}/arrivals/report`">Open PO</Link>
                                </Button>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1 text-sm">
                            <div v-for="item in log.items" :key="item.id" class="text-muted-foreground">
                                {{ item.part_number }} - {{ item.part_name }} | +{{ formatQty(item.quantity) }} to {{ item.warehouse_code }} - {{ item.warehouse_name }}
                            </div>
                        </div>

                        <div v-if="log.notes" class="mt-3 text-sm">{{ log.notes }}</div>
                    </div>
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
