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

type CustomerOption = {
    id: number;
    name: string;
    currency_code: string | null;
};

type OrderOption = {
    id: number;
    co_number: string;
    customer_id: number;
    currency_code: string;
    items: Array<{
        part_id: number | null;
        part_number: string | null;
        part_name: string | null;
        quantity: string;
        unit_price: string;
    }>;
};

type PartOption = {
    id: number;
    part_number: string;
    name: string;
    category: string | null;
    selling_price: number;
};

type CurrencyOption = {
    code: string;
    name: string;
};

type InvoiceLine = {
    part_id: number | null;
    description: string | null;
    quantity: string;
    unit_price: string;
};

type InvoiceData = {
    id: number;
    invoice_number: string;
    customer_id: number;
    customer_order_id: number | null;
    invoice_date: string | null;
    due_date: string | null;
    currency_code: string;
    notes: string | null;
    lines: InvoiceLine[];
};

type Props = {
    invoice: InvoiceData;
    customers: CustomerOption[];
    orders: OrderOption[];
    parts: PartOption[];
    defaultCurrency: string;
    currencies: CurrencyOption[];
    taxRate: number;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales/customer-orders' },
    { title: 'Invoice List', href: '/sales/invoices' },
    { title: `Edit ${props.invoice.invoice_number}`, href: `/sales/invoices/${props.invoice.id}/edit` },
];

const form = useForm({
    customer_id: props.invoice.customer_id as unknown as number,
    customer_order_id: (props.invoice.customer_order_id ?? '') as unknown as number,
    invoice_date: props.invoice.invoice_date ?? '',
    due_date: props.invoice.due_date ?? '',
    currency_code: props.invoice.currency_code ?? '',
    notes: props.invoice.notes ?? '',
    lines: props.invoice.lines.length
        ? props.invoice.lines.map((line) => ({
              part_id: (line.part_id ?? '') as unknown as number,
              description: line.description ?? '',
              quantity: line.quantity,
              unit_price: line.unit_price,
          }))
        : [
              {
                  part_id: '' as unknown as number,
                  description: '',
                  quantity: '1',
                  unit_price: '0',
              },
          ],
});

const selectedCustomer = computed(() => {
    return props.customers.find((customer) => customer.id === Number(form.customer_id)) ?? null;
});

const selectedOrder = computed(() => {
    return props.orders.find((order) => order.id === Number(form.customer_order_id)) ?? null;
});

const partGroups = computed(() => [
    { label: 'Manufacture Part', parts: props.parts.filter((part) => part.category === 'manufacture') },
    { label: 'Purchase Part', parts: props.parts.filter((part) => part.category === 'purchase') },
    { label: 'Lainnya', parts: props.parts.filter((part) => part.category !== 'manufacture' && part.category !== 'purchase') },
]);

const addLine = () => {
    form.lines.push({
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

    line.unit_price = selectedPart.selling_price.toString();
    if (!line.description) {
        line.description = `${selectedPart.part_number} - ${selectedPart.name}`;
    }
};

const chooseCustomer = () => {
    const customer = selectedCustomer.value;

    if (!customer) {
        return;
    }

    form.currency_code = customer.currency_code ?? '';
};

const chooseOrder = () => {
    const order = selectedOrder.value;

    if (!order) {
        return;
    }

    form.customer_id = order.customer_id as unknown as number;
    form.currency_code = order.currency_code || '';
    form.lines = order.items.map((item) => ({
        part_id: (item.part_id ?? '') as unknown as number,
        description: [item.part_number, item.part_name].filter(Boolean).join(' - '),
        quantity: item.quantity,
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
    form.put(`/sales/invoices/${props.invoice.id}`);
};
</script>

<template>
    <Head :title="`Edit ${invoice.invoice_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Edit Invoice" description="Invoice ini masih bisa diedit karena payment belum diajukan." />
                <Button variant="outline" as-child>
                    <Link href="/sales/invoices">Back to List</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-5 flex flex-wrap items-center gap-3 rounded-md bg-muted/40 px-3 py-2">
                    <span class="text-xs text-muted-foreground">Invoice Number</span>
                    <span class="font-mono text-sm font-semibold">{{ invoice.invoice_number }}</span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="customer_id">Customer <span class="text-destructive">*</span></Label>
                            <select
                                id="customer_id"
                                v-model="form.customer_id"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @change="chooseCustomer"
                            >
                                <option value="">- pilih customer -</option>
                                <option v-for="customer in props.customers" :key="customer.id" :value="customer.id">
                                    {{ customer.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.customer_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="customer_order_id">Source Customer Order (opsional)</Label>
                            <select
                                id="customer_order_id"
                                v-model="form.customer_order_id"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @change="chooseOrder"
                            >
                                <option value="">- tanpa customer order -</option>
                                <option v-for="order in props.orders" :key="order.id" :value="order.id">
                                    {{ order.co_number }}
                                </option>
                            </select>
                            <InputError :message="form.errors.customer_order_id" />
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
                            <p class="text-xs text-muted-foreground">
                                Tax dihitung otomatis dari
                                <Link href="/accounting/tax-setting" class="underline">Tax Setting</Link>.
                            </p>
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
                                            <template v-for="group in partGroups" :key="group.label">
                                                <optgroup v-if="group.parts.length" :label="group.label">
                                                    <option v-for="part in group.parts" :key="part.id" :value="part.id">
                                                        {{ part.part_number }} - {{ part.name }}
                                                    </option>
                                                </optgroup>
                                            </template>
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
                            <Link href="/sales/invoices">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Save Changes</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
