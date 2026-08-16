<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

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
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.audit_trails'), href: '/accounting/audit-trails' },
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
    if (!window.confirm(t('accounting.audit_trails.delete_confirm'))) {
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
                :title="t('accounting.audit_trails.heading_title')"
                :description="t('accounting.audit_trails.heading_description')"
            />

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-5">
                <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
                    <Input v-model="searchForm.search" :placeholder="t('accounting.audit_trails.search_placeholder')" class="max-w-sm" />
                    <Button type="submit" variant="outline">{{ t('common.search') }}</Button>
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
                                <Button type="button" size="sm" variant="destructive" @click="deleteTrail(trail)">{{ t('common.delete') }}</Button>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-foreground">{{ t('accounting.audit_trails.subject_label') }}: {{ trail.subject }}</p>
                        <p v-if="trail.details" class="mt-1 text-sm text-muted-foreground">{{ trail.details }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
