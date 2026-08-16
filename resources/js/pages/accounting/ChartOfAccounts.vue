<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Account = {
    id: number;
    code: string;
    name: string;
    category: string;
    account_type: string | null;
    status: string;
};

type ImportResult = {
    created: number;
    updated: number;
    errors: string[];
    errorCount: number;
};

type Props = {
    accounts: Account[];
    typeLabels: Record<string, string>;
    filters: { search: string };
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    importResult?: ImportResult | null;
};

const props = defineProps<Props>();
const { t } = useI18n();
const editingId = ref<number | null>(null);
const accountDialogOpen = ref(false);
const importFileInput = ref<HTMLInputElement | null>(null);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.chart_of_accounts'), href: '/accounting/chart-of-accounts' },
];

const form = useForm({
    code: '',
    name: '',
    category: '',
    account_type: '',
    status: 'active',
    search: props.filters.search ?? '',
});

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const submitSearch = () => {
    searchForm.get('/accounting/chart-of-accounts', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const exportUrl = computed(() => {
    const search = searchForm.search.trim();

    return search
        ? `/accounting/chart-of-accounts/export?search=${encodeURIComponent(search)}`
        : '/accounting/chart-of-accounts/export';
});

const importForm = useForm<{ file: File | null }>({
    file: null,
});

const onImportFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    importForm.file = target.files?.[0] ?? null;
};

const submitImport = () => {
    if (!importForm.file) {
        return;
    }

    importForm.post('/accounting/chart-of-accounts/import', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();

            if (importFileInput.value) {
                importFileInput.value.value = '';
            }
        },
    });
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.status = 'active';
    form.clearErrors();
};

const closeAccountDialog = () => {
    accountDialogOpen.value = false;
    resetForm();
};

const openCreateDialog = () => {
    resetForm();
    accountDialogOpen.value = true;
};

const editAccount = (account: Account) => {
    editingId.value = account.id;
    form.code = account.code;
    form.name = account.name;
    form.category = account.category;
    form.account_type = account.account_type ?? '';
    form.status = account.status;
    form.clearErrors();
    accountDialogOpen.value = true;
};

const submit = () => {
    if (editingId.value) {
        form.put(`/accounting/chart-of-accounts/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: closeAccountDialog,
        });

        return;
    }

    form.post('/accounting/chart-of-accounts', {
        preserveScroll: true,
        onSuccess: closeAccountDialog,
    });
};

const deleteAccount = (account: Account) => {
    if (!window.confirm(t('accounting.chart_of_accounts.delete_confirm', { code: account.code, name: account.name }))) {
        return;
    }

    useForm({}).delete(`/accounting/chart-of-accounts/${account.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Accounting - Chart of Accounts" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    :title="t('accounting.chart_of_accounts.heading_title')"
                    :description="t('accounting.chart_of_accounts.heading_description')"
                />
                <Button type="button" @click="openCreateDialog"
                    >{{ t('accounting.chart_of_accounts.add_account_button') }}</Button
                >
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-medium">{{ t('accounting.chart_of_accounts.import_export_heading') }}</h3>
                        <p class="text-sm text-muted-foreground">
                            {{ t('accounting.chart_of_accounts.import_export_desc') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            as="a"
                            href="/accounting/chart-of-accounts/import-template"
                            variant="outline"
                            size="sm"
                        >
                            {{ t('common.download_template') }}
                        </Button>
                        <Button
                            as="a"
                            :href="exportUrl"
                            variant="outline"
                            size="sm"
                            >{{ t('common.export_excel') }}</Button
                        >

                        <input
                            ref="importFileInput"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="hidden"
                            @change="onImportFileChange"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="importFileInput?.click()"
                        >
                            {{
                                importForm.file
                                    ? importForm.file.name
                                    : t('common.choose_file')
                            }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :disabled="
                                !importForm.file || importForm.processing
                            "
                            @click="submitImport"
                        >
                            {{
                                importForm.processing
                                    ? t('common.importing')
                                    : t('common.import')
                            }}
                        </Button>
                    </div>
                </div>

                <InputError :message="importForm.errors.file" />

                <div
                    v-if="importResult"
                    class="mt-4 rounded-md border border-sidebar-border/60 bg-muted/30 p-3 text-sm"
                >
                    <p>
                        {{ t('accounting.chart_of_accounts.import_result_summary', { created: importResult.created, updated: importResult.updated }) }}
                    </p>
                    <div v-if="importResult.errors.length" class="mt-2">
                        <p class="font-medium text-destructive">
                            {{ t('accounting.chart_of_accounts.import_errors_summary', { count: importResult.errorCount }) }}{{
                                importResult.errorCount >
                                importResult.errors.length
                                    ? t('accounting.chart_of_accounts.import_errors_shown', { shown: importResult.errors.length })
                                    : ''
                            }}:
                        </p>
                        <ul
                            class="mt-1 list-disc space-y-0.5 pl-5 text-muted-foreground"
                        >
                            <li
                                v-for="(message, index) in importResult.errors"
                                :key="index"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
                    <Input
                        v-model="searchForm.search"
                        :placeholder="t('accounting.chart_of_accounts.search_placeholder')"
                        class="max-w-sm"
                    />
                    <Button type="submit" variant="outline">{{ t('common.search') }}</Button>
                </form>

                <div
                    class="overflow-hidden rounded-lg border border-sidebar-border/60"
                >
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.chart_of_accounts.table_code') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.chart_of_accounts.table_name') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.chart_of_accounts.table_type') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.chart_of_accounts.table_category') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.chart_of_accounts.table_status') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('accounting.chart_of_accounts.table_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="account in accounts"
                                :key="account.id"
                                class="border-t border-sidebar-border/60"
                            >
                                <td class="px-4 py-3 font-mono">
                                    {{ account.code }}
                                </td>
                                <td class="px-4 py-3">{{ account.name }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="account.account_type" class="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                        {{ props.typeLabels[account.account_type] ?? account.account_type }}
                                    </span>
                                    <span v-else class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        {{ t('accounting.chart_of_accounts.unclassified_badge') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ account.category }}
                                </td>
                                <td class="px-4 py-3">{{ account.status }}</td>
                                <td class="space-x-2 px-4 py-3">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="editAccount(account)"
                                        >{{ t('common.edit') }}</Button
                                    >
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        @click="deleteAccount(account)"
                                        >{{ t('common.delete') }}</Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                :open="accountDialogOpen"
                @update:open="
                    (open) =>
                        open ? (accountDialogOpen = true) : closeAccountDialog()
                "
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{
                            editingId ? t('accounting.chart_of_accounts.dialog_edit_title') : t('accounting.chart_of_accounts.dialog_add_title')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                editingId
                                    ? t('accounting.chart_of_accounts.dialog_edit_desc')
                                    : t('accounting.chart_of_accounts.dialog_add_desc')
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        class="grid gap-4 md:grid-cols-2"
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-2">
                            <Label for="code">{{ t('accounting.chart_of_accounts.code_label') }}</Label>
                            <Input
                                id="code"
                                v-model="form.code"
                                placeholder="1000"
                            />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="name">{{ t('accounting.chart_of_accounts.name_label') }}</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Cash"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="account_type">{{ t('accounting.chart_of_accounts.type_label') }}</Label>
                            <select
                                id="account_type"
                                v-model="form.account_type"
                                class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            >
                                <option value="" disabled>{{ t('accounting.chart_of_accounts.select_type_option') }}</option>
                                <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError :message="form.errors.account_type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="category">{{ t('accounting.chart_of_accounts.category_label') }}</Label>
                            <Input
                                id="category"
                                v-model="form.category"
                                placeholder="Current Asset"
                            />
                            <InputError :message="form.errors.category" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="status">{{ t('accounting.chart_of_accounts.status_label') }}</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            >
                                <option value="active">{{ t('accounting.chart_of_accounts.status_active') }}</option>
                                <option value="inactive">{{ t('accounting.chart_of_accounts.status_inactive') }}</option>
                            </select>
                        </div>

                        <DialogFooter class="md:col-span-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="closeAccountDialog"
                                >{{ t('common.cancel') }}</Button
                            >
                            <Button type="submit" :disabled="form.processing">
                                {{
                                    editingId
                                        ? t('accounting.chart_of_accounts.update_button')
                                        : t('accounting.chart_of_accounts.save_button')
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
