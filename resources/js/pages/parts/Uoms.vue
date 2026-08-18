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

type UomItem = {
    id: number;
    code: string;
    name: string;
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
    uoms: UomItem[];
    filters: { search: string };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Part', href: '/parts' },
    { title: 'Satuan', href: '/parts/uoms' },
];

const editingId = ref<number | null>(null);
const dialogOpen = ref(false);

const form = useForm({
    code: '',
    name: '',
});

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const page = usePage();
const uomDeleteError = computed(() => (page.props.errors as Record<string, string> | undefined)?.uom);

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No UOM data';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} units`;
});

const submitSearch = () => {
    searchForm.get('/parts/uoms', {
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

const editUom = (uom: UomItem) => {
    editingId.value = uom.id;
    form.code = uom.code;
    form.name = uom.name;
    form.clearErrors();
    dialogOpen.value = true;
};

const submit = () => {
    if (editingId.value) {
        form.put(`/parts/uoms/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: closeDialog,
        });

        return;
    }

    form.post('/parts/uoms', {
        preserveScroll: true,
        onSuccess: closeDialog,
    });
};

const deleteUom = (uom: UomItem) => {
    if (!window.confirm(`Hapus satuan ${uom.code} - ${uom.name}?`)) {
        return;
    }

    useForm({}).delete(`/parts/uoms/${uom.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Satuan (UOM)" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    title="Satuan (UOM)"
                    description="Kelola daftar satuan (unit of measure) yang dipakai part dan BOM."
                />
                <Button type="button" @click="openCreateDialog">Add UOM</Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">UOM List</h3>
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search UOM..." class="w-full sm:w-72" />
                        <Button type="submit" variant="outline">Search</Button>
                        <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                    </form>
                </div>

                <InputError :message="uomDeleteError" />

                <div class="overflow-x-auto rounded-lg border border-sidebar-border/60">
                    <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                        <thead class="bg-sidebar-accent/40 text-left">
                            <tr>
                                <th class="px-3 py-2 font-medium">Code</th>
                                <th class="px-3 py-2 font-medium">Name</th>
                                <th class="px-3 py-2 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr v-for="uom in uoms" :key="uom.id">
                                <td class="px-3 py-2 font-mono font-medium">{{ uom.code }}</td>
                                <td class="px-3 py-2">{{ uom.name }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex gap-2">
                                        <Button type="button" variant="outline" size="sm" @click="editUom(uom)">
                                            Edit
                                        </Button>
                                        <Button type="button" variant="destructive" size="sm" @click="deleteUom(uom)">
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!uoms.length">
                                <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">
                                    Satuan tidak ditemukan.
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
                        <DialogTitle>{{ editingId ? 'Edit UOM' : 'Add UOM' }}</DialogTitle>
                        <DialogDescription>
                            {{ editingId ? 'Ubah data satuan yang dipilih.' : 'Tambahkan satuan baru.' }}
                        </DialogDescription>
                    </DialogHeader>

                    <form class="grid gap-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input id="code" v-model="form.code" placeholder="PCS" />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" placeholder="Pieces" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeDialog">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ editingId ? 'Update UOM' : 'Save UOM' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
