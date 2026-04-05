<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PoItem = {
    id: number;
    part_id: number;
    part_number: string | null;
    part_name: string | null;
    quantity: string;
    received_quantity: string;
    remaining_quantity: string;
    unit: string;
};

type Props = {
    purchaseOrder: {
        id: number;
        po_number: string;
        status: number;
        order_date: string | null;
        expected_date: string | null;
        supplier: {
            id: number | null;
            name: string | null;
        };
        items: PoItem[];
    };
    warehouses: Array<{
        id: number;
        code: string;
        name: string;
    }>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'Report Arrival', href: '/purchase/po/arrivals' },
    { title: props.purchaseOrder.po_number, href: `/purchase/po/${props.purchaseOrder.id}/arrivals/report` },
];

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    arrival_date: today,
    notes: '',
    lines: props.purchaseOrder.items.map((item) => ({
        purchase_order_item_id: item.id,
        received_quantity: '0',
        warehouse_id: '' as unknown as number,
        notes: '',
    })),
});

const submit = () => {
    form.post(`/purchase/po/${props.purchaseOrder.id}/arrivals/report`);
};
</script>

<template>
    <Head :title="`Arrival Report - ${props.purchaseOrder.po_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading :title="`Arrival Report - ${props.purchaseOrder.po_number}`" description="Input qty barang yang diterima dari supplier." />
                <Button variant="outline" as-child>
                    <Link href="/purchase/po/arrivals">Back</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-5 grid gap-2 rounded-md bg-muted/30 px-3 py-2 text-sm md:grid-cols-3">
                    <div><span class="text-muted-foreground">Supplier:</span> {{ props.purchaseOrder.supplier.name ?? '-' }}</div>
                    <div><span class="text-muted-foreground">Order Date:</span> {{ props.purchaseOrder.order_date ?? '-' }}</div>
                    <div><span class="text-muted-foreground">Expected Date:</span> {{ props.purchaseOrder.expected_date ?? '-' }}</div>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="arrival_date">Arrival Date <span class="text-destructive">*</span></Label>
                            <Input id="arrival_date" v-model="form.arrival_date" type="date" />
                            <InputError :message="form.errors.arrival_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes">Header Notes</Label>
                            <Input id="notes" v-model="form.notes" maxlength="5000" />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(line, index) in form.lines" :key="line.purchase_order_item_id" class="rounded-md border border-sidebar-border/50 p-3">
                            <div class="mb-2 text-sm font-semibold">
                                {{ props.purchaseOrder.items[index]?.part_number }} - {{ props.purchaseOrder.items[index]?.part_name }}
                            </div>
                            <div class="mb-3 text-xs text-muted-foreground">
                                Ordered: {{ props.purchaseOrder.items[index]?.quantity }} {{ props.purchaseOrder.items[index]?.unit }} |
                                Received: {{ props.purchaseOrder.items[index]?.received_quantity }} |
                                Remaining: {{ props.purchaseOrder.items[index]?.remaining_quantity }}
                            </div>

                            <div class="grid gap-3 md:grid-cols-[0.7fr_1fr_1fr]">
                                <div class="grid gap-2">
                                    <Label :for="`qty-${index}`">Received Qty</Label>
                                    <Input :id="`qty-${index}`" v-model="line.received_quantity" type="number" min="0" step="1" />
                                    <InputError :message="form.errors[`lines.${index}.received_quantity`]" />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`wh-${index}`">Warehouse</Label>
                                    <select
                                        :id="`wh-${index}`"
                                        v-model="line.warehouse_id"
                                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option value="">- pilih warehouse -</option>
                                        <option v-for="warehouse in props.warehouses" :key="warehouse.id" :value="warehouse.id">
                                            {{ warehouse.code }} - {{ warehouse.name }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors[`lines.${index}.warehouse_id`]" />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`line-notes-${index}`">Line Notes</Label>
                                    <Input :id="`line-notes-${index}`" v-model="line.notes" maxlength="1000" />
                                    <InputError :message="form.errors[`lines.${index}.notes`]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <InputError :message="form.errors.lines" />

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/purchase/po/arrivals">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Submit Arrival Report</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
