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
};

type AccountOption = {
    id: number;
    code: string;
    name: string;
};

type JournalLine = {
    id: number;
    chart_of_account_id: number;
    chart_of_account_code: string | null;
    chart_of_account_name: string | null;
    line_type: 'debit' | 'credit';
    amount: number;
    description: string | null;
};

type JournalEntry = {
    id: number;
    entry_number: string;
    entry_date: string;
    description: string | null;
    status: 'draft' | 'posted';
    fiscal_period_id: number;
    fiscal_period_code: string | null;
    lines: JournalLine[];
};

type Props = {
    entries: JournalEntry[];
    fiscalPeriods: FiscalPeriodOption[];
    accounts: AccountOption[];
    filters: { search: string };
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

const props = defineProps<Props>();
const editingId = ref<number | null>(null);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'Journal Entries', href: '/accounting/journal-entries' },
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

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const debitTotal = computed(() => {
    return form.lines.filter((line) => line.line_type === 'debit').reduce((total, line) => total + (Number(line.amount) || 0), 0);
});

const creditTotal = computed(() => {
    return form.lines.filter((line) => line.line_type === 'credit').reduce((total, line) => total + (Number(line.amount) || 0), 0);
});

const submitSearch = () => {
    searchForm.get('/accounting/journal-entries', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.fiscal_period_id = props.fiscalPeriods[0]?.id ?? 0;
    form.status = 'draft';
    form.lines = [createLine(), createLine()];
    form.clearErrors();
};

const editEntry = (entry: JournalEntry) => {
    editingId.value = entry.id;
    form.entry_number = entry.entry_number;
    form.fiscal_period_id = entry.fiscal_period_id;
    form.entry_date = entry.entry_date;
    form.description = entry.description ?? '';
    form.status = entry.status;
    form.lines = entry.lines.length
        ? entry.lines.map((line) => ({
              chart_of_account_id: line.chart_of_account_id,
              line_type: line.line_type,
              amount: String(line.amount),
              description: line.description ?? '',
          }))
        : [createLine(), createLine()];
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(`/accounting/manual-journal/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post('/accounting/manual-journal', {
        preserveScroll: true,
        onSuccess: resetForm,
    });
};

const addLine = () => {
    form.lines.push(createLine());
};

const removeLine = (index: number) => {
    if (form.lines.length <= 2) {
        return;
    }

    form.lines.splice(index, 1);
};

const deleteEntry = (entry: JournalEntry) => {
    if (!window.confirm(`Hapus journal entry ${entry.entry_number}?`)) {
        return;
    }

    useForm({}).delete(`/accounting/manual-journal/${entry.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Accounting - Journal Entries" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                title="Journal Entries"
                description="CRUD untuk header jurnal, lengkap dengan detail line debit dan credit."
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
                        <select id="fiscal-period" v-model.number="form.fiscal_period_id" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option v-for="period in fiscalPeriods" :key="period.id" :value="period.id">
                                {{ period.code }}
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
                        <select id="status" v-model="form.status" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
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
                                <Label :for="`journal-account-${index}`">Akun</Label>
                                <select :id="`journal-account-${index}`" v-model.number="line.chart_of_account_id" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`journal-type-${index}`">Type</Label>
                                <select :id="`journal-type-${index}`" v-model="line.line_type" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                                    <option value="debit">Debit</option>
                                    <option value="credit">Credit</option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`journal-amount-${index}`">Amount</Label>
                                <Input :id="`journal-amount-${index}`" v-model="line.amount" type="number" min="0" step="0.01" />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`journal-line-description-${index}`">Description</Label>
                                <div class="flex gap-2">
                                    <Input :id="`journal-line-description-${index}`" v-model="line.description" placeholder="Opsional" />
                                    <Button type="button" variant="ghost" @click="removeLine(index)">Remove</Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/40 p-4 text-sm">
                        <p>Debit total: {{ debitTotal.toLocaleString('id-ID') }} | Credit total: {{ creditTotal.toLocaleString('id-ID') }}</p>
                        <div class="flex gap-2">
                            <Button type="button" variant="outline" @click="resetForm">Reset</Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ editingId ? 'Update Journal' : 'Save Journal' }}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
                    <Input v-model="searchForm.search" placeholder="Search journal entry..." class="max-w-sm" />
                    <Button type="submit" variant="outline">Search</Button>
                </form>

                <div class="overflow-hidden rounded-lg border border-sidebar-border/60">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Entry Number</th>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Period</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in entries" :key="entry.id" class="border-t border-sidebar-border/60 align-top">
                                <td class="px-4 py-3 font-mono">{{ entry.entry_number }}</td>
                                <td class="px-4 py-3">{{ entry.entry_date }}</td>
                                <td class="px-4 py-3">{{ entry.fiscal_period_code }}</td>
                                <td class="px-4 py-3">{{ entry.status }}</td>
                                <td class="px-4 py-3 space-x-2">
                                    <Button type="button" size="sm" variant="outline" @click="editEntry(entry)">Edit</Button>
                                    <Button type="button" size="sm" variant="destructive" @click="deleteEntry(entry)">Delete</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
