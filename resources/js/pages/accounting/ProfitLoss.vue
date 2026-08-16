<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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
    total_revenue: string;
    total_expense: string;
    net_income: string;
};

type Props = {
    dateFrom: string;
    dateTo: string;
    revenue: AccountBalance[];
    expense: AccountBalance[];
    totals: Totals;
    unclassifiedCount: number;
};

const props = defineProps<Props>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.profit_loss'), href: '/accounting/profit-loss' },
];

const filterForm = useForm({
    date_from: props.dateFrom,
    date_to: props.dateTo,
});

const submitFilter = () => {
    filterForm.get('/accounting/profit-loss', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const isNetIncomePositive = (): boolean => Number(props.totals.net_income) >= 0;
</script>

<template>
    <Head title="Profit & Loss" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
            <Heading
                :title="t('accounting.profit_loss.heading_title')"
                :description="t('accounting.profit_loss.heading_description')"
            />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitFilter">
                    <div class="grid gap-1">
                        <Label for="date_from" class="text-xs text-muted-foreground">{{ t('accounting.profit_loss.from_label') }}</Label>
                        <Input id="date_from" v-model="filterForm.date_from" type="date" class="w-44" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="date_to" class="text-xs text-muted-foreground">{{ t('accounting.profit_loss.to_label') }}</Label>
                        <Input id="date_to" v-model="filterForm.date_to" type="date" class="w-44" />
                    </div>
                    <Button type="submit" variant="outline">{{ t('common.apply') }}</Button>
                </form>
            </div>

            <div
                v-if="props.unclassifiedCount > 0"
                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700"
            >
                {{ t('accounting.unclassified_warning', { count: props.unclassifiedCount }) }}
                <a href="/accounting/chart-of-accounts" class="underline">{{ t('nav.chart_of_accounts') }}</a>.
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">{{ t('accounting.profit_loss.revenue_heading') }}</h3>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-if="props.revenue.length === 0">
                            <td colspan="2" class="py-3 text-center text-muted-foreground">{{ t('accounting.profit_loss.no_revenue_accounts') }}</td>
                        </tr>
                        <tr v-for="account in props.revenue" :key="account.id" class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">{{ account.code }} - {{ account.name }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(account.balance).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-sidebar-border/70 font-semibold">
                            <td class="py-2 pr-3">{{ t('accounting.profit_loss.total_revenue_label') }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.totals.total_revenue).toLocaleString() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">{{ t('accounting.profit_loss.expense_heading') }}</h3>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-if="props.expense.length === 0">
                            <td colspan="2" class="py-3 text-center text-muted-foreground">{{ t('accounting.profit_loss.no_expense_accounts') }}</td>
                        </tr>
                        <tr v-for="account in props.expense" :key="account.id" class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">{{ account.code }} - {{ account.name }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(account.balance).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-sidebar-border/70 font-semibold">
                            <td class="py-2 pr-3">{{ t('accounting.profit_loss.total_expense_label') }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.totals.total_expense).toLocaleString() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div
                class="rounded-lg border p-4"
                :class="isNetIncomePositive() ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'"
            >
                <div class="flex items-center justify-between text-sm font-semibold" :class="isNetIncomePositive() ? 'text-green-700' : 'text-red-700'">
                    <span>{{ t('accounting.profit_loss.net_income_label') }}</span>
                    <span class="font-mono">{{ Number(props.totals.net_income).toLocaleString() }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
