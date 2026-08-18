<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import WarehouseCombobox from '@/components/WarehouseCombobox.vue';
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
import 'select2/dist/css/select2.min.css';

type WarehouseOption = {
    id: number;
    code: string;
    name: string;
    location: string | null;
};

type SupplierOption = {
    id: number;
    name: string;
};

type UomOption = {
    id: number;
    code: string;
    name: string;
};

type DefaultCurrency = {
    code: string;
    symbol: string;
};

type Props = {
    warehouses: WarehouseOption[];
    suppliers: SupplierOption[];
    uoms: UomOption[];
    defaultCurrency: DefaultCurrency;
};

const props = defineProps<Props>();
const supplierSelectRef = ref<HTMLSelectElement | null>(null);
const selectedSupplierIds = ref<number[]>([]);
let supplierSelectJQuery: any = null;

const warehouseOptions = ref<WarehouseOption[]>([...props.warehouses]);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Register Part',
        href: '/parts/register',
    },
];

const form = useForm({
    part_number: '',
    name: '',
    category: 'purchase',
    inventory_type: 'material' as 'material' | 'tool',
    default_uom_id: null as number | null,
    description: '',
    selling_price: 0,
    safety_stock: 0,
    suppliers: [] as Array<{ supplier_id: number; purchase_price: number }>,
    stocks: [
        {
            warehouse_id: warehouseOptions.value[0]?.id ?? 0,
            quantity: 0,
        },
    ],
});

const selectedSuppliers = computed(() => {
    return props.suppliers.filter((supplier) => selectedSupplierIds.value.includes(supplier.id));
});

const formatCurrency = (value: number): string => {
    const formatted = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(value || 0);

    return `${props.defaultCurrency.symbol} ${formatted}`;
};

const isSupplierRequired = computed(() => form.category === 'purchase');

const syncSuppliersSelection = (supplierIds: number[]) => {
    const previousPurchasePriceBySupplier = new Map(
        form.suppliers.map((supplierRow) => [supplierRow.supplier_id, supplierRow.purchase_price]),
    );

    form.suppliers = supplierIds.map((supplierId) => ({
        supplier_id: supplierId,
        purchase_price: previousPurchasePriceBySupplier.get(supplierId) ?? 0,
    }));
};

const updateSupplierPrice = (supplierId: number, value: number) => {
    const target = form.suppliers.find((supplierRow) => supplierRow.supplier_id === supplierId);

    if (!target) {
        return;
    }

    target.purchase_price = Number.isFinite(value) ? value : 0;
};

const getSupplierErrorBySupplierId = (supplierId: number): string | undefined => {
    const rowIndex = form.suppliers.findIndex((supplierRow) => supplierRow.supplier_id === supplierId);

    if (rowIndex < 0) {
        return undefined;
    }

    return form.errors[`suppliers.${rowIndex}.purchase_price`];
};

watch(selectedSupplierIds, (supplierIds) => {
    syncSuppliersSelection(supplierIds);
});

onMounted(async () => {
    if (!supplierSelectRef.value) {
        return;
    }

    const jqueryModule = await import('jquery');
    const jquery = jqueryModule.default;
    (window as any).$ = jquery;
    (window as any).jQuery = jquery;

    const select2Module = await import('select2');
    const attachSelect2 = select2Module.default;

    if (typeof attachSelect2 === 'function') {
        attachSelect2(window, jquery);
    }

    supplierSelectJQuery = jquery(supplierSelectRef.value);
    supplierSelectJQuery.select2({
        placeholder: 'Pilih supplier',
        width: '100%',
        allowClear: true,
    });

    supplierSelectJQuery.on('change', () => {
        const rawValue = supplierSelectJQuery.val() as string[] | null;
        selectedSupplierIds.value = (rawValue ?? []).map((value) => Number(value));
    });
});

onBeforeUnmount(() => {
    if (!supplierSelectJQuery) {
        return;
    }

    supplierSelectJQuery.off('change');
    supplierSelectJQuery.select2('destroy');
    supplierSelectJQuery = null;
});

const addStockRow = () => {
    form.stocks.push({
        warehouse_id: warehouseOptions.value[0]?.id ?? 0,
        quantity: 0,
    });
};

const removeStockRow = (index: number) => {
    form.stocks.splice(index, 1);
};

const submit = () => {
    form.post('/parts');
};

const warehouseDialogOpen = ref(false);
const pendingStockIndex = ref<number | null>(null);
const quickWarehouseForm = reactive({
    code: '',
    name: '',
    location: '',
});
const quickWarehouseErrors = ref<Record<string, string[]>>({});
const quickWarehouseProcessing = ref(false);
const quickWarehouseGeneralError = ref('');

const closeWarehouseDialog = () => {
    warehouseDialogOpen.value = false;
    pendingStockIndex.value = null;
    quickWarehouseErrors.value = {};
    quickWarehouseGeneralError.value = '';
};

const requestCreateWarehouse = (rowIndex: number, typedName: string) => {
    pendingStockIndex.value = rowIndex;
    quickWarehouseForm.code = '';
    quickWarehouseForm.name = typedName;
    quickWarehouseForm.location = '';
    quickWarehouseErrors.value = {};
    quickWarehouseGeneralError.value = '';
    warehouseDialogOpen.value = true;
};

const submitQuickWarehouse = async () => {
    quickWarehouseProcessing.value = true;
    quickWarehouseErrors.value = {};
    quickWarehouseGeneralError.value = '';

    try {
        const response = await axios.post('/parts/warehouses/quick-create', quickWarehouseForm, {
            headers: { Accept: 'application/json' },
        });

        warehouseOptions.value.push(response.data);

        if (pendingStockIndex.value !== null) {
            form.stocks[pendingStockIndex.value].warehouse_id = response.data.id;
        }

        warehouseDialogOpen.value = false;
        pendingStockIndex.value = null;
    } catch (error: any) {
        if (error.response?.status === 422) {
            quickWarehouseErrors.value = error.response.data.errors ?? {};
        } else {
            quickWarehouseGeneralError.value = 'Sesi habis atau terjadi kesalahan, silakan muat ulang halaman.';
        }
    } finally {
        quickWarehouseProcessing.value = false;
    }
};
</script>

<template>
    <Head title="Register Part" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
            <Heading
                title="Register Part"
                description="Input part baru, supplier + harga beli, harga jual, safety stock, dan stok awal per warehouse."
            />

            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid gap-4 rounded-lg border border-sidebar-border/70 p-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="part_number">Part Number</Label>
                        <Input id="part_number" v-model="form.part_number" placeholder="contoh: BRG-0001" />
                        <InputError :message="form.errors.part_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Part Name</Label>
                        <Input id="name" v-model="form.name" placeholder="Nama part" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="category">Part Category</Label>
                        <select
                            id="category"
                            v-model="form.category"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="purchase">Purchase Part</option>
                            <option value="manufacture">Manufacture Part</option>
                        </select>
                        <InputError :message="form.errors.category" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="inventory_type">Inventory Type</Label>
                        <select
                            id="inventory_type"
                            v-model="form.inventory_type"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="material">Material (Consumable)</option>
                            <option value="tool">Tool (Borrow/Return)</option>
                        </select>
                        <InputError :message="form.errors.inventory_type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="default_uom_id">Default UOM</Label>
                        <select
                            id="default_uom_id"
                            v-model="form.default_uom_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option :value="null">- pilih satuan -</option>
                            <option v-for="uom in uoms" :key="uom.id" :value="uom.id">
                                {{ uom.code }} - {{ uom.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.default_uom_id" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Deskripsi part"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="selling_price">Selling Price</Label>
                        <Input id="selling_price" v-model.number="form.selling_price" type="number" min="0" step="0.01" />
                        <p class="text-sm text-muted-foreground">{{ formatCurrency(form.selling_price) }}</p>
                        <InputError :message="form.errors.selling_price" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="safety_stock">Safety Stock</Label>
                        <Input id="safety_stock" v-model.number="form.safety_stock" type="number" min="0" step="1" />
                        <InputError :message="form.errors.safety_stock" />
                    </div>
                </div>

                <div class="space-y-3 rounded-lg border border-sidebar-border/70 p-4">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold">Suppliers (Select2 Multiple)</h3>
                        <p class="text-sm text-muted-foreground">
                            Tambah supplier dari menu Supplier terlebih dahulu, lalu pilih lebih dari satu supplier di sini.
                            <template v-if="isSupplierRequired">Wajib diisi untuk Purchase Part.</template>
                            <template v-else>Opsional untuk Manufacture Part.</template>
                        </p>
                    </div>

                    <div class="grid gap-2"
                    >
                        <Label for="suppliers">Supplier<span v-if="isSupplierRequired" class="text-destructive"> *</span></Label>
                        <select
                            id="suppliers"
                            ref="supplierSelectRef"
                            multiple
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                        >
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.suppliers" />
                    </div>

                    <div
                        v-for="supplier in selectedSuppliers"
                        :key="`supplier-price-${supplier.id}`"
                        class="grid gap-3 rounded-md border border-sidebar-border/50 p-3 md:grid-cols-[1fr_220px]"
                    >
                        <div class="grid gap-2">
                            <Label :for="`supplier-price-${supplier.id}`">{{ supplier.name }}</Label>
                            <p class="text-xs text-muted-foreground">Purchase price untuk supplier ini.</p>
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`supplier-price-${supplier.id}`">Purchase Price</Label>
                            <Input
                                :id="`supplier-price-${supplier.id}`"
                                :model-value="
                                    form.suppliers.find((row) => row.supplier_id === supplier.id)?.purchase_price ?? 0
                                "
                                type="number"
                                min="0"
                                step="0.01"
                                @update:model-value="
                                    updateSupplierPrice(supplier.id, Number($event))
                                "
                            />
                            <p class="text-sm text-muted-foreground">
                                {{ formatCurrency(form.suppliers.find((row) => row.supplier_id === supplier.id)?.purchase_price ?? 0) }}
                            </p>
                            <InputError :message="getSupplierErrorBySupplierId(supplier.id)" />
                        </div>
                    </div>

                    <p v-if="!selectedSuppliers.length" class="text-sm text-muted-foreground">
                        Belum ada supplier dipilih.
                    </p>
                </div>

                <div class="space-y-3 rounded-lg border border-sidebar-border/70 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold">Initial Stock</h3>
                        <Button type="button" variant="outline" @click="addStockRow">
                            Add Warehouse Stock
                        </Button>
                    </div>

                    <p v-if="!warehouseOptions.length" class="text-sm text-muted-foreground">
                        Belum ada warehouse. Ketik nama warehouse di kolom pencarian di bawah untuk menambahkannya.
                    </p>

                    <div
                        v-for="(stock, index) in form.stocks"
                        :key="`stock-${index}`"
                        class="grid gap-3 rounded-md border border-sidebar-border/50 p-3 md:grid-cols-[1fr_220px_auto]"
                    >
                        <div class="grid gap-2">
                            <Label :for="`warehouse-${index}`">Warehouse</Label>
                            <WarehouseCombobox
                                :id="`warehouse-${index}`"
                                v-model="stock.warehouse_id"
                                :warehouses="warehouseOptions"
                                @create-requested="(name) => requestCreateWarehouse(index, name)"
                            />
                            <InputError :message="form.errors[`stocks.${index}.warehouse_id`]" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`quantity-${index}`">Quantity</Label>
                            <Input
                                :id="`quantity-${index}`"
                                v-model.number="stock.quantity"
                                type="number"
                                min="0"
                                step="1"
                            />
                            <InputError :message="form.errors[`stocks.${index}.quantity`]" />
                        </div>

                        <div class="flex items-end">
                            <Button type="button" variant="ghost" class="w-full" @click="removeStockRow(index)">
                                Remove
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">Save Part</Button>
                </div>
            </form>

            <Dialog
                :open="warehouseDialogOpen"
                @update:open="(open) => (open ? (warehouseDialogOpen = true) : closeWarehouseDialog())"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Add Warehouse</DialogTitle>
                        <DialogDescription>
                            Tambahkan warehouse baru, langsung terpilih untuk baris stok ini.
                        </DialogDescription>
                    </DialogHeader>

                    <form class="grid gap-4" @submit.prevent="submitQuickWarehouse">
                        <p v-if="quickWarehouseGeneralError" class="text-sm text-destructive">
                            {{ quickWarehouseGeneralError }}
                        </p>

                        <div class="grid gap-2">
                            <Label for="quick-warehouse-code">Code</Label>
                            <Input id="quick-warehouse-code" v-model="quickWarehouseForm.code" placeholder="WH-JKT" />
                            <InputError :message="quickWarehouseErrors.code?.[0]" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="quick-warehouse-name">Name</Label>
                            <Input id="quick-warehouse-name" v-model="quickWarehouseForm.name" placeholder="Warehouse Jakarta" />
                            <InputError :message="quickWarehouseErrors.name?.[0]" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="quick-warehouse-location">Location</Label>
                            <Input id="quick-warehouse-location" v-model="quickWarehouseForm.location" placeholder="Opsional" />
                            <InputError :message="quickWarehouseErrors.location?.[0]" />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeWarehouseDialog">Cancel</Button>
                            <Button type="submit" :disabled="quickWarehouseProcessing">Save Warehouse</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.select2-container) {
    width: 100% !important;
}

:deep(.select2-container--default .select2-selection--multiple) {
    min-height: 38px;
    border-radius: 0.375rem;
    border: 1px solid var(--input);
    background: transparent;
    padding: 2px 8px;
}

:deep(.select2-container--default.select2-container--focus .select2-selection--multiple) {
    border-color: var(--ring);
    box-shadow: 0 0 0 1px var(--ring);
}

:deep(.select2-container--default .select2-selection--multiple .select2-selection__choice) {
    border: 0;
    border-radius: 0.375rem;
    background: var(--muted);
    color: var(--foreground);
    padding: 2px 8px;
    margin-top: 4px;
}

:deep(.select2-container--default .select2-selection--multiple .select2-selection__choice__remove) {
    margin-right: 6px;
    color: var(--foreground);
}
</style>

<!--
    Select2 renders its open dropdown in a floating container appended near
    the end of <body>, outside this component's scoped DOM subtree, so
    Vue's scoped `:deep()` selectors never match it. These rules must stay
    global (unscoped). `!important` matches select2's own higher-specificity
    `.select2-container--default ...` base rules regardless of CSS load order.
-->
<style>
.select2-dropdown {
    background: var(--popover) !important;
    color: var(--popover-foreground) !important;
    border: 1px solid var(--border) !important;
    border-radius: 0.5rem !important;
    box-shadow:
        0 10px 15px -3px rgb(0 0 0 / 0.1),
        0 4px 6px -4px rgb(0 0 0 / 0.1);
    overflow: hidden;
}

.select2-search--dropdown {
    padding: 0.5rem !important;
}

.select2-search--dropdown .select2-search__field {
    border: 1px solid var(--input) !important;
    border-radius: 0.375rem;
    background: transparent;
    color: var(--foreground);
    padding: 0.375rem 0.5rem;
    outline: none;
}

.select2-results__option {
    color: var(--popover-foreground) !important;
    padding: 0.5rem 0.75rem !important;
}

.select2-results__option--selected {
    background: var(--muted) !important;
}

.select2-results__option--highlighted[aria-selected] {
    background: var(--accent) !important;
    color: var(--accent-foreground) !important;
}
</style>
