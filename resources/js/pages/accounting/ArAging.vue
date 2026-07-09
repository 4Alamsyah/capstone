<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type AgingRow = {
    id: number;
    invoice_number: string;
    customer_name: string | null;
    invoice_date: string | null;
    due_date: string | null;
    currency_code: string;
    total_amount: string;
    amount_paid: string;
    balance_due: string;
    days_overdue: number;
    aging_bucket: string;
};

type Bucket = {
    rows: AgingRow[];
    total_balance: string;
};

type Props = {
    buckets: Record<string, Bucket>;
    grandTotal: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'AR Aging', href: '/accounting/ar-aging' },
];

const bucketOrder = ['Not Due', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'];
</script>

<template>
    <Head title="AR Aging" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <Heading
                title="AR Aging"
                description="Daftar invoice yang masih outstanding, dikelompokkan berdasarkan umur piutang."
            />

            <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Total Outstanding Receivable</span>
                    <span class="font-mono text-lg font-semibold">{{ Number(props.grandTotal).toLocaleString() }}</span>
                </div>
            </div>

            <div v-for="bucketName in bucketOrder" :key="bucketName" class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ bucketName }}</h3>
                    <span class="font-mono text-sm text-muted-foreground">
                        {{ Number(props.buckets[bucketName]?.total_balance ?? 0).toLocaleString() }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">Invoice Number</th>
                                <th class="py-2 pr-3">Customer</th>
                                <th class="py-2 pr-3">Invoice Date</th>
                                <th class="py-2 pr-3">Due Date</th>
                                <th class="py-2 pr-3">Total</th>
                                <th class="py-2 pr-3">Paid</th>
                                <th class="py-2 pr-3">Balance</th>
                                <th class="py-2 pr-3">Days Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!(props.buckets[bucketName]?.rows.length)">
                                <td colspan="8" class="py-4 text-center text-muted-foreground">No invoices in this bucket.</td>
                            </tr>
                            <tr
                                v-for="row in props.buckets[bucketName]?.rows"
                                :key="row.id"
                                class="border-b border-sidebar-border/40 last:border-0"
                            >
                                <td class="py-2 pr-3 font-mono font-medium">{{ row.invoice_number }}</td>
                                <td class="py-2 pr-3">{{ row.customer_name ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ row.invoice_date ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ row.due_date ?? '-' }}</td>
                                <td class="py-2 pr-3 font-mono">{{ Number(row.total_amount).toLocaleString() }} {{ row.currency_code }}</td>
                                <td class="py-2 pr-3 font-mono">{{ Number(row.amount_paid).toLocaleString() }}</td>
                                <td class="py-2 pr-3 font-mono font-semibold">{{ Number(row.balance_due).toLocaleString() }}</td>
                                <td class="py-2 pr-3">{{ row.days_overdue }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
