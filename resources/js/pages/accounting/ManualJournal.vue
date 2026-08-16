<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

type FiscalPeriodOption = {
    id: number;
    code: string;
    status: string;
};

type AccountOption = {
    id: number;
    code: string;
    name: string;
    status: string;
};

type Props = {
    fiscalPeriods: FiscalPeriodOption[];
    accounts: AccountOption[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('accounting.general.manual_journal_title'), href: '/accounting/manual-journal' },
];

const createLine = () => ({
    chart_of_account_id: props.accounts[0]?.id ?? 0,
    line_type: 'debit' as 'debit' | 'credit',
    amount: '',
    description: '',
});

const form = useForm({
    entry_number: '',
    fiscal_period_id: props.fiscalPeriods[0]?.id ?? 0,
    entry_date: '',
    description: '',
    status: 'draft' as 'draft' | 'posted',
    lines: [createLine(), createLine()],
});

const addLine = () => {
    form.lines.push(createLine());
};

const removeLine = (index: number) => {
    if (form.lines.length <= 2) {
        return;
    }

    form.lines.splice(index, 1);
};

const debitTotal = computed(() => {
    return form.lines
        .filter((line) => line.line_type === 'debit')
        .reduce((total, line) => total + (Number(line.amount) || 0), 0);
});

const creditTotal = computed(() => {
    return form.lines
        .filter((line) => line.line_type === 'credit')
        .reduce((total, line) => total + (Number(line.amount) || 0), 0);
});

const submit = () => {
    form.post('/accounting/manual-journal', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.lines = [createLine(), createLine()];
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Accounting - Manual Journal" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                :title="t('accounting.manual_journal.heading_title')"
                :description="t('accounting.manual_journal.heading_description')"
            />

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="entry-number">{{ t('accounting.journal_form.entry_number_label') }}</Label>
                        <Input id="entry-number" v-model="form.entry_number" placeholder="JE-2026-0005" />
                        <InputError :message="form.errors.entry_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fiscal-period">{{ t('accounting.journal_form.fiscal_period_label') }}</Label>
                        <select
                            id="fiscal-period"
                            v-model.number="form.fiscal_period_id"
                            class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        >
                            <option v-for="period in fiscalPeriods" :key="period.id" :value="period.id">
                                {{ period.code }} ({{ period.status }})
                            </option>
                        </select>
                        <InputError :message="form.errors.fiscal_period_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="entry-date">{{ t('accounting.journal_form.entry_date_label') }}</Label>
                        <Input id="entry-date" v-model="form.entry_date" type="date" />
                        <InputError :message="form.errors.entry_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">{{ t('accounting.journal_form.status_label') }}</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        >
                            <option value="draft">{{ t('accounting.journal_form.status_draft') }}</option>
                            <option value="posted">{{ t('accounting.journal_form.status_posted') }}</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="description">{{ t('accounting.journal_form.description_label') }}</Label>
                        <Input id="description" v-model="form.description" :placeholder="t('accounting.journal_form.description_placeholder')" />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="md:col-span-2 space-y-4 rounded-lg border border-sidebar-border/70 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold">{{ t('accounting.journal_form.journal_lines_heading') }}</h3>
                            <Button type="button" variant="outline" @click="addLine">{{ t('accounting.journal_form.add_line_button') }}</Button>
                        </div>

                        <div v-for="(line, index) in form.lines" :key="index" class="grid gap-3 rounded-md border border-sidebar-border/60 p-3 md:grid-cols-4">
                            <div class="grid gap-2">
                                <Label :for="`line-account-${index}`">{{ t('accounting.journal_form.account_label') }}</Label>
                                <select
                                    :id="`line-account-${index}`"
                                    v-model.number="line.chart_of_account_id"
                                    class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                >
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`line-type-${index}`">{{ t('accounting.journal_form.type_label') }}</Label>
                                <select
                                    :id="`line-type-${index}`"
                                    v-model="line.line_type"
                                    class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                >
                                    <option value="debit">{{ t('accounting.journal_form.debit_option') }}</option>
                                    <option value="credit">{{ t('accounting.journal_form.credit_option') }}</option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`line-amount-${index}`">{{ t('accounting.journal_form.amount_label') }}</Label>
                                <Input :id="`line-amount-${index}`" v-model="line.amount" type="number" min="0" step="0.01" />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`line-description-${index}`">{{ t('accounting.journal_form.description_label') }}</Label>
                                <div class="flex gap-2">
                                    <Input :id="`line-description-${index}`" v-model="line.description" :placeholder="t('accounting.journal_form.line_description_placeholder')" />
                                    <Button type="button" variant="ghost" @click="removeLine(index)">{{ t('accounting.journal_form.remove_button') }}</Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/40 p-4 text-sm">
                        <p>{{ t('accounting.journal_form.debit_total_label') }}: {{ debitTotal.toLocaleString('id-ID') }} | {{ t('accounting.journal_form.credit_total_label') }}: {{ creditTotal.toLocaleString('id-ID') }}</p>
                        <Button type="submit" :disabled="form.processing">{{ t('accounting.journal_form.save_journal_button') }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
