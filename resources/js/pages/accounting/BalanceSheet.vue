<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type AccountBalance = {
    id: number;
    code: string;
    name: string;
    balance: string;
};

type Totals = {
    total_assets: string;
    total_liabilities: string;
    total_equity: string;
    total_liabilities_and_equity: string;
    is_balanced: boolean;
};

type Props = {
    asOfDate: string;
    assets: AccountBalance[];
    liabilities: AccountBalance[];
    equity: AccountBalance[];
    currentYearEarnings: string;
    totals: Totals;
    unclassifiedCount: number;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'Balance Sheet', href: '/accounting/balance-sheet' },
];

const filterForm = useForm({
    as_of_date: props.asOfDate,
});

const submitFilter = () => {
    filterForm.get('/accounting/balance-sheet', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Balance Sheet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
            <Heading
                title="Balance Sheet"
                description="Posisi Aset, Liabilitas, dan Ekuitas perusahaan pada tanggal tertentu."
            />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitFilter">
                    <div class="grid gap-1">
                        <Label for="as_of_date" class="text-xs text-muted-foreground">As Of Date</Label>
                        <Input id="as_of_date" v-model="filterForm.as_of_date" type="date" class="w-48" />
                    </div>
                    <Button type="submit" variant="outline">Apply</Button>
                </form>
            </div>

            <div
                v-if="props.unclassifiedCount > 0"
                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700"
            >
                {{ props.unclassifiedCount }} akun di Chart of Accounts belum punya Type (Asset/Liability/Equity/Revenue/Expense) dan tidak ikut dihitung di laporan ini.
                Lengkapi di <a href="/accounting/chart-of-accounts" class="underline">Chart of Accounts</a>.
            </div>

            <div
                class="rounded-md border px-3 py-2 text-sm font-medium"
                :class="props.totals.is_balanced ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'"
            >
                {{ props.totals.is_balanced ? 'Balanced — Assets = Liabilities + Equity.' : 'Out of Balance — periksa jurnal yang belum posted atau data yang belum lengkap.' }}
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">Assets</h3>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-if="props.assets.length === 0">
                            <td colspan="2" class="py-3 text-center text-muted-foreground">Tidak ada akun Asset.</td>
                        </tr>
                        <tr v-for="account in props.assets" :key="account.id" class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">{{ account.code }} - {{ account.name }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(account.balance).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-sidebar-border/70 font-semibold">
                            <td class="py-2 pr-3">Total Assets</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.totals.total_assets).toLocaleString() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">Liabilities</h3>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-if="props.liabilities.length === 0">
                            <td colspan="2" class="py-3 text-center text-muted-foreground">Tidak ada akun Liability.</td>
                        </tr>
                        <tr v-for="account in props.liabilities" :key="account.id" class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">{{ account.code }} - {{ account.name }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(account.balance).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-sidebar-border/70 font-semibold">
                            <td class="py-2 pr-3">Total Liabilities</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.totals.total_liabilities).toLocaleString() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">Equity</h3>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="account in props.equity" :key="account.id" class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">{{ account.code }} - {{ account.name }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(account.balance).toLocaleString() }}</td>
                        </tr>
                        <tr class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">Current Year Earnings (Revenue - Expense)</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.currentYearEarnings).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-sidebar-border/70 font-semibold">
                            <td class="py-2 pr-3">Total Equity</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.totals.total_equity).toLocaleString() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4">
                <div class="flex items-center justify-between text-sm font-semibold">
                    <span>Total Liabilities + Equity</span>
                    <span class="font-mono">{{ Number(props.totals.total_liabilities_and_equity).toLocaleString() }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
