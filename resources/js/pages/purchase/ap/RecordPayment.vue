<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type ApInvoiceData = {
    id: number;
    ap_invoice_number: string;
    currency_code: string;
    total_amount: string;
    amount_paid: string;
    balance_due: string;
};

type Props = {
    apInvoice: ApInvoiceData;
    paymentMethods: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'AP Invoice', href: '/purchase/ap/invoices' },
    { title: `Record Payment - ${props.apInvoice.ap_invoice_number}`, href: `/purchase/ap/invoices/${props.apInvoice.id}/record-payment` },
];

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    amount: props.apInvoice.balance_due,
    payment_date: today,
    payment_method: 'bank_transfer',
    reference_number: '',
    notes: '',
});

const submit = () => {
    form.post(`/purchase/ap/invoices/${props.apInvoice.id}/record-payment`);
};
</script>

<template>
    <Head :title="`Record Payment - ${apInvoice.ap_invoice_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-lg flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Record Payment" description="Catat pembayaran yang dikirim ke supplier untuk invoice ini." />
                <Button variant="outline" as-child>
                    <Link href="/purchase/ap/invoices">Back to List</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-5 grid gap-2 rounded-md bg-muted/40 px-3 py-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">AP Invoice</span>
                        <span class="font-mono font-semibold">{{ apInvoice.ap_invoice_number }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Total</span>
                        <span class="font-mono">{{ Number(apInvoice.total_amount).toLocaleString() }} {{ apInvoice.currency_code }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Already Paid</span>
                        <span class="font-mono">{{ Number(apInvoice.amount_paid).toLocaleString() }} {{ apInvoice.currency_code }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-sidebar-border/70 pt-2 font-semibold">
                        <span>Balance Due</span>
                        <span class="font-mono">{{ Number(apInvoice.balance_due).toLocaleString() }} {{ apInvoice.currency_code }}</span>
                    </div>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="amount">Amount <span class="text-destructive">*</span></Label>
                        <Input id="amount" v-model="form.amount" type="number" min="0.01" step="any" />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="payment_date">Payment Date</Label>
                        <Input id="payment_date" v-model="form.payment_date" type="date" />
                        <InputError :message="form.errors.payment_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="payment_method">Payment Method</Label>
                        <select
                            id="payment_method"
                            v-model="form.payment_method"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option v-for="(label, value) in paymentMethods" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.payment_method" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="reference_number">Reference Number</Label>
                        <Input id="reference_number" v-model="form.reference_number" maxlength="100" placeholder="No. transfer / cheque (opsional)" />
                        <InputError :message="form.errors.reference_number" />
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

                    <div class="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/purchase/ap/invoices">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Save Payment</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
