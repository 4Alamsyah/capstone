<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type LogItem = {
    id: number;
    log_type: string;
    title: string;
    description: string | null;
    created_at: string;
    user_name: string | null;
    work_order: {
        id: number | null;
        wo_number: string | null;
        part_number: string | null;
        part_name: string | null;
    };
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
    logs: LogItem[];
    filters: { search: string };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Log MO', href: '/work-orders/logs' },
];

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const submitSearch = () => {
    searchForm.get('/work-orders/logs', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    submitSearch();
};

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No MO logs found';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} log entries`;
});
</script>

<template>
    <Head title="Log MO" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading
                title="Log MO"
                description="Lihat histori perubahan status, report hasil, dan konsumsi stock untuk setiap MO."
            />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">MO Activity History</h3>
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search WO number, title, or description..." class="w-full sm:w-80" />
                        <Button type="submit" variant="outline">Search</Button>
                        <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                    </form>
                </div>

                <div class="space-y-3">
                    <div v-if="logs.length === 0" class="rounded-md border border-dashed border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground">
                        Belum ada histori MO.
                    </div>

                    <div v-for="log in logs" :key="log.id" class="rounded-md border border-sidebar-border/50 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ log.title }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{ log.work_order.wo_number }}
                                    <span v-if="log.work_order.part_number">
                                        | {{ log.work_order.part_number }} - {{ log.work_order.part_name }}
                                    </span>
                                    <span v-if="log.user_name"> | by {{ log.user_name }}</span>
                                </div>
                            </div>
                            <div class="text-xs text-muted-foreground">{{ log.created_at }}</div>
                        </div>

                        <div v-if="log.description" class="mt-3 text-sm">
                            {{ log.description }}
                        </div>

                        <div class="mt-3 flex justify-end">
                            <Button v-if="log.work_order.id" size="sm" variant="outline" as-child>
                                <Link :href="`/work-orders/${log.work_order.id}`">Open MO</Link>
                            </Button>
                        </div>
                    </div>
                </div>

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
