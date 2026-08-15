<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type FiscalPeriodOption = {
    id: number;
    code: string;
};

type AccountOption = {
    id: number;
    code: string;
    name: string;
};

type ReportLine = {
    id: number;
    chart_of_account_code: string | null;
    chart_of_account_name: string | null;
    line_type: 'debit' | 'credit';
    amount: number;
    description: string | null;
};

type ReportEntry = {
    id: number;
    entry_number: string;
    entry_date: string | null;
    description: string | null;
    status: 'draft' | 'posted';
    fiscal_period_code: string | null;
    lines: ReportLine[];
};

type Filters = {
    date_from: string | null;
    date_to: string | null;
    fiscal_period_id: number | null;
    status: 'draft' | 'posted' | null;
    account_id: number | null;
    search: string;
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
    entries: ReportEntry[];
    fiscalPeriods: FiscalPeriodOption[];
    accounts: AccountOption[];
    filters: Filters;
    totals: { debit_total: number; credit_total: number };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'Report Jurnal', href: '/accounting/journal-report' },
];

const filterForm = useForm({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    fiscal_period_id: props.filters.fiscal_period_id ?? '',
    status: props.filters.status ?? '',
    account_id: props.filters.account_id ?? '',
    search: props.filters.search ?? '',
});

const submitFilters = () => {
    filterForm.get('/accounting/journal-report', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.reset();
    filterForm.get('/accounting/journal-report', {
        preserveScroll: true,
        replace: true,
    });
};

const buildQuery = (params: Record<string, string | number | null | undefined>) => {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, String(value));
        }
    });
    const qs = query.toString();
    return qs ? `?${qs}` : '';
};

const currentFilterParams = computed(() => ({
    date_from: filterForm.date_from,
    date_to: filterForm.date_to,
    fiscal_period_id: filterForm.fiscal_period_id,
    status: filterForm.status,
    account_id: filterForm.account_id,
    search: filterForm.search,
}));

const exportUrl = computed(() => `/accounting/journal-report/export${buildQuery(currentFilterParams.value)}`);
const pdfUrl = computed(() => `/accounting/journal-report/pdf${buildQuery(currentFilterParams.value)}`);

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'Tidak ada data';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} entries`;
});

const formatAmount = (value: number) => value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Accounting - Report Jurnal" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    title="Report Jurnal"
                    description="Laporan jurnal umum kronologis dengan filter tanggal, periode, status, dan akun."
                />
                <div class="flex flex-wrap items-center gap-2">
                    <Button as="a" :href="exportUrl" variant="outline" size="sm">Export Excel</Button>
                    <Button as="a" :href="pdfUrl" variant="outline" size="sm" target="_blank">Print / PDF</Button>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="grid gap-4 md:grid-cols-3 lg:grid-cols-6" @submit.prevent="submitFilters">
                    <div class="grid gap-2">
                        <Label for="date-from">Dari Tanggal</Label>
                        <Input id="date-from" v-model="filterForm.date_from" type="date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="date-to">Sampai Tanggal</Label>
                        <Input id="date-to" v-model="filterForm.date_to" type="date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fiscal-period">Fiscal Period</Label>
                        <select id="fiscal-period" v-model="filterForm.fiscal_period_id" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option value="">Semua Periode</option>
                            <option v-for="period in fiscalPeriods" :key="period.id" :value="period.id">
                                {{ period.code }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select id="status" v-model="filterForm.status" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option value="">Semua Status</option>
                            <option value="draft">Draft</option>
                            <option value="posted">Posted</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="account">Akun</Label>
                        <select id="account" v-model="filterForm.account_id" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option value="">Semua Akun</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="search">Cari</Label>
                        <Input id="search" v-model="filterForm.search" placeholder="Entry number / deskripsi" />
                    </div>

                    <div class="md:col-span-3 lg:col-span-6 flex gap-2">
                        <Button type="submit">Filter</Button>
                        <Button type="button" variant="outline" @click="resetFilters">Reset</Button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <div class="overflow-x-auto rounded-lg border border-sidebar-border/60">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Entry</th>
                                <th class="px-4 py-3 font-medium">Akun</th>
                                <th class="px-4 py-3 font-medium">Deskripsi</th>
                                <th class="px-4 py-3 text-right font-medium">Debit</th>
                                <th class="px-4 py-3 text-right font-medium">Credit</th>
                            </tr>
                        </thead>
                        <template v-for="entry in entries" :key="entry.id">
                            <tbody class="border-t border-sidebar-border/60">
                                <tr class="bg-muted/30 font-medium">
                                    <td colspan="5" class="px-4 py-2">
                                        {{ entry.entry_date }} &middot; {{ entry.entry_number }}
                                        <span v-if="entry.fiscal_period_code">&middot; {{ entry.fiscal_period_code }}</span>
                                        &middot; <span class="capitalize">{{ entry.status }}</span>
                                        <span v-if="entry.description"> &mdash; {{ entry.description }}</span>
                                    </td>
                                </tr>
                                <tr v-for="line in entry.lines" :key="line.id" class="align-top">
                                    <td class="px-4 py-2"></td>
                                    <td class="px-4 py-2">{{ line.chart_of_account_code }} - {{ line.chart_of_account_name }}</td>
                                    <td class="px-4 py-2">{{ line.description ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        {{ line.line_type === 'debit' ? formatAmount(line.amount) : '' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        {{ line.line_type === 'credit' ? formatAmount(line.amount) : '' }}
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                        <tbody v-if="!entries.length">
                            <tr>
                                <td colspan="5" class="py-6 text-center text-muted-foreground">Tidak ada jurnal yang cocok dengan filter.</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-sidebar-border font-semibold">
                                <td colspan="3" class="px-4 py-3 text-right">Grand Total</td>
                                <td class="px-4 py-3 text-right font-mono">{{ formatAmount(totals.debit_total) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ formatAmount(totals.credit_total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-sidebar-border/70 pt-4">
                    <p class="text-sm text-muted-foreground">{{ paginationText }}</p>
                    <div class="flex items-center gap-2">
                        <Button v-if="pagination.prev_page_url" variant="outline" as-child>
                            <Link :href="pagination.prev_page_url">Previous</Link>
                        </Button>
                        <span class="text-sm text-muted-foreground">
                            Page {{ pagination.current_page }} / {{ pagination.last_page }}
                        </span>
                        <Button v-if="pagination.next_page_url" variant="outline" as-child>
                            <Link :href="pagination.next_page_url">Next</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
