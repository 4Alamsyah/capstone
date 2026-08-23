<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Activity, CalendarClock, Info, Users, X } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

type Category = 'created' | 'updated' | 'deleted' | 'payment' | 'posted' | 'other';

type Trail = {
    id: number;
    actor: string;
    actor_email: string | null;
    action: string;
    category: Category;
    subject_type: string;
    subject_label: string;
    subject_id: number | null;
    details: string | null;
    time: string;
    happened_at_full: string | null;
    happened_at_iso: string | null;
    recorded_at: string | null;
};

type Option = { value: string | number; label: string };

type Filters = {
    search: string;
    user_id: number | null;
    subject_type: string | null;
    action: string | null;
    date_from: string | null;
    date_to: string | null;
    per_page: number;
};

type Props = {
    trails: Trail[];
    filters: Filters;
    filterOptions: {
        users: Option[];
        subjectTypes: Option[];
        actions: string[];
    };
    stats: { total: number; today: number; actors: number };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
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

const CATEGORY_STYLES: Record<Category, string> = {
    created: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
    updated: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
    deleted: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
    payment: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400',
    posted: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-400',
    other: 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-400',
};

const filterState = reactive({
    search: props.filters.search ?? '',
    user_id: props.filters.user_id ?? '',
    subject_type: props.filters.subject_type ?? '',
    action: props.filters.action ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    per_page: props.filters.per_page ?? 10,
});

const activeFilterCount = computed(() =>
    [
        filterState.search,
        filterState.user_id,
        filterState.subject_type,
        filterState.action,
        filterState.date_from,
        filterState.date_to,
    ].filter((value) => value !== '' && value !== null).length,
);

const applyFilters = (extra: Record<string, string | number> = {}) => {
    const source: Record<string, string | number> = { ...filterState, ...extra };
    const query: Record<string, string | number> = {};

    // Empty values are dropped so they never reach the URL as blank params.
    Object.entries(source).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            query[key] = value;
        }
    });

    router.get('/accounting/audit-trails', query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterState.search = '';
    filterState.user_id = '';
    filterState.subject_type = '';
    filterState.action = '';
    filterState.date_from = '';
    filterState.date_to = '';
    filterState.per_page = 10;

    router.get('/accounting/audit-trails', {}, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const goToPage = (page: number) => {
    if (page < 1 || page > props.pagination.last_page) {
        return;
    }

    applyFilters({ page });
};

const detailTrail = ref<Trail | null>(null);

const initials = (name: string): string =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');

const deleteTrail = (trail: Trail) => {
    if (!window.confirm(t('accounting.audit_trails.delete_confirm'))) {
        return;
    }

    useForm({}).delete(`/accounting/audit-trails/${trail.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (detailTrail.value?.id === trail.id) {
                detailTrail.value = null;
            }
        },
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

            <!-- Summary of the currently filtered set -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400">
                        <Activity class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">{{ t('accounting.audit_trails.stat_total') }}</p>
                        <p class="text-xl font-semibold">{{ stats.total }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                        <CalendarClock class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">{{ t('accounting.audit_trails.stat_today') }}</p>
                        <p class="text-xl font-semibold">{{ stats.today }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                        <Users class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">{{ t('accounting.audit_trails.stat_actors') }}</p>
                        <p class="text-xl font-semibold">{{ stats.actors }}</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="flex items-center gap-2 text-sm font-semibold">
                        {{ t('common.filter') }}
                        <span
                            v-if="activeFilterCount > 0"
                            class="rounded-full bg-primary px-2 py-0.5 text-[11px] font-medium text-primary-foreground"
                        >
                            {{ activeFilterCount }}
                        </span>
                    </h3>
                    <Button
                        v-if="activeFilterCount > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="resetFilters"
                    >
                        <X class="mr-1 h-3.5 w-3.5" />
                        {{ t('common.reset') }}
                    </Button>
                </div>

                <form class="grid gap-4 md:grid-cols-2 lg:grid-cols-3" @submit.prevent="applyFilters()">
                    <div class="grid min-w-0 gap-2 lg:col-span-3">
                        <Label for="filter-search">{{ t('common.search') }}</Label>
                        <Input
                            id="filter-search"
                            v-model="filterState.search"
                            :placeholder="t('accounting.audit_trails.search_placeholder')"
                        />
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="filter-actor">{{ t('accounting.audit_trails.filter_actor') }}</Label>
                        <select
                            id="filter-actor"
                            v-model="filterState.user_id"
                            class="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        >
                            <option value="">{{ t('accounting.audit_trails.all_option') }}</option>
                            <option v-for="user in filterOptions.users" :key="user.value" :value="user.value">
                                {{ user.label }}
                            </option>
                        </select>
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="filter-subject">{{ t('accounting.audit_trails.filter_subject') }}</Label>
                        <select
                            id="filter-subject"
                            v-model="filterState.subject_type"
                            class="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        >
                            <option value="">{{ t('accounting.audit_trails.all_option') }}</option>
                            <option v-for="subject in filterOptions.subjectTypes" :key="subject.value" :value="subject.value">
                                {{ subject.label }}
                            </option>
                        </select>
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="filter-action">{{ t('common.action') }}</Label>
                        <select
                            id="filter-action"
                            v-model="filterState.action"
                            class="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        >
                            <option value="">{{ t('accounting.audit_trails.all_option') }}</option>
                            <option v-for="action in filterOptions.actions" :key="action" :value="action">
                                {{ action }}
                            </option>
                        </select>
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="filter-date-from">{{ t('accounting.audit_trails.filter_date_from') }}</Label>
                        <Input id="filter-date-from" v-model="filterState.date_from" type="date" />
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="filter-date-to">{{ t('accounting.audit_trails.filter_date_to') }}</Label>
                        <Input id="filter-date-to" v-model="filterState.date_to" type="date" />
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="filter-per-page">{{ t('accounting.audit_trails.per_page') }}</Label>
                        <select
                            id="filter-per-page"
                            v-model.number="filterState.per_page"
                            class="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        >
                            <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
                        </select>
                    </div>

                    <div class="flex items-end lg:col-span-3">
                        <Button type="submit">{{ t('common.apply') }}</Button>
                    </div>
                </form>
            </div>

            <!-- Trail list -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-5">
                <div v-if="trails.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                    {{ t('accounting.audit_trails.empty_state') }}
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="trail in trails"
                        :key="trail.id"
                        class="rounded-lg border border-sidebar-border/70 p-4 transition hover:border-sidebar-border"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground"
                                >
                                    {{ initials(trail.actor) }}
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold">{{ trail.actor }}</p>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[11px] font-medium capitalize"
                                            :class="CATEGORY_STYLES[trail.category]"
                                        >
                                            {{ trail.category }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-muted-foreground">{{ trail.action }}</p>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <p class="text-xs text-muted-foreground">{{ trail.time }}</p>
                                <Button type="button" size="sm" variant="outline" @click="detailTrail = trail">
                                    <Info class="mr-1 h-3.5 w-3.5" />
                                    {{ t('accounting.audit_trails.detail_button') }}
                                </Button>
                                <Button type="button" size="sm" variant="destructive" @click="deleteTrail(trail)">
                                    {{ t('common.delete') }}
                                </Button>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <span class="text-muted-foreground">
                                {{ t('accounting.audit_trails.subject_label') }}:
                                <strong class="font-medium text-foreground">{{ trail.subject_label }}</strong>
                                <span v-if="trail.subject_id" class="text-muted-foreground">#{{ trail.subject_id }}</span>
                            </span>
                            <span v-if="trail.details" class="text-muted-foreground">
                                {{ t('accounting.audit_trails.field_details') }}:
                                <strong class="font-medium text-foreground">{{ trail.details }}</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="pagination.total > 0"
                    class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-sidebar-border/70 pt-4"
                >
                    <p class="text-xs text-muted-foreground">
                        {{
                            t('accounting.audit_trails.showing', {
                                from: pagination.from ?? 0,
                                to: pagination.to ?? 0,
                                total: pagination.total,
                            })
                        }}
                    </p>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="pagination.current_page <= 1"
                            @click="goToPage(pagination.current_page - 1)"
                        >
                            {{ t('common.previous') }}
                        </Button>
                        <span class="text-xs text-muted-foreground">
                            {{ t('common.page_of', { current: pagination.current_page, total: pagination.last_page }) }}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="pagination.current_page >= pagination.last_page"
                            @click="goToPage(pagination.current_page + 1)"
                        >
                            {{ t('common.next') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail dialog -->
        <Dialog :open="detailTrail !== null" @update:open="(open) => { if (!open) detailTrail = null; }">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ t('accounting.audit_trails.detail_title') }}</DialogTitle>
                    <DialogDescription>{{ t('accounting.audit_trails.detail_desc') }}</DialogDescription>
                </DialogHeader>

                <dl v-if="detailTrail" class="divide-y divide-sidebar-border/50 text-sm">
                    <div class="grid grid-cols-3 gap-3 py-2">
                        <dt class="text-muted-foreground">{{ t('accounting.audit_trails.field_id') }}</dt>
                        <dd class="col-span-2 font-medium">#{{ detailTrail.id }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-2">
                        <dt class="text-muted-foreground">{{ t('accounting.audit_trails.field_actor') }}</dt>
                        <dd class="col-span-2">
                            <p class="font-medium">{{ detailTrail.actor }}</p>
                            <p v-if="detailTrail.actor_email" class="text-xs text-muted-foreground">
                                {{ detailTrail.actor_email }}
                            </p>
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-2">
                        <dt class="text-muted-foreground">{{ t('common.action') }}</dt>
                        <dd class="col-span-2 font-medium">{{ detailTrail.action }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-2">
                        <dt class="text-muted-foreground">{{ t('accounting.audit_trails.field_category') }}</dt>
                        <dd class="col-span-2">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium capitalize"
                                :class="CATEGORY_STYLES[detailTrail.category]"
                            >
                                {{ detailTrail.category }}
                            </span>
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-2">
                        <dt class="text-muted-foreground">{{ t('accounting.audit_trails.subject_label') }}</dt>
                        <dd class="col-span-2">
                            <p class="font-medium">
                                {{ detailTrail.subject_label }}
                                <span v-if="detailTrail.subject_id" class="text-muted-foreground">#{{ detailTrail.subject_id }}</span>
                            </p>
                            <p class="font-mono text-xs break-all text-muted-foreground">{{ detailTrail.subject_type }}</p>
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-2">
                        <dt class="text-muted-foreground">{{ t('accounting.audit_trails.field_details') }}</dt>
                        <dd class="col-span-2 break-words">
                            {{ detailTrail.details ?? '—' }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-2">
                        <dt class="text-muted-foreground">{{ t('accounting.audit_trails.field_happened_at') }}</dt>
                        <dd class="col-span-2 font-medium">{{ detailTrail.happened_at_full ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-2">
                        <dt class="text-muted-foreground">{{ t('accounting.audit_trails.field_recorded_at') }}</dt>
                        <dd class="col-span-2 text-muted-foreground">{{ detailTrail.recorded_at ?? '—' }}</dd>
                    </div>
                </dl>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
