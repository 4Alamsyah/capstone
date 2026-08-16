<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem, NavSubItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

const isSubItemActive = (item: NavSubItem): boolean => {
    if (item.items?.length) {
        return item.items.some((childItem) => isSubItemActive(childItem));
    }

    return isCurrentUrl(item.href);
};

const isItemActive = (item: NavItem): boolean => {
    if (item.items?.length) {
        return item.items.some((subItem) => isSubItemActive(subItem));
    }

    return isCurrentUrl(item.href);
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <Collapsible v-if="item.items?.length" as-child :default-open="isItemActive(item)" class="group/collapsible">
                    <div>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :is-active="isItemActive(item)" :tooltip="item.title">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.title">
                                    <Collapsible
                                        v-if="subItem.items?.length"
                                        as-child
                                        :default-open="isSubItemActive(subItem)"
                                        class="group/collapsible-sub"
                                    >
                                        <div>
                                            <CollapsibleTrigger as-child>
                                                <SidebarMenuSubButton :is-active="isSubItemActive(subItem)">
                                                    <span>{{ subItem.title }}</span>
                                                    <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible-sub:rotate-90" />
                                                </SidebarMenuSubButton>
                                            </CollapsibleTrigger>
                                            <CollapsibleContent>
                                                <SidebarMenuSub>
                                                    <SidebarMenuSubItem v-for="childItem in subItem.items" :key="childItem.title">
                                                        <SidebarMenuSubButton as-child :is-active="isCurrentUrl(childItem.href)">
                                                            <Link :href="childItem.href">
                                                                <span>{{ childItem.title }}</span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                </SidebarMenuSub>
                                            </CollapsibleContent>
                                        </div>
                                    </Collapsible>

                                    <SidebarMenuSubButton v-else as-child :is-active="isCurrentUrl(subItem.href)">
                                        <Link :href="subItem.href">
                                            <span>{{ subItem.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </div>
                </Collapsible>

                <SidebarMenuButton
                    v-else
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
