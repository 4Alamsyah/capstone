<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PartSupplier = {
    id: number;
    supplier_id: number;
    supplier_name: string | null;
    purchase_price: number;
};

type PartStock = {
    id: number;
    warehouse_id: number;
    warehouse_code: string | null;
    warehouse_name: string | null;
    quantity: number;
};

type WarehouseOption = {
    id: number;
    code: string;
    name: string;
};

type PartItem = {
    id: number;
    part_number: string;
    name: string;
    category: 'purchase' | 'manufacture';
    inventory_type: 'material' | 'tool';
    description: string | null;
    selling_price: number;
    safety_stock: number;
    total_stock: number;
    suppliers: PartSupplier[];
    stocks: PartStock[];
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

type DefaultCurrency = {
    code: string;
    symbol: string;
};

type Props = {
    parts: PartItem[];
    warehouses: WarehouseOption[];
    defaultCurrency: DefaultCurrency;
    filters: {
        search: string;
    };
    pagination: PaginationMeta;
};

const props = defineProps<Props>();
const editDialogOpen = ref(false);
const editingPartId = ref<number | null>(null);

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const editForm = useForm({
    part_number: '',
    name: '',
    category: 'purchase' as 'purchase' | 'manufacture',
    inventory_type: 'material' as 'material' | 'tool',
    description: '',
    selling_price: 0,
    safety_stock: 0,
    suppliers: [] as Array<{ supplier_id: number; supplier_name: string | null; purchase_price: number }>,
    stocks: [] as Array<{ warehouse_id: number; warehouse_code: string; warehouse_name: string; quantity: number }>,
    search: props.filters.search ?? '',
    page: props.pagination.current_page,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Part List',
        href: '/parts',
    },
];

const formatCurrency = (value: number): string => {
    const formatted = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(value || 0);

    return `${props.defaultCurrency.symbol} ${formatted}`;
};

const formatCategory = (category: 'purchase' | 'manufacture'): string => {
    return category === 'manufacture' ? 'Manufacture Part' : 'Purchase Part';
};

const formatInventoryType = (inventoryType: 'material' | 'tool'): string => {
    return inventoryType === 'tool' ? 'Tool (Borrow/Return)' : 'Material (Consumable)';
};

const isEditSupplierRequired = computed(() => editForm.category === 'purchase');

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No part data';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} parts`;
});

const submitSearch = () => {
    searchForm.get('/parts', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    submitSearch();
};

const openEditDialog = (part: PartItem) => {
    const stockByWarehouseId = new Map(part.stocks.map((stock) => [stock.warehouse_id, stock.quantity]));

    editingPartId.value = part.id;
    editForm.part_number = part.part_number;
    editForm.name = part.name;
    editForm.category = part.category;
    editForm.inventory_type = part.inventory_type;
    editForm.description = part.description ?? '';
    editForm.selling_price = part.selling_price;
    editForm.safety_stock = part.safety_stock;
    editForm.suppliers = part.suppliers.map((supplier) => ({
        supplier_id: supplier.supplier_id,
        supplier_name: supplier.supplier_name,
        purchase_price: supplier.purchase_price,
    }));
    editForm.stocks = props.warehouses.map((warehouse) => ({
        warehouse_id: warehouse.id,
        warehouse_code: warehouse.code,
        warehouse_name: warehouse.name,
        quantity: stockByWarehouseId.get(warehouse.id) ?? 0,
    }));
    editForm.search = props.filters.search ?? '';
    editForm.page = props.pagination.current_page;
    editForm.clearErrors();
    editDialogOpen.value = true;
};

const closeEditDialog = () => {
    editDialogOpen.value = false;
    editingPartId.value = null;
    editForm.clearErrors();
};

const getSupplierPriceError = (index: number): string | undefined => {
    return editForm.errors[`suppliers.${index}.purchase_price`];
};

const getStockQuantityError = (index: number): string | undefined => {
    return editForm.errors[`stocks.${index}.quantity`];
};

const submitEdit = () => {
    if (!editingPartId.value) {
        return;
    }

    editForm.put(`/parts/${editingPartId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditDialog();
        },
    });
};

const deletePart = (part: PartItem) => {
    if (!window.confirm(`Hapus part ${part.name}?`)) {
        return;
    }

    useForm({
        search: props.filters.search ?? '',
        page: props.pagination.current_page,
    }).delete(`/parts/${part.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Part List" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Part List" description="Daftar part, supplier + harga beli, harga jual, dan total stok." />
                <div class="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
                    <form class="flex w-full flex-wrap items-center gap-2 sm:w-auto" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search part..." class="w-full sm:w-72" />
                        <Button type="submit" variant="outline">Search</Button>
                        <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                    </form>
                    <Button as-child>
                        <Link href="/parts/register">Register New Part</Link>
                    </Button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-sidebar-border/70">
                <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                    <thead class="bg-sidebar-accent/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Part Number</th>
                            <th class="px-4 py-3 font-medium">Part Name</th>
                            <th class="px-4 py-3 font-medium">Category</th>
                            <th class="px-4 py-3 font-medium">Inventory Type</th>
                            <th class="px-4 py-3 font-medium">Supplier Prices</th>
                            <th class="px-4 py-3 font-medium">Selling Price</th>
                            <th class="px-4 py-3 font-medium">Safety Stock</th>
                            <th class="px-4 py-3 font-medium">Total Stock</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="part in parts" :key="part.id" class="align-top">
                            <td class="px-4 py-3 font-medium">{{ part.part_number }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ part.name }}</p>
                                <p v-if="part.description" class="text-xs text-muted-foreground">
                                    {{ part.description }}
                                </p>
                            </td>
                            <td class="px-4 py-3">{{ formatCategory(part.category) }}</td>
                            <td class="px-4 py-3">{{ formatInventoryType(part.inventory_type) }}</td>
                            <td class="px-4 py-3">
                                <ul v-if="part.suppliers.length" class="space-y-1">
                                    <li v-for="supplier in part.suppliers" :key="supplier.id">
                                        {{ supplier.supplier_name || 'Unknown Supplier' }}:
                                        {{ formatCurrency(supplier.purchase_price) }}
                                    </li>
                                </ul>
                                <p v-else class="text-muted-foreground">No supplier price.</p>
                            </td>
                            <td class="px-4 py-3">{{ formatCurrency(part.selling_price) }}</td>
                            <td class="px-4 py-3">{{ part.safety_stock }}</td>
                            <td class="px-4 py-3">{{ part.total_stock }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="openEditDialog(part)">
                                        Edit
                                    </Button>
                                    <Button type="button" variant="destructive" size="sm" @click="deletePart(part)">
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!parts.length">
                            <td colspan="9" class="px-4 py-8 text-center text-muted-foreground">
                                Data part tidak ditemukan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 px-4 py-3">
                <p class="text-sm text-muted-foreground">{{ paginationText }}</p>
                <div class="flex items-center gap-2">
                    <Button v-if="pagination.prev_page_url" variant="outline" as-child>
                        <Link :href="pagination.prev_page_url">Previous</Link>
                    </Button>
                    <span class="text-sm text-muted-foreground">Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
                    <Button v-if="pagination.next_page_url" variant="outline" as-child>
                        <Link :href="pagination.next_page_url">Next</Link>
                    </Button>
                </div>
            </div>

            <Dialog :open="editDialogOpen" @update:open="editDialogOpen = $event">
                <DialogContent class="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Edit Part</DialogTitle>
                        <DialogDescription>
                            Ubah data part dan harga supplier langsung dari daftar part.
                        </DialogDescription>
                    </DialogHeader>

                    <form class="space-y-5" @submit.prevent="submitEdit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="edit-part-number">Part Number</Label>
                                <Input id="edit-part-number" v-model="editForm.part_number" />
                                <InputError :message="editForm.errors.part_number" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-name">Part Name</Label>
                                <Input id="edit-name" v-model="editForm.name" />
                                <InputError :message="editForm.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-category">Part Category</Label>
                                <select
                                    id="edit-category"
                                    v-model="editForm.category"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="purchase">Purchase Part</option>
                                    <option value="manufacture">Manufacture Part</option>
                                </select>
                                <InputError :message="editForm.errors.category" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-inventory-type">Inventory Type</Label>
                                <select
                                    id="edit-inventory-type"
                                    v-model="editForm.inventory_type"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="material">Material (Consumable)</option>
                                    <option value="tool">Tool (Borrow/Return)</option>
                                </select>
                                <InputError :message="editForm.errors.inventory_type" />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="edit-description">Description</Label>
                                <textarea
                                    id="edit-description"
                                    v-model="editForm.description"
                                    rows="3"
                                    class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                />
                                <InputError :message="editForm.errors.description" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-selling-price">Selling Price</Label>
                                <Input id="edit-selling-price" v-model.number="editForm.selling_price" type="number" min="0" step="0.01" />
                                <InputError :message="editForm.errors.selling_price" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-safety-stock">Safety Stock</Label>
                                <Input id="edit-safety-stock" v-model.number="editForm.safety_stock" type="number" min="0" step="1" />
                                <InputError :message="editForm.errors.safety_stock" />
                            </div>
                        </div>

                        <div class="space-y-3 rounded-lg border border-sidebar-border/70 p-4">
                            <div>
                                <h3 class="text-sm font-semibold">Supplier Purchase Prices</h3>
                                <p class="text-sm text-muted-foreground">
                                    Harga beli per supplier yang sudah terhubung ke part ini.
                                    <template v-if="isEditSupplierRequired">Wajib diisi untuk Purchase Part.</template>
                                    <template v-else>Opsional untuk Manufacture Part.</template>
                                </p>
                            </div>
                            <p v-if="!editForm.suppliers.length" class="text-sm text-muted-foreground">
                                Belum ada supplier terhubung ke part ini.
                            </p>
                            <InputError :message="editForm.errors.suppliers" />

                            <div
                                v-for="(supplier, index) in editForm.suppliers"
                                :key="`${supplier.supplier_id}-${index}`"
                                class="grid gap-3 rounded-md border border-sidebar-border/50 p-3 md:grid-cols-[1fr_220px]"
                            >
                                <div class="grid gap-2">
                                    <Label :for="`edit-supplier-price-${supplier.supplier_id}`">
                                        {{ supplier.supplier_name || 'Unknown Supplier' }}
                                    </Label>
                                    <p class="text-xs text-muted-foreground">Purchase price supplier ini.</p>
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`edit-supplier-price-${supplier.supplier_id}`">Purchase Price</Label>
                                    <Input
                                        :id="`edit-supplier-price-${supplier.supplier_id}`"
                                        v-model.number="supplier.purchase_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                    <InputError :message="getSupplierPriceError(index)" />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 rounded-lg border border-sidebar-border/70 p-4">
                            <div>
                                <h3 class="text-sm font-semibold">Direct Stock</h3>
                                <p class="text-sm text-muted-foreground">Edit stok part ini langsung per warehouse dari popup ini.</p>
                            </div>

                            <div
                                v-for="(stock, index) in editForm.stocks"
                                :key="`${stock.warehouse_id}-${index}`"
                                class="grid gap-3 rounded-md border border-sidebar-border/50 p-3 md:grid-cols-[1fr_220px]"
                            >
                                <div class="grid gap-2">
                                    <Label :for="`edit-stock-${stock.warehouse_id}`">
                                        {{ stock.warehouse_code }} - {{ stock.warehouse_name }}
                                    </Label>
                                    <p class="text-xs text-muted-foreground">Quantity di warehouse ini.</p>
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`edit-stock-${stock.warehouse_id}`">Quantity</Label>
                                    <Input
                                        :id="`edit-stock-${stock.warehouse_id}`"
                                        v-model.number="stock.quantity"
                                        type="number"
                                        min="0"
                                        step="1"
                                    />
                                    <InputError :message="getStockQuantityError(index)" />
                                </div>
                            </div>
                        </div>

                        <DialogFooter class="gap-2">
                            <Button type="button" variant="outline" @click="closeEditDialog">Cancel</Button>
                            <Button type="submit" :disabled="editForm.processing">Save Changes</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
