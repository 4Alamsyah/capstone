<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type EntryOption = { id: number; entry_number: string };
type AccountOption = { id: number; code: string; name: string };
type JournalLine = {
    id: number;
    journal_entry_id: number;
    journal_entry_number: string | null;
    chart_of_account_id: number;
    chart_of_account_code: string | null;
    chart_of_account_name: string | null;
    line_type: 'debit' | 'credit';
    amount: number;
    description: string | null;
};

type Props = {
    lines: JournalLine[];
    entries: EntryOption[];
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
    { title: 'Journal Lines', href: '/accounting/journal-lines' },
];

const form = useForm({
    journal_entry_id: props.entries[0]?.id ?? 0,
    chart_of_account_id: props.accounts[0]?.id ?? 0,
    line_type: 'debit' as 'debit' | 'credit',
    amount: '',
    description: '',
});

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const submitSearch = () => {
    searchForm.get('/accounting/journal-lines', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.journal_entry_id = props.entries[0]?.id ?? 0;
    form.chart_of_account_id = props.accounts[0]?.id ?? 0;
    form.line_type = 'debit';
    form.clearErrors();
};

const editLine = (line: JournalLine) => {
    editingId.value = line.id;
    form.journal_entry_id = line.journal_entry_id;
    form.chart_of_account_id = line.chart_of_account_id;
    form.line_type = line.line_type;
    form.amount = String(line.amount);
    form.description = line.description ?? '';
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(`/accounting/journal-lines/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post('/accounting/journal-lines', {
        preserveScroll: true,
        onSuccess: resetForm,
    });
};

const deleteLine = (line: JournalLine) => {
    if (!window.confirm('Hapus journal line ini?')) {
        return;
    }

    useForm({}).delete(`/accounting/journal-lines/${line.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Accounting - Journal Lines" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                title="Journal Lines"
                description="CRUD untuk detail debit dan kredit pada setiap journal entry."
            />

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="entry">Journal Entry</Label>
                        <select id="entry" v-model.number="form.journal_entry_id" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option v-for="entry in entries" :key="entry.id" :value="entry.id">
                                {{ entry.entry_number }}
                            </option>
                        </select>
                        <InputError :message="form.errors.journal_entry_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="account">Account</Label>
                        <select id="account" v-model.number="form.chart_of_account_id" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.chart_of_account_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="type">Type</Label>
                        <select id="type" v-model="form.line_type" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option value="debit">Debit</option>
                            <option value="credit">Credit</option>
                        </select>
                        <InputError :message="form.errors.line_type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="amount">Amount</Label>
                        <Input id="amount" v-model="form.amount" type="number" min="0" step="0.01" />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="description">Description</Label>
                        <Input id="description" v-model="form.description" placeholder="Opsional" />
                    </div>

                    <div class="md:col-span-2 flex items-center gap-3">
                        <Button type="submit" :disabled="form.processing">
                            {{ editingId ? 'Update Line' : 'Save Line' }}
                        </Button>
                        <Button type="button" variant="outline" @click="resetForm">Reset</Button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
                    <Input v-model="searchForm.search" placeholder="Search line..." class="max-w-sm" />
                    <Button type="submit" variant="outline">Search</Button>
                </form>

                <div class="overflow-hidden rounded-lg border border-sidebar-border/60">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Entry</th>
                                <th class="px-4 py-3 font-medium">Account</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">Amount</th>
                                <th class="px-4 py-3 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in lines" :key="line.id" class="border-t border-sidebar-border/60">
                                <td class="px-4 py-3 font-mono">{{ line.journal_entry_number }}</td>
                                <td class="px-4 py-3">{{ line.chart_of_account_code }} - {{ line.chart_of_account_name }}</td>
                                <td class="px-4 py-3">{{ line.line_type }}</td>
                                <td class="px-4 py-3">{{ line.amount.toLocaleString('id-ID') }}</td>
                                <td class="px-4 py-3 space-x-2">
                                    <Button type="button" size="sm" variant="outline" @click="editLine(line)">Edit</Button>
                                    <Button type="button" size="sm" variant="destructive" @click="deleteLine(line)">Delete</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
