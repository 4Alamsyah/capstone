<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type QuotationItem = {
    id: number;
    quotation_number: string;
    order_date: string | null;
    delivery_date: string | null;
    currency_code: string;
    subtotal: string;
    customer: {
        id: number | null;
        name: string | null;
    };
    items: {
        id: number;
        part_number: string | null;
        part_name: string | null;
        quantity: string;
        unit_price: string;
        line_total: string;
    }[];
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
    quotations: QuotationItem[];
    filters: {
        search: string;
        order_from: string;
        order_to: string;
    };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales/customer-orders' },
    { title: 'Quotation List', href: '/sales/quotations' },
];

const filterForm = useForm({
    search: props.filters.search ?? '',
    order_from: props.filters.order_from ?? '',
    order_to: props.filters.order_to ?? '',
});

const submitFilter = () => {
    filterForm.get('/sales/quotations', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilter = () => {
    filterForm.search = '';
    filterForm.order_from = '';
    filterForm.order_to = '';
    submitFilter();
};

const deleteQuotation = (quotation: QuotationItem) => {
    if (!window.confirm(`Hapus quotation ${quotation.quotation_number}?`)) {
        return;
    }

    useForm({}).delete(`/sales/quotations/${quotation.id}`);
};

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No quotations found';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} quotations`;
});
</script>

<template>
    <Head title="Quotation List" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Quotation List" description="Daftar penawaran harga customer untuk ditindaklanjuti oleh tim sales." />
                <Button as-child>
                    <Link href="/sales/quotations/create">+ Register Quotation</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-end gap-2">
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Search</span>
                        <Input v-model="filterForm.search" placeholder="Quotation number, customer, part..." class="w-64" />
                    </div>
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Date From</span>
                        <Input v-model="filterForm.order_from" type="date" class="w-44" />
                    </div>
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Date To</span>
                        <Input v-model="filterForm.order_to" type="date" class="w-44" />
                    </div>
                    <Button variant="outline" @click="submitFilter">Filter</Button>
                    <Button v-if="filterForm.search || filterForm.order_from || filterForm.order_to" variant="ghost" @click="clearFilter">Clear</Button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">Quotation Number</th>
                                <th class="py-2 pr-3">Customer</th>
                                <th class="py-2 pr-3">Quotation Date</th>
                                <th class="py-2 pr-3">Delivery Target</th>
                                <th class="py-2 pr-3">Items</th>
                                <th class="py-2 pr-3">Total</th>
                                <th class="py-2 pr-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="quotations.length === 0">
                                <td colspan="7" class="py-8 text-center text-muted-foreground">No quotations found.</td>
                            </tr>
                            <tr v-for="quotation in quotations" :key="quotation.id" class="border-b border-sidebar-border/40 align-top last:border-0">
                                <td class="py-2 pr-3 font-mono font-medium">{{ quotation.quotation_number }}</td>
                                <td class="py-2 pr-3">{{ quotation.customer.name ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ quotation.order_date ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ quotation.delivery_date ?? '-' }}</td>
                                <td class="py-2 pr-3">
                                    <div class="space-y-1">
                                        <div v-for="item in quotation.items" :key="item.id" class="text-xs text-muted-foreground">
                                            {{ item.part_number || '-' }} - {{ item.part_name || '-' }} x {{ item.quantity }}
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 pr-3 font-semibold">{{ Number(quotation.subtotal).toLocaleString() }} {{ quotation.currency_code }}</td>
                                <td class="py-2 pr-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="outline" as-child>
                                            <Link :href="`/sales/quotations/${quotation.id}/edit`">Edit</Link>
                                        </Button>
                                        <Button size="sm" variant="destructive" @click="deleteQuotation(quotation)">Delete</Button>
                                    </div>
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
