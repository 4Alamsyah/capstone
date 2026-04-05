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

type SupplierItem = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
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
    suppliers: SupplierItem[];
    filters: {
        search: string;
    };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supplier',
        href: '/suppliers',
    },
];

const form = useForm({
    name: '',
    phone: '',
    email: '',
    address: '',
});

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const editId = ref<number | null>(null);

const editForm = useForm({
    name: '',
    phone: '',
    email: '',
    address: '',
});

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No supplier data';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} suppliers`;
});

const submit = () => {
    form.post('/suppliers', {
        onSuccess: () => {
            form.reset();
        },
    });
};

const submitSearch = () => {
    searchForm.get('/suppliers', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    submitSearch();
};

const startEdit = (supplier: SupplierItem) => {
    editId.value = supplier.id;
    editForm.name = supplier.name;
    editForm.phone = supplier.phone ?? '';
    editForm.email = supplier.email ?? '';
    editForm.address = supplier.address ?? '';
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

    editForm.put(`/suppliers/${editId.value}`, {
        onSuccess: () => {
            cancelEdit();
        },
    });
};

const deleteSupplier = (supplier: SupplierItem) => {
    if (!window.confirm(`Hapus supplier ${supplier.name}?`)) {
        return;
    }

    useForm({}).delete(`/suppliers/${supplier.id}`);
};
</script>

<template>
    <Head title="Supplier" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading
                title="Supplier Management"
                description="Tambah supplier baru dari menu Supplier, lalu pilih multi supplier di Register Part."
            />

            <div class="grid gap-6 lg:grid-cols-[420px_1fr]">
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-4 text-sm font-semibold">Add Supplier</h3>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="name">Supplier Name</Label>
                            <Input id="name" v-model="form.name" placeholder="Nama supplier" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="form.phone" placeholder="No telepon" />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="form.email" type="email" placeholder="Email supplier" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address">Address</Label>
                            <textarea
                                id="address"
                                v-model="form.address"
                                rows="3"
                                class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                placeholder="Alamat supplier"
                            />
                            <InputError :message="form.errors.address" />
                        </div>

                        <Button type="submit" class="w-full" :disabled="form.processing">Save Supplier</Button>
                    </form>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold">Supplier List</h3>
                        <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                            <Input v-model="searchForm.search" placeholder="Search supplier..." class="w-full sm:w-72" />
                            <Button type="submit" variant="outline">Search</Button>
                            <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                        </form>
                    </div>

                    <div v-if="editId !== null" class="mb-4 rounded-md border border-sidebar-border/70 p-3">
                        <h4 class="mb-3 text-sm font-semibold">Edit Supplier</h4>

                        <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submitEdit">
                            <div class="grid gap-2">
                                <Label for="edit-name">Supplier Name</Label>
                                <Input id="edit-name" v-model="editForm.name" />
                                <InputError :message="editForm.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-phone">Phone</Label>
                                <Input id="edit-phone" v-model="editForm.phone" />
                                <InputError :message="editForm.errors.phone" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-email">Email</Label>
                                <Input id="edit-email" v-model="editForm.email" type="email" />
                                <InputError :message="editForm.errors.email" />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="edit-address">Address</Label>
                                <textarea
                                    id="edit-address"
                                    v-model="editForm.address"
                                    rows="2"
                                    class="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                />
                                <InputError :message="editForm.errors.address" />
                            </div>

                            <div class="flex gap-2 md:col-span-2">
                                <Button type="submit" :disabled="editForm.processing">Update</Button>
                                <Button type="button" variant="outline" @click="cancelEdit">Cancel</Button>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                            <thead class="bg-sidebar-accent/40 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Name</th>
                                    <th class="px-3 py-2 font-medium">Phone</th>
                                    <th class="px-3 py-2 font-medium">Email</th>
                                    <th class="px-3 py-2 font-medium">Address</th>
                                    <th class="px-3 py-2 font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="supplier in suppliers" :key="supplier.id">
                                    <td class="px-3 py-2 font-medium">{{ supplier.name }}</td>
                                    <td class="px-3 py-2">{{ supplier.phone || '-' }}</td>
                                    <td class="px-3 py-2">{{ supplier.email || '-' }}</td>
                                    <td class="px-3 py-2">{{ supplier.address || '-' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-2">
                                            <Button type="button" variant="outline" size="sm" @click="startEdit(supplier)">
                                                Edit
                                            </Button>
                                            <Button type="button" variant="destructive" size="sm" @click="deleteSupplier(supplier)">
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!suppliers.length">
                                    <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">
                                        Supplier tidak ditemukan.
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
            </div>
        </div>
    </AppLayout>
</template>
