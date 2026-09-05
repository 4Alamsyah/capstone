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
    category: string | null;
    selling_price: number;
    stock_on_hand: number;
};

type CurrencyOption = {
    code: string;
    name: string;
};

type QuotationEdit = {
    id: number;
    quotation_number: string;
    customer_id: number;
    order_date: string | null;
    delivery_date: string | null;
    shipping_address: string | null;
    payment_terms: string | null;
    currency_code: string | null;
    notes: string | null;
    lines: Array<{
        part_id: number;
        quantity: string;
        unit_price: string;
    }>;
};

type Props = {
    quotation: QuotationEdit;
    customers: CustomerOption[];
    parts: PartOption[];
    defaultCurrency: string;
    currencies: CurrencyOption[];
    paymentTermsOptions: string[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales/customer-orders' },
    { title: 'Quotation List', href: '/sales/quotations' },
    { title: 'Edit Quotation', href: `/sales/quotations/${props.quotation.id}/edit` },
];

const form = useForm({
    customer_id: props.quotation.customer_id as unknown as number,
    order_date: props.quotation.order_date ?? new Date().toISOString().slice(0, 10),
    delivery_date: props.quotation.delivery_date ?? '',
    shipping_address: props.quotation.shipping_address ?? '',
    payment_terms: props.quotation.payment_terms ?? '',
    currency_code: props.quotation.currency_code ?? '',
    notes: props.quotation.notes ?? '',
    lines: props.quotation.lines.length
        ? props.quotation.lines.map((line) => ({
              part_id: line.part_id as unknown as number,
              quantity: line.quantity,
              unit_price: line.unit_price,
          }))
        : [
              {
                  part_id: '' as unknown as number,
                  quantity: '1',
                  unit_price: '0',
              },
          ],
});

const addLine = () => {
    form.lines.push({
        part_id: '' as unknown as number,
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

const partGroups = computed(() => [
    { label: 'Manufacture Part', category: 'manufacture', parts: props.parts.filter((part) => part.category === 'manufacture') },
    { label: 'Purchase Part', category: 'purchase', parts: props.parts.filter((part) => part.category === 'purchase') },
    { label: 'Lainnya', category: null, parts: props.parts.filter((part) => part.category !== 'manufacture' && part.category !== 'purchase') },
]);

const choosePart = (index: number) => {
    const line = form.lines[index];
    const selectedPart = getPartById(Number(line.part_id));

    if (!selectedPart) {
        return;
    }

    line.unit_price = selectedPart.selling_price.toString();
};

const lineTotal = (index: number) => {
    const line = form.lines[index];

    return Number(line.quantity || 0) * Number(line.unit_price || 0);
};

const subtotal = computed(() => {
    return form.lines.reduce((sum, _, index) => sum + lineTotal(index), 0);
});

const submit = () => {
    form.put(`/sales/quotations/${props.quotation.id}`);
};
</script>

<template>
    <Head :title="`Edit Quotation ${props.quotation.quotation_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Edit Quotation" description="Ubah data quotation customer dan item penawaran." />
                <Button variant="outline" as-child>
                    <Link href="/sales/quotations">Back to List</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-5 flex flex-wrap items-center gap-3 rounded-md bg-muted/40 px-3 py-2">
                    <span class="text-xs text-muted-foreground">Quotation Number</span>
                    <span class="font-mono text-sm font-semibold">{{ props.quotation.quotation_number }}</span>
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
                            <Label for="order_date">Quotation Date</Label>
                            <Input id="order_date" v-model="form.order_date" type="date" />
                            <InputError :message="form.errors.order_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="delivery_date">Delivery Target</Label>
                            <Input id="delivery_date" v-model="form.delivery_date" type="date" />
                            <InputError :message="form.errors.delivery_date" />
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
                            <h3 class="text-sm font-semibold">Quotation Lines</h3>
                            <Button type="button" variant="outline" size="sm" @click="addLine">+ Add Line</Button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(line, index) in form.lines" :key="index" class="rounded-md border border-sidebar-border/50 p-3">
                                <div class="grid gap-3 md:grid-cols-[1.5fr_0.7fr_0.8fr_auto]">
                                    <div class="grid gap-2">
                                        <Label :for="`line-part-${index}`">Part Number <span class="text-destructive">*</span></Label>
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

                                <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                                    <div class="text-muted-foreground">
                                        Stock reference: {{ getPartById(Number(line.part_id))?.stock_on_hand ?? 0 }}
                                    </div>
                                    <div class="font-semibold text-muted-foreground">
                                        Line total: {{ Number(lineTotal(index)).toLocaleString() }} {{ form.currency_code || props.defaultCurrency }}
                                    </div>
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
                        <div class="text-sm text-muted-foreground">Quotation Total</div>
                        <div class="font-mono text-base font-semibold">{{ Number(subtotal).toLocaleString() }} {{ form.currency_code || props.defaultCurrency }}</div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/sales/quotations">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Update Quotation</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
