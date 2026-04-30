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

type Period = {
    id: number;
    code: string;
    start_date: string;
    end_date: string;
    status: string;
};

type Props = {
    periods: Period[];
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
    { title: 'Fiscal Periods', href: '/accounting/fiscal-periods' },
];

const form = useForm({
    code: '',
    start_date: '',
    end_date: '',
    status: 'open',
});

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const submitSearch = () => {
    searchForm.get('/accounting/fiscal-periods', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.status = 'open';
    form.clearErrors();
};

const editPeriod = (period: Period) => {
    editingId.value = period.id;
    form.code = period.code;
    form.start_date = period.start_date;
    form.end_date = period.end_date;
    form.status = period.status;
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(`/accounting/fiscal-periods/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post('/accounting/fiscal-periods', {
        preserveScroll: true,
        onSuccess: resetForm,
    });
};

const deletePeriod = (period: Period) => {
    if (!window.confirm(`Hapus fiscal period ${period.code}?`)) {
        return;
    }

    useForm({}).delete(`/accounting/fiscal-periods/${period.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Accounting - Fiscal Periods" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                title="Fiscal Periods"
                description="CRUD untuk membuka, menutup, dan mengatur periode akuntansi."
            />

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="code">Code</Label>
                        <Input id="code" v-model="form.code" placeholder="2026-04" />
                        <InputError :message="form.errors.code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select id="status" v-model="form.status" class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs">
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="start-date">Start Date</Label>
                        <Input id="start-date" v-model="form.start_date" type="date" />
                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="end-date">End Date</Label>
                        <Input id="end-date" v-model="form.end_date" type="date" />
                        <InputError :message="form.errors.end_date" />
                    </div>

                    <div class="md:col-span-2 flex items-center gap-3">
                        <Button type="submit" :disabled="form.processing">
                            {{ editingId ? 'Update Period' : 'Save Period' }}
                        </Button>
                        <Button type="button" variant="outline" @click="resetForm">Reset</Button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
                    <Input v-model="searchForm.search" placeholder="Search period..." class="max-w-sm" />
                    <Button type="submit" variant="outline">Search</Button>
                </form>

                <div class="overflow-hidden rounded-lg border border-sidebar-border/60">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Start</th>
                                <th class="px-4 py-3 font-medium">End</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="period in periods" :key="period.id" class="border-t border-sidebar-border/60">
                                <td class="px-4 py-3 font-mono">{{ period.code }}</td>
                                <td class="px-4 py-3">{{ period.start_date }}</td>
                                <td class="px-4 py-3">{{ period.end_date }}</td>
                                <td class="px-4 py-3">{{ period.status }}</td>
                                <td class="px-4 py-3 space-x-2">
                                    <Button type="button" size="sm" variant="outline" @click="editPeriod(period)">Edit</Button>
                                    <Button type="button" size="sm" variant="destructive" @click="deletePeriod(period)">Delete</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
