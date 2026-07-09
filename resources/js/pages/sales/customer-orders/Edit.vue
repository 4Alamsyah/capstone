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
    shipping_address: string | null;
    payment_terms: string | null;
    currency_code: string | null;
};

type PartOption = {
    id: number;
    part_number: string;
    name: string;
    selling_price: number;
    stock_on_hand: number;
};

type CurrencyOption = {
    code: string;
    name: string;
};

type OrderLine = {
    part_id: number | null;
    quantity: string;
    unit: string | null;
    unit_price: string;
    remarks: string | null;
};

type OrderData = {
    id: number;
    co_number: string;
    customer_id: number;
    order_date: string | null;
    delivery_date: string | null;
    shipping_address: string | null;
    payment_terms: string | null;
    project_code: string | null;
    delivery_type: string | null;
    po_number: string | null;
    currency_code: string;
    notes: string | null;
    lines: OrderLine[];
};

type Props = {
    order: OrderData;
    customers: CustomerOption[];
    parts: PartOption[];
    defaultCurrency: string;
    currencies: CurrencyOption[];
    paymentTermsOptions: string[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales/customer-orders' },
    { title: 'Order List', href: '/sales/customer-orders' },
    { title: `Edit ${props.order.co_number}`, href: `/sales/customer-orders/${props.order.id}/edit` },
];

const form = useForm({
    customer_id: props.order.customer_id as unknown as number,
    order_date: props.order.order_date ?? '',
    delivery_date: props.order.delivery_date ?? '',
    shipping_address: props.order.shipping_address ?? '',
    payment_terms: props.order.payment_terms ?? '',
    project_code: props.order.project_code ?? '',
    delivery_type: (props.order.delivery_type ?? 'equipment') as 'equipment' | 'material',
    po_number: props.order.po_number ?? '',
    currency_code: props.order.currency_code ?? '',
    notes: props.order.notes ?? '',
    lines: props.order.lines.length
        ? props.order.lines.map((line) => ({
              part_id: (line.part_id ?? '') as unknown as number,
              quantity: line.quantity,
              unit: line.unit ?? 'PCS',
              unit_price: line.unit_price,
              remarks: line.remarks ?? '',
          }))
        : [
              {
                  part_id: '' as unknown as number,
                  quantity: '1',
                  unit: 'PCS',
                  unit_price: '0',
                  remarks: '',
              },
          ],
});

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

const selectedCustomer = computed(() => {
    return props.customers.find((customer) => customer.id === Number(form.customer_id)) ?? null;
});

const applyCustomerDefaults = () => {
    const customer = selectedCustomer.value;

    if (!customer) {
        return;
    }

    form.shipping_address = customer.shipping_address ?? '';
    form.payment_terms = customer.payment_terms ?? '';
    form.currency_code = customer.currency_code ?? '';
};

const getPartById = (partId: number) => {
    return props.parts.find((part) => part.id === Number(partId)) ?? null;
};

const preAllocatedQty = (lineIndex: number, partId: number) => {
    if (!partId) {
        return 0;
    }

    return form.lines
        .slice(0, lineIndex)
        .filter((line) => Number(line.part_id) === Number(partId))
        .reduce((sum, line) => sum + Number(line.quantity || 0), 0);
};

const lineAvailability = (lineIndex: number) => {
    const line = form.lines[lineIndex];
    const selectedPart = getPartById(Number(line.part_id));

    if (!selectedPart) {
        return {
            stock: 0,
            remaining: 0,
            requiresMo: false,
        };
    }

    const quantity = Number(line.quantity || 0);
    const used = preAllocatedQty(lineIndex, selectedPart.id);
    const remaining = Math.max(0, selectedPart.stock_on_hand - used);

    return {
        stock: selectedPart.stock_on_hand,
        remaining,
        requiresMo: quantity > remaining,
    };
};

const lineTotal = (index: number) => {
    const line = form.lines[index];

    return Number(line.quantity || 0) * Number(line.unit_price || 0);
};

const subtotal = computed(() => {
    return form.lines.reduce((sum, _, index) => sum + lineTotal(index), 0);
});

const choosePart = (index: number) => {
    const line = form.lines[index];
    const selectedPart = getPartById(Number(line.part_id));

    if (!selectedPart) {
        return;
    }

    line.unit_price = selectedPart.selling_price.toString();
};

const submit = () => {
    form.put(`/sales/customer-orders/${props.order.id}`);
};
</script>

<template>
    <Head :title="`Edit ${order.co_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Edit Customer Order" description="Order ini masih berstatus Registered, jadi masih bisa diedit penuh." />
                <Button variant="outline" as-child>
                    <Link href="/sales/customer-orders">← Back</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-5 flex flex-wrap items-center gap-3 rounded-md bg-muted/40 px-3 py-2">
                    <span class="text-xs text-muted-foreground">CO Number</span>
                    <span class="font-mono text-sm font-semibold">{{ order.co_number }}</span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="customer_id">Customer <span class="text-destructive">*</span></Label>
                            <select
                                id="customer_id"
                                v-model="form.customer_id"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @change="applyCustomerDefaults"
                            >
                                <option value="">- pilih customer -</option>
                                <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                    {{ customer.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.customer_id" />
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
                            <Label for="order_date">Order Date</Label>
                            <Input id="order_date" v-model="form.order_date" type="date" />
                            <InputError :message="form.errors.order_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="delivery_date">Delivery Date</Label>
                            <Input id="delivery_date" v-model="form.delivery_date" type="date" />
                            <InputError :message="form.errors.delivery_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="po_number">PO Number</Label>
                            <Input id="po_number" v-model="form.po_number" maxlength="100" />
                            <InputError :message="form.errors.po_number" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project_code">Project Code</Label>
                            <Input id="project_code" v-model="form.project_code" maxlength="100" />
                            <InputError :message="form.errors.project_code" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="payment_terms">Payment Terms</Label>
                            <select
                                id="payment_terms"
                                v-model="form.payment_terms"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">- pilih payment terms -</option>
                                <option v-for="term in props.paymentTermsOptions" :key="term" :value="term">{{ term }}</option>
                            </select>
                            <InputError :message="form.errors.payment_terms" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="delivery_type">Delivery Type</Label>
                            <select
                                id="delivery_type"
                                v-model="form.delivery_type"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="equipment">Equipment</option>
                                <option value="material">Material</option>
                            </select>
                            <InputError :message="form.errors.delivery_type" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="shipping_address">Shipping Address</Label>
                            <textarea
                                id="shipping_address"
                                v-model="form.shipping_address"
                                rows="2"
                                class="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            />
                            <InputError :message="form.errors.shipping_address" />
                        </div>
                    </div>

                    <div class="rounded-md border border-sidebar-border/70 p-3">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold">Order Lines</h3>
                            <Button type="button" variant="outline" size="sm" @click="addLine">+ Add Line</Button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(line, index) in form.lines" :key="index" class="rounded-md border border-sidebar-border/50 p-3">
                                <div class="grid gap-3 md:grid-cols-[1.2fr_0.55fr_0.5fr_0.75fr_1fr_auto]">
                                    <div class="grid gap-2">
                                        <Label :for="`line-part-${index}`">Part Number <span class="text-destructive">*</span></Label>
                                        <select
                                            :id="`line-part-${index}`"
                                            v-model="line.part_id"
                                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                            @change="choosePart(index)"
                                        >
                                            <option value="">- pilih part -</option>
                                            <option v-for="part in parts" :key="part.id" :value="part.id">
                                                {{ part.part_number }} - {{ part.name }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors[`lines.${index}.part_id`]" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label :for="`line-qty-${index}`">Qty <span class="text-destructive">*</span></Label>
                                        <Input :id="`line-qty-${index}`" v-model="line.quantity" type="number" min="0.0001" step="any" />
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

                                    <div class="grid gap-2">
                                        <Label :for="`line-remarks-${index}`">Remarks</Label>
                                        <Input :id="`line-remarks-${index}`" v-model="line.remarks" maxlength="255" placeholder="opsional" />
                                        <InputError :message="form.errors[`lines.${index}.remarks`]" />
                                    </div>

                                    <div class="flex items-end">
                                        <Button type="button" variant="destructive" size="sm" @click="removeLine(index)">Remove</Button>
                                    </div>
                                </div>

                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <div class="text-muted-foreground">
                                        Stock: {{ lineAvailability(index).stock }} | Remaining for this line: {{ lineAvailability(index).remaining }}
                                    </div>
                                    <div v-if="lineAvailability(index).requiresMo" class="rounded bg-amber-100 px-2 py-1 font-semibold text-amber-700">
                                        Need MO suggestion (stock not enough)
                                    </div>
                                    <div v-else class="rounded bg-green-100 px-2 py-1 font-semibold text-green-700">
                                        Stock available
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

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-sidebar-border/70 bg-muted/30 p-3">
                        <div class="text-sm text-muted-foreground">Subtotal</div>
                        <div class="font-mono text-base font-semibold">{{ Number(subtotal).toLocaleString() }} {{ form.currency_code || props.defaultCurrency }}</div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/sales/customer-orders">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Save Changes</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
