<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

type RevaluationRow = {
    type: 'ar' | 'ap';
    id: number;
    document_number: string;
    party_name: string | null;
    currency_code: string;
    outstanding_foreign: string;
    carrying_rate: string;
    new_rate: number;
    old_base_value: string;
    new_base_value: string;
    difference: string;
};

type Props = {
    revaluationDate: string;
    baseCurrencyCode: string;
    arLines: RevaluationRow[];
    apLines: RevaluationRow[];
    totalGainLoss: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.fx_revaluation'), href: '/accounting/fx-revaluation' },
];

const filterForm = useForm({
    revaluation_date: props.revaluationDate,
});

const submitFilter = () => {
    filterForm.get('/accounting/fx-revaluation', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const runForm = useForm({
    revaluation_date: props.revaluationDate,
});

const hasAdjustments = computed(() => {
    const rows = [...props.arLines, ...props.apLines];

    return rows.some((row) => Math.abs(Number(row.difference)) >= 0.01);
});

const runRevaluation = () => {
    if (!window.confirm(t('accounting.fx_revaluation.run_confirm'))) {
        return;
    }

    runForm.revaluation_date = filterForm.revaluation_date;
    runForm.post('/accounting/fx-revaluation', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="FX Revaluation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
            <Heading
                :title="t('accounting.fx_revaluation.heading_title')"
                :description="t('accounting.fx_revaluation.heading_description')"
            />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitFilter">
                    <div class="grid gap-1">
                        <Label for="revaluation_date" class="text-xs text-muted-foreground">{{ t('accounting.fx_revaluation.date_label') }}</Label>
                        <Input id="revaluation_date" v-model="filterForm.revaluation_date" type="date" class="w-48" />
                    </div>
                    <Button type="submit" variant="outline">{{ t('common.apply') }}</Button>
                </form>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">{{ t('accounting.fx_revaluation.ar_heading') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">{{ t('accounting.fx_revaluation.table_document') }}</th>
                                <th class="py-2 pr-3">{{ t('accounting.fx_revaluation.table_party_ar') }}</th>
                                <th class="py-2 pr-3">{{ t('accounting.fx_revaluation.table_currency') }}</th>
                                <th class="py-2 pr-3 text-right">{{ t('accounting.fx_revaluation.table_outstanding') }}</th>
                                <th class="py-2 pr-3 text-right">{{ t('accounting.fx_revaluation.table_carrying_rate') }}</th>
                                <th class="py-2 pr-3 text-right">{{ t('accounting.fx_revaluation.table_new_rate') }}</th>
                                <th class="py-2 pr-3 text-right">{{ t('accounting.fx_revaluation.table_difference') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.arLines.length === 0">
                                <td colspan="7" class="py-4 text-center text-muted-foreground">{{ t('accounting.fx_revaluation.no_data_row') }}</td>
                            </tr>
                            <tr v-for="row in props.arLines" :key="`ar-${row.id}`" class="border-b border-sidebar-border/40 last:border-0">
                                <td class="py-2 pr-3 font-mono">{{ row.document_number }}</td>
                                <td class="py-2 pr-3">{{ row.party_name ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ row.currency_code }}</td>
                                <td class="py-2 pr-3 text-right font-mono">{{ Number(row.outstanding_foreign).toLocaleString() }}</td>
                                <td class="py-2 pr-3 text-right font-mono">{{ Number(row.carrying_rate).toLocaleString() }}</td>
                                <td class="py-2 pr-3 text-right font-mono">{{ Number(row.new_rate).toLocaleString() }}</td>
                                <td
                                    class="py-2 pr-3 text-right font-mono font-semibold"
                                    :class="Number(row.difference) > 0 ? 'text-green-700' : Number(row.difference) < 0 ? 'text-red-700' : ''"
                                >
                                    {{ Number(row.difference).toLocaleString() }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h3 class="mb-3 text-sm font-semibold">{{ t('accounting.fx_revaluation.ap_heading') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">{{ t('accounting.fx_revaluation.table_document') }}</th>
                                <th class="py-2 pr-3">{{ t('accounting.fx_revaluation.table_party_ap') }}</th>
                                <th class="py-2 pr-3">{{ t('accounting.fx_revaluation.table_currency') }}</th>
                                <th class="py-2 pr-3 text-right">{{ t('accounting.fx_revaluation.table_outstanding') }}</th>
                                <th class="py-2 pr-3 text-right">{{ t('accounting.fx_revaluation.table_carrying_rate') }}</th>
                                <th class="py-2 pr-3 text-right">{{ t('accounting.fx_revaluation.table_new_rate') }}</th>
                                <th class="py-2 pr-3 text-right">{{ t('accounting.fx_revaluation.table_difference') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.apLines.length === 0">
                                <td colspan="7" class="py-4 text-center text-muted-foreground">{{ t('accounting.fx_revaluation.no_data_row') }}</td>
                            </tr>
                            <tr v-for="row in props.apLines" :key="`ap-${row.id}`" class="border-b border-sidebar-border/40 last:border-0">
                                <td class="py-2 pr-3 font-mono">{{ row.document_number }}</td>
                                <td class="py-2 pr-3">{{ row.party_name ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ row.currency_code }}</td>
                                <td class="py-2 pr-3 text-right font-mono">{{ Number(row.outstanding_foreign).toLocaleString() }}</td>
                                <td class="py-2 pr-3 text-right font-mono">{{ Number(row.carrying_rate).toLocaleString() }}</td>
                                <td class="py-2 pr-3 text-right font-mono">{{ Number(row.new_rate).toLocaleString() }}</td>
                                <td
                                    class="py-2 pr-3 text-right font-mono font-semibold"
                                    :class="Number(row.difference) < 0 ? 'text-green-700' : Number(row.difference) > 0 ? 'text-red-700' : ''"
                                >
                                    {{ Number(row.difference).toLocaleString() }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <span class="text-sm text-muted-foreground">{{ t('accounting.fx_revaluation.total_label') }}</span>
                        <span
                            class="ml-2 font-mono text-lg font-semibold"
                            :class="Number(props.totalGainLoss) > 0 ? 'text-green-700' : Number(props.totalGainLoss) < 0 ? 'text-red-700' : ''"
                        >
                            {{ Number(props.totalGainLoss).toLocaleString() }} {{ props.baseCurrencyCode }}
                        </span>
                    </div>
                    <Button :disabled="!hasAdjustments || runForm.processing" @click="runRevaluation">
                        {{ t('accounting.fx_revaluation.run_button') }}
                    </Button>
                </div>
                <p v-if="!hasAdjustments" class="mt-2 text-xs text-muted-foreground">{{ t('accounting.fx_revaluation.no_adjustment_hint') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
