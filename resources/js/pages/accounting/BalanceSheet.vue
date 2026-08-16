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
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.balance_sheet'), href: '/accounting/balance-sheet' },
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
                :title="t('accounting.balance_sheet.heading_title')"
                :description="t('accounting.balance_sheet.heading_description')"
            />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitFilter">
                    <div class="grid gap-1">
                        <Label for="as_of_date" class="text-xs text-muted-foreground">{{ t('accounting.balance_sheet.as_of_date_label') }}</Label>
                        <Input id="as_of_date" v-model="filterForm.as_of_date" type="date" class="w-48" />
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

            <div
                class="rounded-md border px-3 py-2 text-sm font-medium"
                :class="props.totals.is_balanced ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'"
            >
                {{ props.totals.is_balanced ? t('accounting.balance_sheet.balanced_message') : t('accounting.balance_sheet.out_of_balance_message') }}
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">{{ t('accounting.balance_sheet.assets_heading') }}</h3>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-if="props.assets.length === 0">
                            <td colspan="2" class="py-3 text-center text-muted-foreground">{{ t('accounting.balance_sheet.no_asset_accounts') }}</td>
                        </tr>
                        <tr v-for="account in props.assets" :key="account.id" class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">{{ account.code }} - {{ account.name }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(account.balance).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-sidebar-border/70 font-semibold">
                            <td class="py-2 pr-3">{{ t('accounting.balance_sheet.total_assets_label') }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.totals.total_assets).toLocaleString() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">{{ t('accounting.balance_sheet.liabilities_heading') }}</h3>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-if="props.liabilities.length === 0">
                            <td colspan="2" class="py-3 text-center text-muted-foreground">{{ t('accounting.balance_sheet.no_liability_accounts') }}</td>
                        </tr>
                        <tr v-for="account in props.liabilities" :key="account.id" class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">{{ account.code }} - {{ account.name }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(account.balance).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-sidebar-border/70 font-semibold">
                            <td class="py-2 pr-3">{{ t('accounting.balance_sheet.total_liabilities_label') }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.totals.total_liabilities).toLocaleString() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">{{ t('accounting.balance_sheet.equity_heading') }}</h3>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="account in props.equity" :key="account.id" class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">{{ account.code }} - {{ account.name }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(account.balance).toLocaleString() }}</td>
                        </tr>
                        <tr class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3">{{ t('accounting.balance_sheet.current_year_earnings_label') }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.currentYearEarnings).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-sidebar-border/70 font-semibold">
                            <td class="py-2 pr-3">{{ t('accounting.balance_sheet.total_equity_label') }}</td>
                            <td class="py-2 text-right font-mono">{{ Number(props.totals.total_equity).toLocaleString() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4">
                <div class="flex items-center justify-between text-sm font-semibold">
                    <span>{{ t('accounting.balance_sheet.total_liab_equity_label') }}</span>
                    <span class="font-mono">{{ Number(props.totals.total_liabilities_and_equity).toLocaleString() }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
