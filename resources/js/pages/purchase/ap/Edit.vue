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

type PurchaseOrderOption = {
    id: number;
    po_number: string;
    supplier_id: number;
    currency_code: string;
    items: Array<{
        id: number;
        part_id: number | null;
        part_number: string | null;
        part_name: string | null;
        received_quantity: string;
        unit_price: string;
    }>;
};

type PartOption = {
    id: number;
    part_number: string;
    name: string;
};

type CurrencyOption = {
    code: string;
    name: string;
};

type ApInvoiceLine = {
    purchase_order_item_id: number | null;
    part_id: number | null;
    description: string | null;
    quantity: string;
    unit_price: string;
};

type ApInvoiceData = {
    id: number;
    ap_invoice_number: string;
    supplier_invoice_number: string | null;
    supplier_id: number;
    purchase_order_id: number | null;
    invoice_date: string | null;
    due_date: string | null;
    currency_code: string;
    notes: string | null;
    lines: ApInvoiceLine[];
};

type Props = {
    apInvoice: ApInvoiceData;
    suppliers: SupplierOption[];
    purchaseOrders: PurchaseOrderOption[];
    parts: PartOption[];
    defaultCurrency: string;
    currencies: CurrencyOption[];
    taxRate: number;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'AP Invoice', href: '/purchase/ap/invoices' },
    { title: `Edit ${props.apInvoice.ap_invoice_number}`, href: `/purchase/ap/invoices/${props.apInvoice.id}/edit` },
];

const form = useForm({
    supplier_id: props.apInvoice.supplier_id as unknown as number,
    purchase_order_id: (props.apInvoice.purchase_order_id ?? '') as unknown as number,
    supplier_invoice_number: props.apInvoice.supplier_invoice_number ?? '',
    invoice_date: props.apInvoice.invoice_date ?? '',
    due_date: props.apInvoice.due_date ?? '',
    currency_code: props.apInvoice.currency_code ?? '',
    notes: props.apInvoice.notes ?? '',
    lines: props.apInvoice.lines.length
        ? props.apInvoice.lines.map((line) => ({
              purchase_order_item_id: (line.purchase_order_item_id ?? '') as unknown as number,
              part_id: (line.part_id ?? '') as unknown as number,
              description: line.description ?? '',
              quantity: line.quantity,
              unit_price: line.unit_price,
          }))
        : [
              {
                  purchase_order_item_id: '' as unknown as number,
                  part_id: '' as unknown as number,
                  description: '',
                  quantity: '1',
                  unit_price: '0',
              },
          ],
});

const selectedPurchaseOrder = computed(() => {
    return props.purchaseOrders.find((po) => po.id === Number(form.purchase_order_id)) ?? null;
});

const addLine = () => {
    form.lines.push({
        purchase_order_item_id: '' as unknown as number,
        part_id: '' as unknown as number,
        description: '',
        quantity: '1',
        unit_price: '0',
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

    if (!line.description) {
        line.description = `${selectedPart.part_number} - ${selectedPart.name}`;
    }
};

const choosePurchaseOrder = () => {
    const po = selectedPurchaseOrder.value;

    if (!po) {
        return;
    }

    form.supplier_id = po.supplier_id as unknown as number;
    form.currency_code = po.currency_code || '';
    form.lines = po.items
        .filter((item) => Number(item.received_quantity) > 0)
        .map((item) => ({
            purchase_order_item_id: item.id as unknown as number,
            part_id: (item.part_id ?? '') as unknown as number,
            description: [item.part_number, item.part_name].filter(Boolean).join(' - '),
            quantity: item.received_quantity,
            unit_price: item.unit_price,
        }));

    if (!form.lines.length) {
        addLine();
    }
};

const lineTotal = (index: number): number => {
    const line = form.lines[index];

    return Number(line.quantity || 0) * Number(line.unit_price || 0);
};

const subtotal = computed(() => {
    return form.lines.reduce((sum, _, index) => sum + lineTotal(index), 0);
});

const taxAmount = computed(() => {
    return subtotal.value * (props.taxRate / 100);
});

const grandTotal = computed(() => {
    return subtotal.value + taxAmount.value;
});

const submit = () => {
    form.put(`/purchase/ap/invoices/${props.apInvoice.id}`);
};
</script>

<template>
    <Head :title="`Edit ${apInvoice.ap_invoice_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Edit AP Invoice" description="AP Invoice ini masih bisa diedit karena masih Draft." />
                <Button variant="outline" as-child>
                    <Link href="/purchase/ap/invoices">Back to List</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-5 flex flex-wrap items-center gap-3 rounded-md bg-muted/40 px-3 py-2">
                    <span class="text-xs text-muted-foreground">AP Invoice Number</span>
                    <span class="font-mono text-sm font-semibold">{{ apInvoice.ap_invoice_number }}</span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="purchase_order_id">Source Purchase Order (opsional)</Label>
                            <select
                                id="purchase_order_id"
                                v-model="form.purchase_order_id"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @change="choosePurchaseOrder"
                            >
                                <option value="">- tanpa PO -</option>
                                <option v-for="po in props.purchaseOrders" :key="po.id" :value="po.id">
                                    {{ po.po_number }}
                                </option>
                            </select>
                            <InputError :message="form.errors.purchase_order_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="supplier_id">Supplier <span class="text-destructive">*</span></Label>
                            <select
                                id="supplier_id"
                                v-model="form.supplier_id"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">- pilih supplier -</option>
                                <option v-for="supplier in props.suppliers" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.supplier_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="supplier_invoice_number">Supplier Invoice Number</Label>
                            <Input id="supplier_invoice_number" v-model="form.supplier_invoice_number" maxlength="255" placeholder="No. invoice dari supplier (opsional)" />
                            <InputError :message="form.errors.supplier_invoice_number" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="invoice_date">Invoice Date</Label>
                            <Input id="invoice_date" v-model="form.invoice_date" type="date" />
                            <InputError :message="form.errors.invoice_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="due_date">Due Date</Label>
                            <Input id="due_date" v-model="form.due_date" type="date" />
                            <InputError :message="form.errors.due_date" />
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
                            <Label>Tax ({{ props.taxRate }}%)</Label>
                            <div class="flex h-10 items-center rounded-md border border-input bg-muted/40 px-3 text-sm text-muted-foreground">
                                {{ Number(taxAmount).toLocaleString() }} {{ form.currency_code || props.defaultCurrency }}
                            </div>
                        </div>
                    </div>

                    <div class="rounded-md border border-sidebar-border/70 p-3">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold">Invoice Lines</h3>
                            <Button type="button" variant="outline" size="sm" @click="addLine">+ Add Line</Button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(line, index) in form.lines" :key="index" class="rounded-md border border-sidebar-border/50 p-3">
                                <div class="grid gap-3 md:grid-cols-[1.3fr_1.2fr_0.6fr_0.8fr_auto]">
                                    <div class="grid gap-2">
                                        <Label :for="`line-part-${index}`">Part</Label>
                                        <select
                                            :id="`line-part-${index}`"
                                            v-model="line.part_id"
                                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                            @change="choosePart(index)"
                                        >
                                            <option value="">- pilih part -</option>
                                            <option v-for="part in props.parts" :key="part.id" :value="part.id">
                                                {{ part.part_number }} - {{ part.name }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors[`lines.${index}.part_id`]" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label :for="`line-description-${index}`">Description</Label>
                                        <Input :id="`line-description-${index}`" v-model="line.description" maxlength="255" />
                                        <InputError :message="form.errors[`lines.${index}.description`]" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label :for="`line-qty-${index}`">Qty <span class="text-destructive">*</span></Label>
                                        <Input :id="`line-qty-${index}`" v-model="line.quantity" type="number" min="0.0001" step="any" />
                                        <InputError :message="form.errors[`lines.${index}.quantity`]" />
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
                        <div class="flex items-center justify-between py-1">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="font-mono">{{ Number(subtotal).toLocaleString() }} {{ form.currency_code || props.defaultCurrency }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1">
                            <span class="text-muted-foreground">Tax ({{ props.taxRate }}%)</span>
                            <span class="font-mono">{{ Number(taxAmount).toLocaleString() }} {{ form.currency_code || props.defaultCurrency }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-sidebar-border/70 pt-2 font-semibold">
                            <span>Total</span>
                            <span class="font-mono">{{ Number(grandTotal).toLocaleString() }} {{ form.currency_code || props.defaultCurrency }}</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/purchase/ap/invoices">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Save Changes</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
