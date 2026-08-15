<script setup lang="ts">
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
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

type WarehouseItem = {
    id: number;
    code: string;
    name: string;
    location: string | null;
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
    warehouses: WarehouseItem[];
    filters: { search: string };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Part', href: '/parts' },
    { title: 'Warehouse', href: '/parts/warehouses' },
];

const editingId = ref<number | null>(null);
const dialogOpen = ref(false);

const form = useForm({
    code: '',
    name: '',
    location: '',
});

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const page = usePage();
const warehouseDeleteError = computed(() => (page.props.errors as Record<string, string> | undefined)?.warehouse);

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No warehouse data';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} warehouses`;
});

const submitSearch = () => {
    searchForm.get('/parts/warehouses', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    submitSearch();
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    dialogOpen.value = false;
    resetForm();
};

const openCreateDialog = () => {
    resetForm();
    dialogOpen.value = true;
};

const editWarehouse = (warehouse: WarehouseItem) => {
    editingId.value = warehouse.id;
    form.code = warehouse.code;
    form.name = warehouse.name;
    form.location = warehouse.location ?? '';
    form.clearErrors();
    dialogOpen.value = true;
};

const submit = () => {
    if (editingId.value) {
        form.put(`/parts/warehouses/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: closeDialog,
        });

        return;
    }

    form.post('/parts/warehouses', {
        preserveScroll: true,
        onSuccess: closeDialog,
    });
};

const deleteWarehouse = (warehouse: WarehouseItem) => {
    if (!window.confirm(`Hapus warehouse ${warehouse.code} - ${warehouse.name}?`)) {
        return;
    }

    useForm({}).delete(`/parts/warehouses/${warehouse.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Warehouse" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    title="Warehouse"
                    description="Kelola daftar warehouse yang dipakai untuk stok part."
                />
                <Button type="button" @click="openCreateDialog">Add Warehouse</Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">Warehouse List</h3>
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search warehouse..." class="w-full sm:w-72" />
                        <Button type="submit" variant="outline">Search</Button>
                        <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                    </form>
                </div>

                <InputError :message="warehouseDeleteError" />

                <div class="overflow-x-auto rounded-lg border border-sidebar-border/60">
                    <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                        <thead class="bg-sidebar-accent/40 text-left">
                            <tr>
                                <th class="px-3 py-2 font-medium">Code</th>
                                <th class="px-3 py-2 font-medium">Name</th>
                                <th class="px-3 py-2 font-medium">Location</th>
                                <th class="px-3 py-2 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr v-for="warehouse in warehouses" :key="warehouse.id">
                                <td class="px-3 py-2 font-mono font-medium">{{ warehouse.code }}</td>
                                <td class="px-3 py-2">{{ warehouse.name }}</td>
                                <td class="px-3 py-2">{{ warehouse.location || '-' }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex gap-2">
                                        <Button type="button" variant="outline" size="sm" @click="editWarehouse(warehouse)">
                                            Edit
                                        </Button>
                                        <Button type="button" variant="destructive" size="sm" @click="deleteWarehouse(warehouse)">
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!warehouses.length">
                                <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">
                                    Warehouse tidak ditemukan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-sidebar-border/70 pt-4">
                    <p class="text-sm text-muted-foreground">{{ paginationText }}</p>
                    <div class="flex items-center gap-2">
                        <Button v-if="pagination.prev_page_url" variant="outline" as-child>
                            <Link :href="pagination.prev_page_url">Previous</Link>
                        </Button>
                        <span class="text-sm text-muted-foreground">
                            Page {{ pagination.current_page }} / {{ pagination.last_page }}
                        </span>
                        <Button v-if="pagination.next_page_url" variant="outline" as-child>
                            <Link :href="pagination.next_page_url">Next</Link>
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                :open="dialogOpen"
                @update:open="(open) => (open ? (dialogOpen = true) : closeDialog())"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ editingId ? 'Edit Warehouse' : 'Add Warehouse' }}</DialogTitle>
                        <DialogDescription>
                            {{ editingId ? 'Ubah data warehouse yang dipilih.' : 'Tambahkan warehouse baru.' }}
                        </DialogDescription>
                    </DialogHeader>

                    <form class="grid gap-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input id="code" v-model="form.code" placeholder="WH-JKT" />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" placeholder="Warehouse Jakarta" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="location">Location</Label>
                            <Input id="location" v-model="form.location" placeholder="Opsional" />
                            <InputError :message="form.errors.location" />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeDialog">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ editingId ? 'Update Warehouse' : 'Save Warehouse' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
