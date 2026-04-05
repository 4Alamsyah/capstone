<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type BomPartSummary = {
    id: number;
    part_number: string;
    name: string;
};

type BomItem = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    items_count: number;
    part: BomPartSummary;
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
    boms: BomItem[];
    filters: { search: string };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BOM', href: '/bom' },
];

const searchForm = useForm({ search: props.filters.search ?? '' });

const submitSearch = () => {
    searchForm.get('/bom', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    submitSearch();
};

const deleteBom = (bom: BomItem) => {
    if (!window.confirm(`Hapus BOM "${bom.name}"?`)) {
        return;
    }

    useForm({}).delete(`/bom/${bom.id}`);
};

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No BOM data';
    }

    return `Showing ${props.pagination.from}–${props.pagination.to} of ${props.pagination.total} BOMs`;
});
</script>

<template>
    <Head title="Bill of Materials" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <Heading title="Bill of Materials (BOM)" description="Kelola struktur komponen dan operasi untuk setiap produk jadi." />
                <Button as-child>
                    <Link href="/bom/create">+ Create BOM</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <!-- Search -->
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">BOM List</h3>
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search BOM or product..." class="w-full sm:w-72" />
                        <Button type="submit" variant="outline">Search</Button>
                        <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                    </form>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">BOM Name</th>
                                <th class="py-2 pr-3">Product (Part)</th>
                                <th class="py-2 pr-3">Items</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="boms.length === 0">
                                <td colspan="5" class="py-8 text-center text-muted-foreground">No BOMs found.</td>
                            </tr>
                            <tr
                                v-for="bom in boms"
                                :key="bom.id"
                                class="border-b border-sidebar-border/40 last:border-0"
                            >
                                <td class="py-2 pr-3 font-medium">{{ bom.name }}</td>
                                <td class="py-2 pr-3">
                                    <span class="text-xs text-muted-foreground">{{ bom.part.part_number }}</span>
                                    <br />
                                    {{ bom.part.name }}
                                </td>
                                <td class="py-2 pr-3">{{ bom.items_count }} item{{ bom.items_count !== 1 ? 's' : '' }}</td>
                                <td class="py-2 pr-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="bom.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                    >
                                        {{ bom.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="py-2 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="outline" as-child>
                                            <Link :href="`/bom/${bom.id}`">View</Link>
                                        </Button>
                                        <Button size="sm" variant="destructive" @click="deleteBom(bom)">Delete</Button>
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
                        <Button v-if="pagination.prev_page_url" size="sm" variant="outline" as-child>
                            <Link :href="pagination.prev_page_url">Prev</Link>
                        </Button>
                        <Button v-if="pagination.next_page_url" size="sm" variant="outline" as-child>
                            <Link :href="pagination.next_page_url">Next</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
