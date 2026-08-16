<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

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
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.journal_lines'), href: '/accounting/journal-lines' },
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
    if (!window.confirm(t('accounting.journal_lines.delete_confirm'))) {
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
                :title="t('accounting.journal_lines.heading_title')"
                :description="t('accounting.journal_lines.heading_description')"
            />

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="entry">{{ t('accounting.journal_lines.journal_entry_label') }}</Label>
                        <select id="entry" v-model.number="form.journal_entry_id" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option v-for="entry in entries" :key="entry.id" :value="entry.id">
                                {{ entry.entry_number }}
                            </option>
                        </select>
                        <InputError :message="form.errors.journal_entry_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="account">{{ t('accounting.journal_lines.account_label') }}</Label>
                        <select id="account" v-model.number="form.chart_of_account_id" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.chart_of_account_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="type">{{ t('accounting.journal_lines.type_label') }}</Label>
                        <select id="type" v-model="form.line_type" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option value="debit">{{ t('accounting.journal_form.debit_option') }}</option>
                            <option value="credit">{{ t('accounting.journal_form.credit_option') }}</option>
                        </select>
                        <InputError :message="form.errors.line_type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="amount">{{ t('accounting.journal_lines.amount_label') }}</Label>
                        <Input id="amount" v-model="form.amount" type="number" min="0" step="0.01" />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="description">{{ t('accounting.journal_lines.description_label') }}</Label>
                        <Input id="description" v-model="form.description" :placeholder="t('accounting.journal_lines.description_placeholder')" />
                    </div>

                    <div class="md:col-span-2 flex items-center gap-3">
                        <Button type="submit" :disabled="form.processing">
                            {{ editingId ? t('accounting.journal_lines.update_button') : t('accounting.journal_lines.save_button') }}
                        </Button>
                        <Button type="button" variant="outline" @click="resetForm">{{ t('common.reset') }}</Button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
                    <Input v-model="searchForm.search" :placeholder="t('accounting.journal_lines.search_placeholder')" class="max-w-sm" />
                    <Button type="submit" variant="outline">{{ t('common.search') }}</Button>
                </form>

                <div class="overflow-hidden rounded-lg border border-sidebar-border/60">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.journal_lines.table_entry') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.journal_lines.table_account') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.journal_lines.table_type') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.journal_lines.table_amount') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.journal_lines.table_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in lines" :key="line.id" class="border-t border-sidebar-border/60">
                                <td class="px-4 py-3 font-mono">{{ line.journal_entry_number }}</td>
                                <td class="px-4 py-3">{{ line.chart_of_account_code }} - {{ line.chart_of_account_name }}</td>
                                <td class="px-4 py-3">{{ line.line_type }}</td>
                                <td class="px-4 py-3">{{ line.amount.toLocaleString('id-ID') }}</td>
                                <td class="px-4 py-3 space-x-2">
                                    <Button type="button" size="sm" variant="outline" @click="editLine(line)">{{ t('common.edit') }}</Button>
                                    <Button type="button" size="sm" variant="destructive" @click="deleteLine(line)">{{ t('common.delete') }}</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
