<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type StockItem = {
    id: number;
    part_number: string | null;
    part_name: string | null;
    warehouse_code: string | null;
    warehouse_name: string | null;
    quantity: number;
};

type StockSummary = {
    warehouse_code: string | null;
    warehouse_name: string | null;
    total_quantity: number;
};

type StockHistoryItem = {
    id: number;
    part_number: string | null;
    part_name: string | null;
    warehouse_code: string | null;
    warehouse_name: string | null;
    work_order_id: number | null;
    wo_number: string | null;
    movement_type: string;
    quantity_change: number;
    notes: string | null;
    created_at: string;
};

type Props = {
    stocks: StockItem[];
    summary: StockSummary[];
    history: StockHistoryItem[];
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Stock',
        href: '/parts/stock',
    },
];
</script>

<template>
    <Head title="Stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading
                title="Stock by Warehouse"
                description="Lokasi barang disimpan dan total stok per warehouse."
            />

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-3 text-sm font-semibold">Warehouse Summary</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                            <thead class="bg-sidebar-accent/40 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Warehouse</th>
                                    <th class="px-3 py-2 font-medium">Total Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="warehouse in summary" :key="warehouse.warehouse_code || warehouse.warehouse_name || 'warehouse'">
                                    <td class="px-3 py-2">
                                        {{ warehouse.warehouse_code }} - {{ warehouse.warehouse_name }}
                                    </td>
                                    <td class="px-3 py-2">{{ warehouse.total_quantity }}</td>
                                </tr>
                                <tr v-if="!summary.length">
                                    <td colspan="2" class="px-3 py-6 text-center text-muted-foreground">
                                        Belum ada data stok warehouse.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-3 text-sm font-semibold">Stock Detail</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                            <thead class="bg-sidebar-accent/40 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Part</th>
                                    <th class="px-3 py-2 font-medium">Warehouse</th>
                                    <th class="px-3 py-2 font-medium">Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="stock in stocks" :key="stock.id">
                                    <td class="px-3 py-2">
                                        {{ stock.part_number }} - {{ stock.part_name }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ stock.warehouse_code }} - {{ stock.warehouse_name }}
                                    </td>
                                    <td class="px-3 py-2">{{ stock.quantity }}</td>
                                </tr>
                                <tr v-if="!stocks.length">
                                    <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">
                                        Belum ada detail stok.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4 md:col-span-2">
                    <h3 class="mb-3 text-sm font-semibold">Stock Consumption History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                            <thead class="bg-sidebar-accent/40 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Date</th>
                                    <th class="px-3 py-2 font-medium">Part</th>
                                    <th class="px-3 py-2 font-medium">Warehouse</th>
                                    <th class="px-3 py-2 font-medium">Qty Change</th>
                                    <th class="px-3 py-2 font-medium">MO</th>
                                    <th class="px-3 py-2 font-medium">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="item in history" :key="item.id">
                                    <td class="px-3 py-2">{{ item.created_at }}</td>
                                    <td class="px-3 py-2">{{ item.part_number }} - {{ item.part_name }}</td>
                                    <td class="px-3 py-2">{{ item.warehouse_code }} - {{ item.warehouse_name }}</td>
                                    <td class="px-3 py-2" :class="item.quantity_change < 0 ? 'text-red-600' : 'text-green-600'">
                                        {{ item.quantity_change }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <Link v-if="item.work_order_id" :href="`/work-orders/${item.work_order_id}`" class="text-primary underline-offset-4 hover:underline">
                                            {{ item.wo_number }}
                                        </Link>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-3 py-2">{{ item.notes ?? '-' }}</td>
                                </tr>
                                <tr v-if="!history.length">
                                    <td colspan="6" class="px-3 py-6 text-center text-muted-foreground">
                                        Belum ada histori konsumsi stock.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
