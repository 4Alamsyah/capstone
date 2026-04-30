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

type Account = {
    id: number;
    code: string;
    name: string;
    category: string;
    status: string;
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
};

const props = defineProps<Props>();
const editingId = ref<number | null>(null);

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

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.status = 'active';
    form.clearErrors();
};

const editAccount = (account: Account) => {
    editingId.value = account.id;
    form.code = account.code;
    form.name = account.name;
    form.category = account.category;
    form.status = account.status;
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(`/accounting/chart-of-accounts/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post('/accounting/chart-of-accounts', {
        preserveScroll: true,
        onSuccess: resetForm,
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
            <Heading
                title="Chart of Accounts"
                description="CRUD untuk daftar akun utama yang dipakai di jurnal dan laporan keuangan."
            />

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="code">Code</Label>
                        <Input id="code" v-model="form.code" placeholder="1000" />
                        <InputError :message="form.errors.code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" placeholder="Cash" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="category">Category</Label>
                        <Input id="category" v-model="form.category" placeholder="Asset" />
                        <InputError :message="form.errors.category" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select id="status" v-model="form.status" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 flex items-center gap-3">
                        <Button type="submit" :disabled="form.processing">
                            {{ editingId ? 'Update Account' : 'Save Account' }}
                        </Button>
                        <Button type="button" variant="outline" @click="resetForm">Reset</Button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
                    <Input v-model="searchForm.search" placeholder="Search account..." class="max-w-sm" />
                    <Button type="submit" variant="outline">Search</Button>
                </form>

                <div class="overflow-hidden rounded-lg border border-sidebar-border/60">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="account in accounts" :key="account.id" class="border-t border-sidebar-border/60">
                                <td class="px-4 py-3 font-mono">{{ account.code }}</td>
                                <td class="px-4 py-3">{{ account.name }}</td>
                                <td class="px-4 py-3">{{ account.category }}</td>
                                <td class="px-4 py-3">{{ account.status }}</td>
                                <td class="px-4 py-3 space-x-2">
                                    <Button type="button" size="sm" variant="outline" @click="editAccount(account)">Edit</Button>
                                    <Button type="button" size="sm" variant="destructive" @click="deleteAccount(account)">Delete</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
