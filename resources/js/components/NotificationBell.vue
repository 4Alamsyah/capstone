<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Bell, FileText, PackageSearch, ShoppingCart } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { read, readAll } from '@/routes/notifications';
import type { AppNotification } from '@/types';

const { t } = useI18n();

const page = usePage();
const unreadCount = computed(() => page.props.notifications?.unreadCount ?? 0);
const recent = computed<AppNotification[]>(() => page.props.notifications?.recent ?? []);

const iconByType: Record<string, typeof Bell> = {
    po_generated: ShoppingCart,
    pv_generated: FileText,
    wo_generated: PackageSearch,
};

function iconFor(type: string | null) {
    return (type && iconByType[type]) || Bell;
}

function relativeTime(isoDate: string): string {
    const diffMs = Date.now() - new Date(isoDate).getTime();
    const minutes = Math.floor(diffMs / 60000);

    if (minutes < 1) return t('notifications.just_now');
    if (minutes < 60) return t('notifications.minutes_ago', { n: minutes });

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return t('notifications.hours_ago', { n: hours });

    const days = Math.floor(hours / 24);
    return t('notifications.days_ago', { n: days });
}

function markAsRead(notification: AppNotification) {
    if (notification.read_at) return;
    axios.post(read.url(notification.id));
}

function markAllAsRead() {
    axios.post(readAll.url()).then(() => {
        router.reload({ only: ['notifications'] });
    });
}

let pollTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    pollTimer = setInterval(() => {
        router.reload({ only: ['notifications'] });
    }, 45000);
});

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative">
                <span class="sr-only">{{ t('notifications.title') }}</span>
                <Bell class="size-5" />
                <Badge
                    v-if="unreadCount > 0"
                    variant="destructive"
                    class="absolute -top-1 -right-1 h-4 min-w-4 justify-center rounded-full px-1 text-[10px]"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </Badge>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80">
            <DropdownMenuLabel class="flex items-center justify-between gap-2">
                <span>{{ t('notifications.title') }}</span>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="text-xs font-normal text-muted-foreground hover:text-foreground hover:underline"
                    @click="markAllAsRead"
                >
                    {{ t('notifications.mark_all_read') }}
                </button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />

            <div class="max-h-96 overflow-y-auto">
                <p v-if="recent.length === 0" class="px-2 py-4 text-center text-sm text-muted-foreground">
                    {{ t('notifications.empty') }}
                </p>

                <DropdownMenuItem v-for="notification in recent" :key="notification.id" as-child>
                    <Link
                        :href="notification.url ?? '#'"
                        class="flex w-full cursor-pointer items-start gap-2 whitespace-normal"
                        @click="markAsRead(notification)"
                    >
                        <component :is="iconFor(notification.type)" class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span class="flex-1 space-y-0.5">
                            <span class="flex items-center gap-1.5">
                                <span class="text-sm font-medium">{{ notification.title }}</span>
                                <span v-if="!notification.read_at" class="size-1.5 shrink-0 rounded-full bg-primary" />
                            </span>
                            <span class="block text-xs text-muted-foreground">{{ notification.description }}</span>
                            <span class="block text-xs text-muted-foreground/70">{{ relativeTime(notification.created_at) }}</span>
                        </span>
                    </Link>
                </DropdownMenuItem>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
