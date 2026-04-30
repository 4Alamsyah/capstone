<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Trail = {
    id: number;
    actor: string;
    action: string;
    subject: string;
    details: string | null;
    time: string;
};

type Props = {
    trails: Trail[];
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

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'Audit Trails', href: '/accounting/audit-trails' },
];

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const submitSearch = () => {
    searchForm.get('/accounting/audit-trails', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const deleteTrail = (trail: Trail) => {
    if (!window.confirm('Hapus audit trail ini?')) {
        return;
    }

    useForm({}).delete(`/accounting/audit-trails/${trail.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Accounting - Audit Trails" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                title="Audit Trails"
                description="Log aktivitas accounting yang bisa ditelusuri dan dibersihkan bila perlu."
            />

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
                    <Input v-model="searchForm.search" placeholder="Search activity..." class="max-w-sm" />
                    <Button type="submit" variant="outline">Search</Button>
                </form>

                <div class="space-y-3">
                    <div v-for="trail in trails" :key="trail.id" class="rounded-lg border border-sidebar-border/70 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">{{ trail.actor }}</p>
                                <p class="text-sm text-muted-foreground">{{ trail.action }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <p class="text-xs text-muted-foreground">{{ trail.time }}</p>
                                <Button type="button" size="sm" variant="destructive" @click="deleteTrail(trail)">Delete</Button>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-foreground">Subject: {{ trail.subject }}</p>
                        <p v-if="trail.details" class="mt-1 text-sm text-muted-foreground">{{ trail.details }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
