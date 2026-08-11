<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatQty } from '@/lib/utils';

export type StockOption = {
    warehouse_id: number;
    warehouse_code: string | null;
    warehouse_name: string | null;
    quantity: number;
};

export type WarehouseOption = {
    id: number;
    code: string;
    name: string;
};

export type ComponentRequirement = {
    bom_item_id: number;
    part_id: number;
    part_number: string;
    part_name: string;
    bom_quantity: string;
    recommended_quantity: number;
    is_sub_assembly: boolean;
    bom_id: number | null;
    bom_name: string | null;
    planning_strategy: 'order_oriented' | 'stock_driven' | null;
    safety_stock?: number;
    total_stock?: number;
    stocks: StockOption[];
    children: ComponentRequirement[];
};

export type ComponentInput = {
    warehouse_id: number | null;
    quantity: string;
    good_quantity: string;
    reject_quantity: string;
};

defineProps<{
    node: ComponentRequirement;
    inputs: Record<number, ComponentInput>;
    warehouses: WarehouseOption[];
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <div class="rounded-md border border-sidebar-border/50 p-3">
        <div class="mb-2 flex flex-wrap items-center gap-2">
            <span
                v-if="node.is_sub_assembly"
                class="inline-flex rounded px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-700"
            >
                Sub-assembly
            </span>
            <span
                v-else-if="node.planning_strategy === 'stock_driven'"
                class="inline-flex rounded px-2 py-0.5 text-xs font-semibold bg-sky-100 text-sky-700"
            >
                Stock Driven
            </span>
            <div class="font-medium">{{ node.part_number }} - {{ node.part_name }}</div>
        </div>
        <div class="mb-2 text-xs text-muted-foreground">
            Qty per BOM: {{ formatQty(node.bom_quantity) }} | Recommended: {{ node.recommended_quantity }}
            <span v-if="node.is_sub_assembly"> | BOM: {{ node.bom_name }}</span>
            <span v-else-if="node.planning_strategy === 'stock_driven'">
                | Stock saat ini: {{ node.total_stock }} / Safety stock: {{ node.safety_stock }}
                — kalau kurang, WO replenishment akan dibuat otomatis
            </span>
        </div>

        <!-- Sub-assembly: produced (good/reject) then consumed into its parent -->
        <div v-if="node.is_sub_assembly" class="grid gap-3 lg:grid-cols-3">
            <div class="grid gap-1">
                <Label :for="`warehouse-${node.bom_item_id}`">Warehouse Produksi</Label>
                <select
                    :id="`warehouse-${node.bom_item_id}`"
                    v-model="inputs[node.bom_item_id].warehouse_id"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <option :value="null">- pilih warehouse -</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                        {{ warehouse.code }} - {{ warehouse.name }}
                    </option>
                </select>
                <InputError :message="errors[`components.${node.bom_item_id}.warehouse_id`]" />
            </div>

            <div class="grid gap-1">
                <Label :for="`good-${node.bom_item_id}`">Good Quantity</Label>
                <Input :id="`good-${node.bom_item_id}`" v-model="inputs[node.bom_item_id].good_quantity" type="number" min="0" step="1" />
                <InputError :message="errors[`components.${node.bom_item_id}.good_quantity`]" />
            </div>

            <div class="grid gap-1">
                <Label :for="`reject-${node.bom_item_id}`">Reject Quantity</Label>
                <Input :id="`reject-${node.bom_item_id}`" v-model="inputs[node.bom_item_id].reject_quantity" type="number" min="0" step="1" />
                <InputError :message="errors[`components.${node.bom_item_id}.reject_quantity`]" />
            </div>
        </div>

        <!-- Raw material leaf: consumed straight from existing stock -->
        <div v-else class="grid gap-3 lg:grid-cols-2">
            <div class="grid gap-1">
                <Label :for="`warehouse-${node.bom_item_id}`">Warehouse</Label>
                <select
                    :id="`warehouse-${node.bom_item_id}`"
                    v-model="inputs[node.bom_item_id].warehouse_id"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <option :value="null">- pilih warehouse -</option>
                    <option v-for="stock in node.stocks" :key="stock.warehouse_id" :value="stock.warehouse_id">
                        {{ stock.warehouse_code }} - {{ stock.warehouse_name }} (stok: {{ stock.quantity }})
                    </option>
                </select>
                <InputError :message="errors[`components.${node.bom_item_id}.warehouse_id`]" />
            </div>

            <div class="grid gap-1">
                <Label :for="`qty-${node.bom_item_id}`">Consumed Qty</Label>
                <Input :id="`qty-${node.bom_item_id}`" v-model="inputs[node.bom_item_id].quantity" type="number" min="0" step="1" />
                <InputError :message="errors[`components.${node.bom_item_id}.quantity`]" />
            </div>
        </div>

        <!-- Nested components of this sub-assembly -->
        <div v-if="node.children.length > 0" class="mt-3 space-y-3 border-l-2 border-sidebar-border/50 pl-3">
            <ComponentReportNode
                v-for="child in node.children"
                :key="child.bom_item_id"
                :node="child"
                :inputs="inputs"
                :warehouses="warehouses"
                :errors="errors"
            />
        </div>
    </div>
</template>
