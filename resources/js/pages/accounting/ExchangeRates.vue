<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

type FlashProps = {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

const page = usePage<FlashProps>();

type CurrencyOption = {
    code: string;
    name: string;
};

type RateRow = {
    id: number;
    currency_code: string;
    rate_to_base: string;
    rate_date: string;
    created_by: string | null;
};

type Props = {
    baseCurrencyCode: string;
    rates: RateRow[];
    currencies: CurrencyOption[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.exchange_rates'), href: '/accounting/exchange-rates' },
];

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    currency_code: props.currencies[0]?.code ?? '',
    rate_to_base: '',
    rate_date: today,
});

const submit = () => {
    form.post('/accounting/exchange-rates', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('rate_to_base');
        },
    });
};

const fetchForm = useForm({});

const fetchLatest = () => {
    fetchForm.post('/accounting/exchange-rates/fetch-latest', {
        preserveScroll: true,
    });
};

const deleteRate = (rate: RateRow) => {
    if (!window.confirm(t('accounting.exchange_rates.delete_confirm', { currency: rate.currency_code, date: rate.rate_date }))) {
        return;
    }

    useForm({}).delete(`/accounting/exchange-rates/${rate.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Exchange Rates" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    :title="t('accounting.exchange_rates.heading_title')"
                    :description="t('accounting.exchange_rates.heading_description', { base: props.baseCurrencyCode })"
                />
                <Button variant="outline" :disabled="fetchForm.processing" @click="fetchLatest">
                    {{ fetchForm.processing ? t('accounting.exchange_rates.fetching') : t('accounting.exchange_rates.fetch_latest_button') }}
                </Button>
            </div>

            <p class="-mt-4 text-xs text-muted-foreground">{{ t('accounting.exchange_rates.fetch_latest_hint') }}</p>

            <div v-if="page.props.flash?.success" class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ page.props.flash.error }}
            </div>

            <div v-if="!props.currencies.length" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                {{ t('accounting.exchange_rates.no_other_currencies') }}
            </div>

            <div v-else class="rounded-lg border border-sidebar-border/70 p-4">
                <form class="grid gap-4 md:grid-cols-3" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="currency_code">{{ t('accounting.exchange_rates.currency_label') }}</Label>
                        <select
                            id="currency_code"
                            v-model="form.currency_code"
                            class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        >
                            <option v-for="currency in props.currencies" :key="currency.code" :value="currency.code">
                                {{ currency.code }} - {{ currency.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.currency_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="rate_to_base">{{ t('accounting.exchange_rates.rate_label', { base: props.baseCurrencyCode }) }}</Label>
                        <Input id="rate_to_base" v-model="form.rate_to_base" type="number" min="0.000001" step="any" placeholder="15000" />
                        <InputError :message="form.errors.rate_to_base" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="rate_date">{{ t('accounting.exchange_rates.date_label') }}</Label>
                        <Input id="rate_date" v-model="form.rate_date" type="date" />
                        <InputError :message="form.errors.rate_date" />
                    </div>

                    <div class="md:col-span-3">
                        <Button type="submit" :disabled="form.processing">{{ t('accounting.exchange_rates.save_button') }}</Button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                            <th class="py-2 pr-3">{{ t('accounting.exchange_rates.table_currency') }}</th>
                            <th class="py-2 pr-3">{{ t('accounting.exchange_rates.table_rate', { base: props.baseCurrencyCode }) }}</th>
                            <th class="py-2 pr-3">{{ t('accounting.exchange_rates.table_date') }}</th>
                            <th class="py-2 pr-3">{{ t('accounting.exchange_rates.table_added_by') }}</th>
                            <th class="py-2 pr-3 text-right">{{ t('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="props.rates.length === 0">
                            <td colspan="5" class="py-6 text-center text-muted-foreground">{{ t('accounting.exchange_rates.no_data_row') }}</td>
                        </tr>
                        <tr v-for="rate in props.rates" :key="rate.id" class="border-b border-sidebar-border/40 last:border-0">
                            <td class="py-2 pr-3 font-mono font-medium">{{ rate.currency_code }}</td>
                            <td class="py-2 pr-3 font-mono">{{ Number(rate.rate_to_base).toLocaleString() }}</td>
                            <td class="py-2 pr-3">{{ rate.rate_date }}</td>
                            <td class="py-2 pr-3 text-muted-foreground">{{ rate.created_by ?? '-' }}</td>
                            <td class="py-2 pr-3 text-right">
                                <Button size="sm" variant="destructive" @click="deleteRate(rate)">{{ t('common.delete') }}</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
