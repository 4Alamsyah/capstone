<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
const editingId = ref<number | null>(null);
const accountDialogOpen = ref(false);
const importFileInput = ref<HTMLInputElement | null>(null);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'Chart of Accounts', href: '/accounting/chart-of-accounts' },
];

const form = useForm({
    code: '',
    name: '',
    category: '',
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
    if (!window.confirm(`Hapus akun ${account.code} - ${account.name}?`)) {
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
                    title="Chart of Accounts"
                    description="CRUD untuk daftar akun utama yang dipakai di jurnal dan laporan keuangan."
                />
                <Button type="button" @click="openCreateDialog"
                    >Add Account</Button
                >
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-medium">Import / Export</h3>
                        <p class="text-sm text-muted-foreground">
                            Download template, isi data akun, lalu import
                            kembali. Export mengikuti filter pencarian yang
                            aktif.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            as="a"
                            href="/accounting/chart-of-accounts/import-template"
                            variant="outline"
                            size="sm"
                        >
                            Download Template
                        </Button>
                        <Button
                            as="a"
                            :href="exportUrl"
                            variant="outline"
                            size="sm"
                            >Export Excel</Button
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
                                    : 'Pilih File'
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
                                    ? 'Mengimpor...'
                                    : 'Import'
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
                        Import selesai:
                        <strong>{{ importResult.created }}</strong> akun baru,
                        <strong>{{ importResult.updated }}</strong> diperbarui.
                    </p>
                    <div v-if="importResult.errors.length" class="mt-2">
                        <p class="font-medium text-destructive">
                            {{ importResult.errorCount }} baris dilewati{{
                                importResult.errorCount >
                                importResult.errors.length
                                    ? ` (menampilkan ${importResult.errors.length} pertama)`
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
                        placeholder="Search account..."
                        class="max-w-sm"
                    />
                    <Button type="submit" variant="outline">Search</Button>
                </form>

                <div
                    class="overflow-hidden rounded-lg border border-sidebar-border/60"
                >
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Action</th>
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
                                    {{ account.category }}
                                </td>
                                <td class="px-4 py-3">{{ account.status }}</td>
                                <td class="space-x-2 px-4 py-3">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="editAccount(account)"
                                        >Edit</Button
                                    >
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        @click="deleteAccount(account)"
                                        >Delete</Button
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
                            editingId ? 'Edit Account' : 'Add Account'
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                editingId
                                    ? 'Ubah data akun yang dipilih.'
                                    : 'Tambahkan akun baru ke Chart of Accounts.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        class="grid gap-4 md:grid-cols-2"
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input
                                id="code"
                                v-model="form.code"
                                placeholder="1000"
                            />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Cash"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="category">Category</Label>
                            <Input
                                id="category"
                                v-model="form.category"
                                placeholder="Asset"
                            />
                            <InputError :message="form.errors.category" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <DialogFooter class="md:col-span-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="closeAccountDialog"
                                >Cancel</Button
                            >
                            <Button type="submit" :disabled="form.processing">
                                {{
                                    editingId
                                        ? 'Update Account'
                                        : 'Save Account'
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
