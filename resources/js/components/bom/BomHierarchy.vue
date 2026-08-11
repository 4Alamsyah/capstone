<script setup lang="ts">
import { formatQty } from '@/lib/utils';

export type BomTreeItem = {
    id: number;
    line_type: 'part' | 'operation';
    label: string | null;
    quantity: string;
    notes: string | null;
    sub_bom: BomTreeNode | null;
};

export type BomTreeNode = {
    bom_id: number;
    bom_name: string;
    items: BomTreeItem[];
};

defineProps<{
    tree: BomTreeNode;
}>();
</script>

<template>
    <div class="rounded-md border border-dashed border-sidebar-border/60 bg-muted/30 p-2 text-xs">
        <div class="mb-1 font-medium text-muted-foreground">BOM: {{ tree.bom_name }}</div>
        <ul v-if="tree.items.length > 0" class="space-y-1.5 pl-3">
            <li v-for="item in tree.items" :key="item.id" class="border-l border-sidebar-border/50 pl-2">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span
                        class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold"
                        :class="item.line_type === 'part' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                    >
                        {{ item.line_type === 'part' ? 'Part' : 'Op' }}
                    </span>
                    <span>{{ item.label ?? '–' }}</span>
                    <span class="text-muted-foreground">× {{ formatQty(item.quantity) }}</span>
                </div>
                <BomHierarchy v-if="item.sub_bom" :tree="item.sub_bom" class="mt-1.5" />
            </li>
        </ul>
        <div v-else class="pl-3 text-muted-foreground">Tidak ada item.</div>
    </div>
</template>
