<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type StockItem = {
    id: number;
    part_number: string | null;
    part_name: string | null;
    inventory_type: 'material' | 'tool' | null;
    warehouse_code: string | null;
    warehouse_name: string | null;
    quantity: number;
};

type StockSummary = {
    warehouse_code: string | null;
    warehouse_name: string | null;
    inventory_type: 'material' | 'tool' | null;
    total_quantity: number;
};

type StockHistoryItem = {
    id: number;
    part_number: string | null;
    part_name: string | null;
    inventory_type: 'material' | 'tool' | null;
    warehouse_code: string | null;
    warehouse_name: string | null;
    work_order_id: number | null;
    wo_number: string | null;
    tool_loan_id: number | null;
    borrower_name: string | null;
    movement_type: string;
    quantity_change: number;
    notes: string | null;
    created_at: string;
};

type ActiveToolLoan = {
    id: number;
    part_id: number;
    part_number: string | null;
    part_name: string | null;
    warehouse_id: number;
    warehouse_code: string | null;
    warehouse_name: string | null;
    borrower_name: string;
    borrowed_quantity: number;
    returned_quantity: number;
    remaining_quantity: number;
    borrowed_at: string | null;
    due_at: string | null;
    notes: string | null;
};

type ToolPartOption = {
    id: number;
    part_number: string;
    name: string;
};

type WarehouseOption = {
    id: number;
    code: string;
    name: string;
};

type Props = {
    stocks: StockItem[];
    summary: StockSummary[];
    history: StockHistoryItem[];
    active_tool_loans: ActiveToolLoan[];
    tool_parts: ToolPartOption[];
    warehouses: WarehouseOption[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Stock',
        href: '/parts/stock',
    },
];

const borrowForm = useForm({
    part_id: props.tool_parts[0]?.id ?? null,
    warehouse_id: props.warehouses[0]?.id ?? null,
    borrowed_quantity: 1,
    borrower_name: '',
    due_at: '',
    notes: '',
});

const returnDrafts = reactive<Record<number, { quantity: number; notes: string }>>({});

const materialStocks = computed(() => props.stocks.filter((item) => item.inventory_type === 'material'));
const toolStocks = computed(() => props.stocks.filter((item) => item.inventory_type === 'tool'));

const formatInventoryType = (inventoryType: 'material' | 'tool' | null): string => {
    if (inventoryType === 'tool') {
        return 'Tool';
    }

    if (inventoryType === 'material') {
        return 'Material';
    }

    return '-';
};

const ensureReturnDraft = (loanId: number, remainingQuantity: number) => {
    if (returnDrafts[loanId]) {
        return;
    }

    returnDrafts[loanId] = {
        quantity: Math.max(1, remainingQuantity),
        notes: '',
    };
};

const submitBorrow = () => {
    borrowForm.post('/parts/stock/tool-loans', {
        preserveScroll: true,
        onSuccess: () => {
            borrowForm.borrowed_quantity = 1;
            borrowForm.borrower_name = '';
            borrowForm.due_at = '';
            borrowForm.notes = '';
        },
    });
};

const getReturnQuantity = (loanId: number, remainingQuantity: number): number => {
    ensureReturnDraft(loanId, remainingQuantity);

    return returnDrafts[loanId].quantity;
};

const updateReturnQuantity = (loanId: number, remainingQuantity: number, value: string | number): void => {
    ensureReturnDraft(loanId, remainingQuantity);
    const parsed = Number(value);

    if (!Number.isFinite(parsed)) {
        return;
    }

    returnDrafts[loanId].quantity = Math.max(1, Math.min(remainingQuantity, Math.floor(parsed)));
};

const getReturnNotes = (loanId: number, remainingQuantity: number): string => {
    ensureReturnDraft(loanId, remainingQuantity);

    return returnDrafts[loanId].notes;
};

const updateReturnNotes = (loanId: number, remainingQuantity: number, value: string | number): void => {
    ensureReturnDraft(loanId, remainingQuantity);
    returnDrafts[loanId].notes = String(value);
};

const submitReturn = (loan: ActiveToolLoan) => {
    ensureReturnDraft(loan.id, loan.remaining_quantity);
    const row = returnDrafts[loan.id];

    useForm({
        returned_quantity: row.quantity,
        return_notes: row.notes,
    }).patch(`/parts/stock/tool-loans/${loan.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading
                title="Stock by Warehouse"
                description="Bedakan material (habis pakai) dan tools (pinjam-kembali) dari satu dashboard."
            />

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-3 text-sm font-semibold">Warehouse Summary by Inventory Type</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                            <thead class="bg-sidebar-accent/40 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Warehouse</th>
                                    <th class="px-3 py-2 font-medium">Type</th>
                                    <th class="px-3 py-2 font-medium">Total Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="warehouse in summary" :key="warehouse.warehouse_code || warehouse.warehouse_name || 'warehouse'">
                                    <td class="px-3 py-2">
                                        {{ warehouse.warehouse_code }} - {{ warehouse.warehouse_name }}
                                    </td>
                                    <td class="px-3 py-2">{{ formatInventoryType(warehouse.inventory_type) }}</td>
                                    <td class="px-3 py-2">{{ warehouse.total_quantity }}</td>
                                </tr>
                                <tr v-if="!summary.length">
                                    <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">
                                        Belum ada data stok warehouse.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-3 text-sm font-semibold">Material Stock (Consumable)</h3>
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
                                <tr v-for="stock in materialStocks" :key="stock.id">
                                    <td class="px-3 py-2">
                                        {{ stock.part_number }} - {{ stock.part_name }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ stock.warehouse_code }} - {{ stock.warehouse_name }}
                                    </td>
                                    <td class="px-3 py-2">{{ stock.quantity }}</td>
                                </tr>
                                <tr v-if="!materialStocks.length">
                                    <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">
                                        Belum ada stok material.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4 md:col-span-2">
                    <h3 class="mb-3 text-sm font-semibold">Tool Stock (Borrow/Return)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                            <thead class="bg-sidebar-accent/40 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Tool</th>
                                    <th class="px-3 py-2 font-medium">Warehouse</th>
                                    <th class="px-3 py-2 font-medium">Available Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="stock in toolStocks" :key="`tool-${stock.id}`">
                                    <td class="px-3 py-2">
                                        {{ stock.part_number }} - {{ stock.part_name }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ stock.warehouse_code }} - {{ stock.warehouse_name }}
                                    </td>
                                    <td class="px-3 py-2">{{ stock.quantity }}</td>
                                </tr>
                                <tr v-if="!toolStocks.length">
                                    <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">
                                        Belum ada stok tools.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4 md:col-span-2">
                    <h3 class="mb-3 text-sm font-semibold">Borrow Tool</h3>
                    <form class="grid gap-3 md:grid-cols-2 lg:grid-cols-3" @submit.prevent="submitBorrow">
                        <div class="grid gap-2">
                            <Label for="borrow-tool-part">Tool</Label>
                            <select
                                id="borrow-tool-part"
                                v-model.number="borrowForm.part_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                            >
                                <option v-for="part in tool_parts" :key="part.id" :value="part.id">
                                    {{ part.part_number }} - {{ part.name }}
                                </option>
                            </select>
                            <InputError :message="borrowForm.errors.part_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="borrow-tool-warehouse">Warehouse</Label>
                            <select
                                id="borrow-tool-warehouse"
                                v-model.number="borrowForm.warehouse_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                            >
                                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                                    {{ warehouse.code }} - {{ warehouse.name }}
                                </option>
                            </select>
                            <InputError :message="borrowForm.errors.warehouse_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="borrow-tool-qty">Qty Borrow</Label>
                            <Input id="borrow-tool-qty" v-model.number="borrowForm.borrowed_quantity" type="number" min="1" />
                            <InputError :message="borrowForm.errors.borrowed_quantity" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="borrow-tool-borrower">Borrower</Label>
                            <Input id="borrow-tool-borrower" v-model="borrowForm.borrower_name" placeholder="Nama peminjam" />
                            <InputError :message="borrowForm.errors.borrower_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="borrow-tool-due-date">Due Date</Label>
                            <Input id="borrow-tool-due-date" v-model="borrowForm.due_at" type="date" />
                            <InputError :message="borrowForm.errors.due_at" />
                        </div>

                        <div class="grid gap-2 lg:col-span-3">
                            <Label for="borrow-tool-notes">Notes</Label>
                            <textarea
                                id="borrow-tool-notes"
                                v-model="borrowForm.notes"
                                rows="2"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                placeholder="Catatan peminjaman"
                            />
                            <InputError :message="borrowForm.errors.notes" />
                        </div>

                        <div class="lg:col-span-3">
                            <Button type="submit" :disabled="borrowForm.processing">Simpan Peminjaman Tool</Button>
                        </div>
                    </form>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4 md:col-span-2">
                    <h3 class="mb-3 text-sm font-semibold">Active Tool Loans</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                            <thead class="bg-sidebar-accent/40 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Tool</th>
                                    <th class="px-3 py-2 font-medium">Borrower</th>
                                    <th class="px-3 py-2 font-medium">Warehouse</th>
                                    <th class="px-3 py-2 font-medium">Borrowed</th>
                                    <th class="px-3 py-2 font-medium">Returned</th>
                                    <th class="px-3 py-2 font-medium">Remaining</th>
                                    <th class="px-3 py-2 font-medium">Due</th>
                                    <th class="px-3 py-2 font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="loan in active_tool_loans" :key="loan.id">
                                    <td class="px-3 py-2">{{ loan.part_number }} - {{ loan.part_name }}</td>
                                    <td class="px-3 py-2">{{ loan.borrower_name }}</td>
                                    <td class="px-3 py-2">{{ loan.warehouse_code }} - {{ loan.warehouse_name }}</td>
                                    <td class="px-3 py-2">{{ loan.borrowed_quantity }}</td>
                                    <td class="px-3 py-2">{{ loan.returned_quantity }}</td>
                                    <td class="px-3 py-2">{{ loan.remaining_quantity }}</td>
                                    <td class="px-3 py-2">{{ loan.due_at ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="grid gap-2 md:grid-cols-[100px_1fr_auto]">
                                            <Input
                                                :model-value="getReturnQuantity(loan.id, loan.remaining_quantity)"
                                                type="number"
                                                min="1"
                                                :max="loan.remaining_quantity"
                                                @update:model-value="updateReturnQuantity(loan.id, loan.remaining_quantity, $event)"
                                            />
                                            <Input
                                                :model-value="getReturnNotes(loan.id, loan.remaining_quantity)"
                                                placeholder="Catatan return"
                                                @update:model-value="updateReturnNotes(loan.id, loan.remaining_quantity, $event)"
                                            />
                                            <Button type="button" variant="outline" @click="submitReturn(loan)">
                                                Return
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!active_tool_loans.length">
                                    <td colspan="8" class="px-3 py-6 text-center text-muted-foreground">
                                        Tidak ada tools yang sedang dipinjam.
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
                                    <th class="px-3 py-2 font-medium">Type</th>
                                    <th class="px-3 py-2 font-medium">Warehouse</th>
                                    <th class="px-3 py-2 font-medium">Qty Change</th>
                                    <th class="px-3 py-2 font-medium">Movement</th>
                                    <th class="px-3 py-2 font-medium">MO</th>
                                    <th class="px-3 py-2 font-medium">Borrower</th>
                                    <th class="px-3 py-2 font-medium">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="item in history" :key="item.id">
                                    <td class="px-3 py-2">{{ item.created_at }}</td>
                                    <td class="px-3 py-2">{{ item.part_number }} - {{ item.part_name }}</td>
                                    <td class="px-3 py-2">{{ formatInventoryType(item.inventory_type) }}</td>
                                    <td class="px-3 py-2">{{ item.warehouse_code }} - {{ item.warehouse_name }}</td>
                                    <td class="px-3 py-2" :class="item.quantity_change < 0 ? 'text-red-600' : 'text-green-600'">
                                        {{ item.quantity_change }}
                                    </td>
                                    <td class="px-3 py-2">{{ item.movement_type }}</td>
                                    <td class="px-3 py-2">
                                        <Link v-if="item.work_order_id" :href="`/work-orders/${item.work_order_id}`" class="text-primary underline-offset-4 hover:underline">
                                            {{ item.wo_number }}
                                        </Link>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-3 py-2">{{ item.borrower_name ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ item.notes ?? '-' }}</td>
                                </tr>
                                <tr v-if="!history.length">
                                    <td colspan="9" class="px-3 py-6 text-center text-muted-foreground">
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
