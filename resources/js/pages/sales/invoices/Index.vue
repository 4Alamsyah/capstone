<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type InvoiceItem = {
    id: number;
    invoice_number: string;
    invoice_date: string | null;
    due_date: string | null;
    status: number;
    currency_code: string;
    subtotal: string;
    tax_amount: string;
    total_amount: string;
    customer: {
        id: number | null;
        name: string | null;
    };
    customer_order: {
        id: number | null;
        co_number: string | null;
    };
    items: Array<{
        id: number;
        part_number: string | null;
        part_name: string | null;
        description: string | null;
        quantity: string;
        unit_price: string;
        line_total: string;
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
    invoices: InvoiceItem[];
    filters: {
        search: string;
        status: string;
    };
    pagination: PaginationMeta;
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales/customer-orders' },
    { title: 'Invoice List', href: '/sales/invoices' },
];

const statusColors: Record<number, string> = {
    1: 'bg-gray-100 text-gray-700',
    2: 'bg-blue-100 text-blue-700',
    3: 'bg-green-100 text-green-700',
    9: 'bg-red-100 text-red-700',
};

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const submitFilter = () => {
    filterForm.get('/sales/invoices', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilter = () => {
    filterForm.search = '';
    filterForm.status = '';
    submitFilter();
};

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No invoices found';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} invoices`;
});
</script>

<template>
    <Head title="Invoice List" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Invoice List" description="Daftar invoice penjualan customer." />
                <Button as-child>
                    <Link href="/sales/invoices/create">+ Register Invoice</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-end gap-2">
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Search</span>
                        <Input v-model="filterForm.search" placeholder="Invoice number, customer, order..." class="w-64" />
                    </div>
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Status</span>
                        <select
                            v-model="filterForm.status"
                            class="h-10 w-40 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All</option>
                            <option v-for="(label, code) in props.statusLabels" :key="code" :value="code">{{ label }}</option>
                        </select>
                    </div>
                    <Button variant="outline" @click="submitFilter">Filter</Button>
                    <Button v-if="filterForm.search || filterForm.status" variant="ghost" @click="clearFilter">Clear</Button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">Invoice Number</th>
                                <th class="py-2 pr-3">Customer</th>
                                <th class="py-2 pr-3">Source Order</th>
                                <th class="py-2 pr-3">Invoice Date</th>
                                <th class="py-2 pr-3">Due Date</th>
                                <th class="py-2 pr-3">Total</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 pr-3">Items</th>
                                <th class="py-2 pr-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.invoices.length === 0">
                                <td colspan="9" class="py-8 text-center text-muted-foreground">No invoices found.</td>
                            </tr>
                            <tr v-for="invoice in props.invoices" :key="invoice.id" class="border-b border-sidebar-border/40 align-top last:border-0">
                                <td class="py-2 pr-3 font-mono font-medium">{{ invoice.invoice_number }}</td>
                                <td class="py-2 pr-3">{{ invoice.customer.name ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ invoice.customer_order.co_number ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ invoice.invoice_date ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ invoice.due_date ?? '-' }}</td>
                                <td class="py-2 pr-3 font-semibold">{{ Number(invoice.total_amount).toLocaleString() }} {{ invoice.currency_code }}</td>
                                <td class="py-2 pr-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusColors[invoice.status]">
                                        {{ props.statusLabels[String(invoice.status)] ?? invoice.status }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3">
                                    <div class="space-y-1">
                                        <div v-for="item in invoice.items" :key="item.id" class="text-xs text-muted-foreground">
                                            {{ item.description || ([item.part_number, item.part_name].filter(Boolean).join(' - ')) || '-' }} x {{ item.quantity }}
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 pr-3 text-right">
                                    <Button size="sm" variant="outline" as-child>
                                        <a :href="`/sales/invoices/${invoice.id}/document`" target="_blank" rel="noopener">Download Invoice PDF</a>
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
