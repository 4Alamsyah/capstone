<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type ApInvoiceRow = {
    id: number;
    ap_invoice_number: string;
    supplier_invoice_number: string | null;
    invoice_date: string | null;
    due_date: string | null;
    status: number;
    approval_notes: string | null;
    paid_at: string | null;
    currency_code: string;
    subtotal: string;
    tax_amount: string;
    total_amount: string;
    amount_paid: string;
    balance_due: string;
    supplier: {
        id: number | null;
        name: string | null;
    };
    purchase_order: {
        id: number | null;
        po_number: string | null;
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
    apInvoices: ApInvoiceRow[];
    filters: {
        search: string;
        status: string;
    };
    pagination: PaginationMeta;
    statusLabels: Record<string, string>;
    canManageApprovals: boolean;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'AP Invoice', href: '/purchase/ap/invoices' },
];

const statusColors: Record<number, string> = {
    1: 'bg-gray-100 text-gray-700',
    2: 'bg-amber-100 text-amber-700',
    3: 'bg-blue-100 text-blue-700',
    4: 'bg-cyan-100 text-cyan-700',
    5: 'bg-green-100 text-green-700',
    8: 'bg-rose-100 text-rose-700',
    9: 'bg-red-100 text-red-700',
};

const isEditable = (apInvoice: ApInvoiceRow): boolean => apInvoice.status === 1;
const isDeletable = (apInvoice: ApInvoiceRow): boolean => [1, 8, 9].includes(apInvoice.status);
const canSubmit = (apInvoice: ApInvoiceRow): boolean => apInvoice.status === 1;
const canApprove = (apInvoice: ApInvoiceRow): boolean => props.canManageApprovals && apInvoice.status === 2;
const canRecordPayment = (apInvoice: ApInvoiceRow): boolean => apInvoice.status === 3 || apInvoice.status === 4;

const submitForApproval = (apInvoice: ApInvoiceRow) => {
    if (!window.confirm(`Ajukan AP Invoice ${apInvoice.ap_invoice_number} untuk approval?`)) {
        return;
    }

    useForm({}).post(`/purchase/ap/invoices/${apInvoice.id}/submit`, {
        preserveScroll: true,
    });
};

const approveInvoice = (apInvoice: ApInvoiceRow) => {
    const notes = window.prompt('Catatan approval (opsional):', apInvoice.approval_notes ?? '') ?? '';

    useForm({ approval_notes: notes }).post(`/purchase/ap/invoices/${apInvoice.id}/approve`, {
        preserveScroll: true,
    });
};

const rejectInvoice = (apInvoice: ApInvoiceRow) => {
    const notes = window.prompt('Alasan reject (wajib):', apInvoice.approval_notes ?? '') ?? '';

    if (notes.trim() === '') {
        window.alert('Alasan reject wajib diisi.');
        return;
    }

    useForm({ approval_notes: notes }).post(`/purchase/ap/invoices/${apInvoice.id}/reject`, {
        preserveScroll: true,
    });
};

const deleteInvoice = (apInvoice: ApInvoiceRow) => {
    if (!window.confirm(`Hapus AP Invoice ${apInvoice.ap_invoice_number}?`)) {
        return;
    }

    useForm({}).delete(`/purchase/ap/invoices/${apInvoice.id}`, {
        preserveScroll: true,
    });
};

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const submitFilter = () => {
    filterForm.get('/purchase/ap/invoices', {
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
        return 'No AP invoices found';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} AP invoices`;
});
</script>

<template>
    <Head title="AP Invoice" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="AP Invoice" description="Invoice dari supplier yang perlu diapprove dan dibayar." />
                <Button as-child>
                    <Link href="/purchase/ap/invoices/create">+ Register AP Invoice</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-end gap-2">
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Search</span>
                        <Input v-model="filterForm.search" placeholder="AP invoice number, supplier, PO..." class="w-64" />
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
                                <th class="py-2 pr-3">AP Invoice Number</th>
                                <th class="py-2 pr-3">Supplier Ref</th>
                                <th class="py-2 pr-3">Supplier</th>
                                <th class="py-2 pr-3">Source PO</th>
                                <th class="py-2 pr-3">Invoice Date</th>
                                <th class="py-2 pr-3">Due Date</th>
                                <th class="py-2 pr-3">Total</th>
                                <th class="py-2 pr-3">Balance Due</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 pr-3">Items</th>
                                <th class="py-2 pr-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.apInvoices.length === 0">
                                <td colspan="11" class="py-8 text-center text-muted-foreground">No AP invoices found.</td>
                            </tr>
                            <tr v-for="apInvoice in props.apInvoices" :key="apInvoice.id" class="border-b border-sidebar-border/40 align-top last:border-0">
                                <td class="py-2 pr-3 font-mono font-medium">{{ apInvoice.ap_invoice_number }}</td>
                                <td class="py-2 pr-3">{{ apInvoice.supplier_invoice_number ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ apInvoice.supplier.name ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ apInvoice.purchase_order.po_number ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ apInvoice.invoice_date ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ apInvoice.due_date ?? '-' }}</td>
                                <td class="py-2 pr-3 font-semibold">{{ Number(apInvoice.total_amount).toLocaleString() }} {{ apInvoice.currency_code }}</td>
                                <td class="py-2 pr-3">
                                    <div class="font-mono">{{ Number(apInvoice.balance_due).toLocaleString() }} {{ apInvoice.currency_code }}</div>
                                    <div v-if="Number(apInvoice.amount_paid) > 0" class="text-xs text-muted-foreground">
                                        Paid: {{ Number(apInvoice.amount_paid).toLocaleString() }}
                                    </div>
                                </td>
                                <td class="py-2 pr-3">
                                    <div class="space-y-1">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusColors[apInvoice.status]">
                                            {{ props.statusLabels[String(apInvoice.status)] ?? apInvoice.status }}
                                        </span>
                                        <p v-if="apInvoice.approval_notes" class="text-xs text-muted-foreground">{{ apInvoice.approval_notes }}</p>
                                    </div>
                                </td>
                                <td class="py-2 pr-3">
                                    <div class="space-y-1">
                                        <div v-for="item in apInvoice.items" :key="item.id" class="text-xs text-muted-foreground">
                                            {{ item.description || ([item.part_number, item.part_name].filter(Boolean).join(' - ')) || '-' }} x {{ formatQty(item.quantity) }}
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 pr-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Button
                                            v-if="isEditable(apInvoice)"
                                            size="sm"
                                            variant="outline"
                                            as-child
                                        >
                                            <Link :href="`/purchase/ap/invoices/${apInvoice.id}/edit`">Edit</Link>
                                        </Button>
                                        <Button
                                            v-if="canSubmit(apInvoice)"
                                            size="sm"
                                            variant="outline"
                                            @click="submitForApproval(apInvoice)"
                                        >
                                            Submit for Approval
                                        </Button>
                                        <Button
                                            v-if="canApprove(apInvoice)"
                                            size="sm"
                                            variant="outline"
                                            @click="approveInvoice(apInvoice)"
                                        >
                                            Approve
                                        </Button>
                                        <Button
                                            v-if="canApprove(apInvoice)"
                                            size="sm"
                                            variant="destructive"
                                            @click="rejectInvoice(apInvoice)"
                                        >
                                            Reject
                                        </Button>
                                        <Button
                                            v-if="canRecordPayment(apInvoice)"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="`/purchase/ap/invoices/${apInvoice.id}/record-payment`">Record Payment</Link>
                                        </Button>
                                        <Button
                                            v-if="isDeletable(apInvoice)"
                                            size="sm"
                                            variant="destructive"
                                            @click="deleteInvoice(apInvoice)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
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
