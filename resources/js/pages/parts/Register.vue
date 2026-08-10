<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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

type Props = {
    warehouses: WarehouseOption[];
    suppliers: SupplierOption[];
};

const props = defineProps<Props>();
const supplierSelectRef = ref<HTMLSelectElement | null>(null);
const selectedSupplierIds = ref<number[]>([]);
let supplierSelectJQuery: any = null;

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
    description: '',
    selling_price: 0,
    safety_stock: 0,
    suppliers: [] as Array<{ supplier_id: number; purchase_price: number }>,
    stocks: props.warehouses.length
        ? [
              {
                  warehouse_id: props.warehouses[0].id,
                  quantity: 0,
              },
          ]
        : [],
});

const selectedSuppliers = computed(() => {
    return props.suppliers.filter((supplier) => selectedSupplierIds.value.includes(supplier.id));
});

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
    if (!props.warehouses.length) {
        return;
    }

    form.stocks.push({
        warehouse_id: props.warehouses[0].id,
        quantity: 0,
    });
};

const removeStockRow = (index: number) => {
    form.stocks.splice(index, 1);
};

const submit = () => {
    form.post('/parts');
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
                        <Button type="button" variant="outline" :disabled="!warehouses.length" @click="addStockRow">
                            Add Warehouse Stock
                        </Button>
                    </div>

                    <p v-if="!warehouses.length" class="text-sm text-muted-foreground">
                        Belum ada warehouse. Tambahkan warehouse terlebih dahulu di database.
                    </p>

                    <div
                        v-for="(stock, index) in form.stocks"
                        :key="`stock-${index}`"
                        class="grid gap-3 rounded-md border border-sidebar-border/50 p-3 md:grid-cols-[1fr_220px_auto]"
                    >
                        <div class="grid gap-2">
                            <Label :for="`warehouse-${index}`">Warehouse</Label>
                            <select
                                :id="`warehouse-${index}`"
                                v-model.number="stock.warehouse_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                                    {{ warehouse.code }} - {{ warehouse.name }}
                                </option>
                            </select>
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
    border: 1px solid hsl(var(--input));
    background: transparent;
    padding: 2px 8px;
}

:deep(.select2-container--default.select2-container--focus .select2-selection--multiple) {
    border-color: hsl(var(--ring));
    box-shadow: 0 0 0 1px hsl(var(--ring));
}

:deep(.select2-container--default .select2-selection--multiple .select2-selection__choice) {
    border: 0;
    border-radius: 0.375rem;
    background: hsl(var(--muted));
    color: hsl(var(--foreground));
    padding: 2px 8px;
    margin-top: 4px;
}

:deep(.select2-container--default .select2-selection--multiple .select2-selection__choice__remove) {
    margin-right: 6px;
    color: hsl(var(--foreground));
}

:deep(.select2-dropdown) {
    border: 1px solid hsl(var(--border));
    border-radius: 0.5rem;
    background: hsl(var(--background));
}

:deep(.select2-results__option--highlighted[aria-selected]) {
    background: hsl(var(--accent)) !important;
    color: hsl(var(--accent-foreground)) !important;
}
</style>
