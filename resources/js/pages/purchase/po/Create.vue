<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type SupplierOption = {
    id: number;
    name: string;
};

type PartOption = {
    id: number;
    part_number: string;
    name: string;
    supplier_prices: Array<{
        supplier_id: number;
        purchase_price: number;
    }>;
};

type CurrencyOption = {
    code: string;
    name: string;
};

type Props = {
    nextPoNumber: string;
    suppliers: SupplierOption[];
    parts: PartOption[];
    defaultCurrency: string;
    currencies: CurrencyOption[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'List PO', href: '/purchase/po' },
    { title: 'Register PO', href: '/purchase/po/create' },
];

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    supplier_id: '' as unknown as number,
    order_date: today,
    expected_date: '',
    currency_code: '',
    notes: '',
    lines: [
        {
            part_id: '' as unknown as number,
            quantity: '1',
            unit: 'PCS',
            unit_price: '0',
            remarks: '',
        },
    ],
});

const selectedSupplierId = computed(() => Number(form.supplier_id || 0));

const addLine = () => {
    form.lines.push({
        part_id: '' as unknown as number,
        quantity: '1',
        unit: 'PCS',
        unit_price: '0',
        remarks: '',
    });
};

const removeLine = (index: number) => {
    if (form.lines.length === 1) {
        return;
    }

    form.lines.splice(index, 1);
};

const choosePart = (index: number) => {
    const line = form.lines[index];
    const selectedPart = props.parts.find((part) => part.id === Number(line.part_id));

    if (!selectedPart) {
        return;
    }

    const supplierPrice = selectedPart.supplier_prices.find((entry) => entry.supplier_id === selectedSupplierId.value);

    if (supplierPrice) {
        line.unit_price = supplierPrice.purchase_price.toString();
    }
};

const lineTotal = (index: number): number => {
    const line = form.lines[index];

    return Number(line.quantity || 0) * Number(line.unit_price || 0);
};

const subtotal = computed(() => {
    return form.lines.reduce((sum, _, index) => sum + lineTotal(index), 0);
});

const submit = () => {
    form.post('/purchase/po');
};
</script>

<template>
    <Head title="Register PO" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Register PO" description="Buat purchase order baru ke supplier." />
                <Button variant="outline" as-child>
                    <Link href="/purchase/po">Back to List</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-5 flex flex-wrap items-center gap-3 rounded-md bg-muted/40 px-3 py-2">
                    <span class="text-xs text-muted-foreground">PO Number (auto)</span>
                    <span class="font-mono text-sm font-semibold">{{ props.nextPoNumber }}</span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="supplier_id">Supplier <span class="text-destructive">*</span></Label>
                            <select
                                id="supplier_id"
                                v-model="form.supplier_id"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">- pilih supplier -</option>
                                <option v-for="supplier in props.suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                            </select>
                            <InputError :message="form.errors.supplier_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="currency_code">Currency (Optional)</Label>
                            <select
                                id="currency_code"
                                v-model="form.currency_code"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">Use default ({{ props.defaultCurrency }})</option>
                                <option v-for="currency in props.currencies" :key="currency.code" :value="currency.code">
                                    {{ currency.code }} - {{ currency.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.currency_code" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="order_date">Order Date <span class="text-destructive">*</span></Label>
                            <Input id="order_date" v-model="form.order_date" type="date" />
                            <InputError :message="form.errors.order_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="expected_date">Expected Date</Label>
                            <Input id="expected_date" v-model="form.expected_date" type="date" />
                            <InputError :message="form.errors.expected_date" />
                        </div>
                    </div>

                    <div class="rounded-md border border-sidebar-border/70 p-3">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold">PO Lines</h3>
                            <Button type="button" variant="outline" size="sm" @click="addLine">+ Add Line</Button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(line, index) in form.lines" :key="index" class="rounded-md border border-sidebar-border/50 p-3">
                                <div class="grid gap-3 md:grid-cols-[1.2fr_0.6fr_0.5fr_0.8fr_auto]">
                                    <div class="grid gap-2">
                                        <Label :for="`line-part-${index}`">Part <span class="text-destructive">*</span></Label>
                                        <select
                                            :id="`line-part-${index}`"
                                            v-model="line.part_id"
                                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                            @change="choosePart(index)"
                                        >
                                            <option value="">- pilih part -</option>
                                            <option v-for="part in props.parts" :key="part.id" :value="part.id">{{ part.part_number }} - {{ part.name }}</option>
                                        </select>
                                        <InputError :message="form.errors[`lines.${index}.part_id`]" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label :for="`line-qty-${index}`">Qty <span class="text-destructive">*</span></Label>
                                        <Input :id="`line-qty-${index}`" v-model="line.quantity" type="number" min="1" step="1" />
                                        <InputError :message="form.errors[`lines.${index}.quantity`]" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label :for="`line-unit-${index}`">Unit</Label>
                                        <Input :id="`line-unit-${index}`" v-model="line.unit" maxlength="20" />
                                        <InputError :message="form.errors[`lines.${index}.unit`]" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label :for="`line-price-${index}`">Unit Price <span class="text-destructive">*</span></Label>
                                        <Input :id="`line-price-${index}`" v-model="line.unit_price" type="number" min="0" step="any" />
                                        <InputError :message="form.errors[`lines.${index}.unit_price`]" />
                                    </div>

                                    <div class="flex items-end">
                                        <Button type="button" variant="destructive" size="sm" @click="removeLine(index)">Remove</Button>
                                    </div>
                                </div>

                                <div class="mt-3 grid gap-2">
                                    <Label :for="`line-remarks-${index}`">Remarks</Label>
                                    <Input :id="`line-remarks-${index}`" v-model="line.remarks" maxlength="1000" />
                                    <InputError :message="form.errors[`lines.${index}.remarks`]" />
                                </div>

                                <div class="mt-2 text-right text-xs font-semibold text-muted-foreground">
                                    Line total: {{ Number(lineTotal(index)).toLocaleString() }} {{ form.currency_code || props.defaultCurrency }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="opsional"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <div class="rounded-md border border-sidebar-border/70 bg-muted/30 p-3 text-sm">
                        <div class="flex items-center justify-between border-t border-sidebar-border/70 pt-2 font-semibold">
                            <span>Subtotal</span>
                            <span class="font-mono">{{ Number(subtotal).toLocaleString() }} {{ form.currency_code || props.defaultCurrency }}</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/purchase/po">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Save PO</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
