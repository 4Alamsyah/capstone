<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type TimelineItem = {
    id: number;
    log_type: string;
    title: string;
    description: string | null;
    user_name: string | null;
    created_at: string;
    hours_from_start: number;
};

type LeadTimeWorkOrder = {
    id: number;
    wo_number: string;
    status: string;
    status_label: string;
    product: {
        part_number: string;
        name: string;
        bom_name: string;
    };
    source_po: string | null;
    created_at: string;
    ended_at: string;
    lead_time_hours: number;
    timeline: TimelineItem[];
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
    workOrders: LeadTimeWorkOrder[];
    filters: { search: string; status: string };
    pagination: PaginationMeta;
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manufacture Order', href: '/work-orders' },
    { title: 'Lead Time', href: '/work-orders/lead-time' },
];

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600',
    released: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-yellow-100 text-yellow-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-500',
};

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const submitFilter = () => {
    filterForm.get('/work-orders/lead-time', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilter = () => {
    filterForm.search = '';
    filterForm.status = '';
    submitFilter();
};

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No manufacture order lead time data found';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} manufacture orders`;
});

const barWidthPercent = (workOrder: LeadTimeWorkOrder): number => {
    // Keep bars visible even for short durations.
    return Math.max(8, 100);
};

const markerLeftPercent = (workOrder: LeadTimeWorkOrder, event: TimelineItem): number => {
    if (workOrder.lead_time_hours <= 0) {
        return 0;
    }

    const ratio = (event.hours_from_start / workOrder.lead_time_hours) * 100;

    return Math.min(100, Math.max(0, Number(ratio.toFixed(2))));
};

const formatHours = (hours: number): string => {
    if (hours < 24) {
        return `${hours} jam`;
    }

    const days = Math.floor(hours / 24);
    const remainingHours = Number((hours % 24).toFixed(2));

    return `${days} hari ${remainingHours} jam`;
};
</script>

<template>
    <Head title="Lead Time MO" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading
                title="Lead Time MO"
                description="Timeline aktivitas dan durasi lead time untuk tiap manufacturing order."
            />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">Timeline Lead Time</h3>
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitFilter">
                        <Input v-model="filterForm.search" placeholder="Search MO / product..." class="w-full sm:w-64" />
                        <select
                            v-model="filterForm.status"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All Status</option>
                            <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <Button type="submit" variant="outline">Filter</Button>
                        <Button v-if="filterForm.search || filterForm.status" type="button" variant="ghost" @click="clearFilter">Clear</Button>
                    </form>
                </div>

                <div class="space-y-4">
                    <div
                        v-if="workOrders.length === 0"
                        class="rounded-md border border-dashed border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        Belum ada data lead time MO.
                    </div>

                    <article
                        v-for="workOrder in workOrders"
                        :key="workOrder.id"
                        class="rounded-lg border border-sidebar-border/50 p-4"
                    >
                        <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
                            <div class="space-y-2">
                                <div class="font-mono text-sm font-semibold">{{ workOrder.wo_number }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ workOrder.product.part_number }} - {{ workOrder.product.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">BOM: {{ workOrder.product.bom_name }}</div>
                                <div class="text-xs text-muted-foreground">Source PO: {{ workOrder.source_po ?? '-' }}</div>
                                <div
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusColors[workOrder.status]"
                                >
                                    {{ workOrder.status_label }}
                                </div>
                                <div class="text-xs text-muted-foreground">Lead Time: {{ formatHours(workOrder.lead_time_hours) }}</div>
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-md border border-sidebar-border/50 bg-muted/20 p-3">
                                    <div class="mb-2 flex items-center justify-between text-xs text-muted-foreground">
                                        <span>{{ workOrder.created_at }}</span>
                                        <span>{{ workOrder.ended_at }}</span>
                                    </div>

                                    <div class="relative h-10 rounded-md bg-sidebar-border/30">
                                        <div
                                            class="absolute left-0 top-1/2 h-2 -translate-y-1/2 rounded-full bg-primary/80"
                                            :style="{ width: `${barWidthPercent(workOrder)}%` }"
                                        />

                                        <div
                                            v-for="event in workOrder.timeline"
                                            :key="event.id"
                                            class="absolute top-1/2 -translate-y-1/2"
                                            :style="{ left: `${markerLeftPercent(workOrder, event)}%` }"
                                        >
                                            <span class="block h-3 w-3 -translate-x-1/2 rounded-full border border-white bg-blue-600" />
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <div
                                        v-for="event in workOrder.timeline"
                                        :key="event.id"
                                        class="rounded-md border border-sidebar-border/40 p-2"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="text-sm font-medium">{{ event.title }}</div>
                                            <div class="text-xs text-muted-foreground">{{ event.created_at }}</div>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            Posisi: +{{ event.hours_from_start }} jam dari start
                                            <span v-if="event.user_name"> | by {{ event.user_name }}</span>
                                        </div>
                                        <div v-if="event.description" class="mt-1 text-sm">{{ event.description }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <Button size="sm" variant="outline" as-child>
                                <Link :href="`/work-orders/${workOrder.id}`">Open MO</Link>
                            </Button>
                        </div>
                    </article>
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
