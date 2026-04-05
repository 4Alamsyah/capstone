<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type WorkCenterItem = {
    id: number;
    name: string;
    description: string | null;
    price_per_operation: string | null;
    employee_count: number | null;
    created_at: string;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    workCenters: WorkCenterItem[];
    filters: { search: string };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Work Center', href: '/work-centers' },
];

// ── Add form ────────────────────────────────────────────────────────────────
const form = useForm({
    name: '',
    description: '',
    price_per_operation: '',
    employee_count: '',
});

const submit = () => {
    form.post('/work-centers', {
        onSuccess: () => form.reset(),
    });
};

// ── Search ───────────────────────────────────────────────────────────────────
const searchForm = useForm({ search: props.filters.search ?? '' });

const submitSearch = () => {
    searchForm.get('/work-centers', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    submitSearch();
};

// ── Inline edit ──────────────────────────────────────────────────────────────
const editId = ref<number | null>(null);

const editForm = useForm({
    name: '',
    description: '',
    price_per_operation: '',
    employee_count: '',
});

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No work center data';
    }

    return `Showing ${props.pagination.from}–${props.pagination.to} of ${props.pagination.total} work centers`;
});

const startEdit = (wc: WorkCenterItem) => {
    editId.value = wc.id;
    editForm.name = wc.name;
    editForm.description = wc.description ?? '';
    editForm.price_per_operation = wc.price_per_operation ?? '';
    editForm.employee_count = wc.employee_count !== null ? String(wc.employee_count) : '';
    editForm.clearErrors();
};

const cancelEdit = () => {
    editId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!editId.value) {
        return;
    }

    editForm.put(`/work-centers/${editId.value}`, {
        onSuccess: () => cancelEdit(),
    });
};

const deleteWorkCenter = (wc: WorkCenterItem) => {
    if (!window.confirm(`Hapus work center "${wc.name}"?`)) {
        return;
    }

    useForm({}).delete(`/work-centers/${wc.id}`);
};

const formatPrice = (value: string | null) => {
    if (value === null || value === '') {
        return '–';
    }

    return 'Rp ' + Number(value).toLocaleString('id-ID');
};
</script>

<template>
    <Head title="Work Center" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading
                title="Work Center / Operation"
                description="Kelola work center beserta harga per operasi dan jumlah karyawan."
            />

            <div class="grid gap-6 lg:grid-cols-[420px_1fr]">
                <!-- ── Add Form ── -->
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-4 text-sm font-semibold">Add Work Center</h3>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="name">Name <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="Nama work center" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                placeholder="Deskripsi (opsional)"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="price_per_operation">Price per Operation</Label>
                            <Input
                                id="price_per_operation"
                                v-model="form.price_per_operation"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0 (opsional)"
                            />
                            <InputError :message="form.errors.price_per_operation" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="employee_count">Employee Count</Label>
                            <Input
                                id="employee_count"
                                v-model="form.employee_count"
                                type="number"
                                min="0"
                                step="1"
                                placeholder="0 (opsional)"
                            />
                            <InputError :message="form.errors.employee_count" />
                        </div>

                        <Button type="submit" class="w-full" :disabled="form.processing">Save Work Center</Button>
                    </form>
                </div>

                <!-- ── List ── -->
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold">Work Center List</h3>
                        <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                            <Input v-model="searchForm.search" placeholder="Search..." class="w-full sm:w-64" />
                            <Button type="submit" variant="outline">Search</Button>
                            <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                        </form>
                    </div>

                    <!-- Inline edit panel -->
                    <div v-if="editId !== null" class="mb-4 rounded-md border border-sidebar-border/70 p-3">
                        <h4 class="mb-3 text-sm font-semibold">Edit Work Center</h4>

                        <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submitEdit">
                            <div class="grid gap-2">
                                <Label for="edit-name">Name <span class="text-destructive">*</span></Label>
                                <Input id="edit-name" v-model="editForm.name" />
                                <InputError :message="editForm.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-price">Price per Operation</Label>
                                <Input
                                    id="edit-price"
                                    v-model="editForm.price_per_operation"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="opsional"
                                />
                                <InputError :message="editForm.errors.price_per_operation" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-employee">Employee Count</Label>
                                <Input
                                    id="edit-employee"
                                    v-model="editForm.employee_count"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="opsional"
                                />
                                <InputError :message="editForm.errors.employee_count" />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="edit-description">Description</Label>
                                <textarea
                                    id="edit-description"
                                    v-model="editForm.description"
                                    rows="2"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    placeholder="opsional"
                                />
                                <InputError :message="editForm.errors.description" />
                            </div>

                            <div class="flex gap-2 md:col-span-2">
                                <Button type="submit" size="sm" :disabled="editForm.processing">Save</Button>
                                <Button type="button" size="sm" variant="outline" @click="cancelEdit">Cancel</Button>
                            </div>
                        </form>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                    <th class="py-2 pr-3">Name</th>
                                    <th class="py-2 pr-3">Description</th>
                                    <th class="py-2 pr-3">Price / Operation</th>
                                    <th class="py-2 pr-3">Employees</th>
                                    <th class="py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="workCenters.length === 0">
                                    <td colspan="5" class="py-8 text-center text-muted-foreground">
                                        No work centers found.
                                    </td>
                                </tr>
                                <tr
                                    v-for="wc in workCenters"
                                    :key="wc.id"
                                    class="border-b border-sidebar-border/40 last:border-0"
                                    :class="{ 'bg-muted/30': editId === wc.id }"
                                >
                                    <td class="py-2 pr-3 font-medium">{{ wc.name }}</td>
                                    <td class="py-2 pr-3 text-muted-foreground">{{ wc.description ?? '–' }}</td>
                                    <td class="py-2 pr-3">{{ formatPrice(wc.price_per_operation) }}</td>
                                    <td class="py-2 pr-3">{{ wc.employee_count ?? '–' }}</td>
                                    <td class="py-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" @click="startEdit(wc)">Edit</Button>
                                            <Button size="sm" variant="destructive" @click="deleteWorkCenter(wc)">Delete</Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4 flex items-center justify-between gap-3 text-sm text-muted-foreground">
                        <span>{{ paginationText }}</span>
                        <div class="flex gap-2">
                            <Button
                                v-if="pagination.prev_page_url"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <Link :href="pagination.prev_page_url">Prev</Link>
                            </Button>
                            <Button
                                v-if="pagination.next_page_url"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <Link :href="pagination.next_page_url">Next</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
