<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

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
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'Manual Journal', href: '/accounting/manual-journal' },
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
                title="Manual Journal"
                description="Form CRUD untuk membuat jurnal umum secara manual."
            />

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="entry-number">Entry Number</Label>
                        <Input id="entry-number" v-model="form.entry_number" placeholder="JE-2026-0005" />
                        <InputError :message="form.errors.entry_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fiscal-period">Fiscal Period</Label>
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
                        <Label for="entry-date">Tanggal</Label>
                        <Input id="entry-date" v-model="form.entry_date" type="date" />
                        <InputError :message="form.errors.entry_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        >
                            <option value="draft">Draft</option>
                            <option value="posted">Posted</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="description">Description</Label>
                        <Input id="description" v-model="form.description" placeholder="Contoh: Penyesuaian biaya utilitas" />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="md:col-span-2 space-y-4 rounded-lg border border-sidebar-border/70 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold">Journal Lines</h3>
                            <Button type="button" variant="outline" @click="addLine">Add Line</Button>
                        </div>

                        <div v-for="(line, index) in form.lines" :key="index" class="grid gap-3 rounded-md border border-sidebar-border/60 p-3 md:grid-cols-4">
                            <div class="grid gap-2">
                                <Label :for="`line-account-${index}`">Akun</Label>
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
                                <Label :for="`line-type-${index}`">Type</Label>
                                <select
                                    :id="`line-type-${index}`"
                                    v-model="line.line_type"
                                    class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                >
                                    <option value="debit">Debit</option>
                                    <option value="credit">Credit</option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`line-amount-${index}`">Amount</Label>
                                <Input :id="`line-amount-${index}`" v-model="line.amount" type="number" min="0" step="0.01" />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`line-description-${index}`">Description</Label>
                                <div class="flex gap-2">
                                    <Input :id="`line-description-${index}`" v-model="line.description" placeholder="Opsional" />
                                    <Button type="button" variant="ghost" @click="removeLine(index)">Remove</Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/40 p-4 text-sm">
                        <p>Debit total: {{ debitTotal.toLocaleString('id-ID') }} | Credit total: {{ creditTotal.toLocaleString('id-ID') }}</p>
                        <Button type="submit" :disabled="form.processing">Save Journal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
