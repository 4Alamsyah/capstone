<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type StockOption = {
    warehouse_id: number;
    warehouse_code: string | null;
    warehouse_name: string | null;
    quantity: number;
};

type ComponentRequirement = {
    bom_item_id: number;
    part_id: number;
    part_number: string;
    part_name: string;
    bom_quantity: string;
    recommended_quantity: number;
    stocks: StockOption[];
};

type RecentReport = {
    id: number;
    previous_status: string | null;
    new_status: string;
    good_quantity: string;
    reject_quantity: string;
    notes: string | null;
    reported_by: string | null;
    created_at: string;
};

type Props = {
    workOrder: {
        id: number;
        wo_number: string;
        status: string;
        quantity: string;
        scheduled_date: string | null;
        bom: {
            id: number;
            name: string;
            part: {
                part_number: string;
                name: string;
            };
        };
    };
    components: ComponentRequirement[];
    recentReports: RecentReport[];
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Report MO', href: '/work-orders/report' },
    { title: props.workOrder.wo_number, href: `/work-orders/${props.workOrder.id}/report` },
];

const form = useForm({
    new_status: props.workOrder.status,
    good_quantity: props.workOrder.quantity,
    reject_quantity: '0',
    notes: '',
    consumptions: props.components.map((component) => ({
        bom_item_id: component.bom_item_id,
        part_id: component.part_id,
        warehouse_id: component.stocks[0]?.warehouse_id ?? null,
        quantity: component.recommended_quantity > 0 ? String(component.recommended_quantity) : '0',
    })),
});

const submit = () => {
    form.post(`/work-orders/${props.workOrder.id}/report`);
};
</script>

<template>
    <Head :title="`Report MO - ${workOrder.wo_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    :title="`Report ${workOrder.wo_number}`"
                    :description="`${workOrder.bom.part.part_number} - ${workOrder.bom.part.name} | BOM: ${workOrder.bom.name}`"
                />
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="`/work-orders/${workOrder.id}`">Detail MO</Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/work-orders/report">Back</Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <form class="space-y-6 rounded-lg border border-sidebar-border/70 p-4" @submit.prevent="submit">
                    <div>
                        <h3 class="text-sm font-semibold">Report Result</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Update status MO dan catat hasil produksi beserta konsumsi material.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="new_status">New Status</Label>
                            <select
                                id="new_status"
                                v-model="form.new_status"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError :message="form.errors.new_status" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="good_quantity">Good Quantity</Label>
                            <Input id="good_quantity" v-model="form.good_quantity" type="number" min="0" step="any" />
                            <InputError :message="form.errors.good_quantity" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="reject_quantity">Reject Quantity</Label>
                            <Input id="reject_quantity" v-model="form.reject_quantity" type="number" min="0" step="any" />
                            <InputError :message="form.errors.reject_quantity" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="notes">Report Notes</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                placeholder="Catatan hasil produksi, kendala, atau remark"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold">Material Consumption</h4>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Isi warehouse dan qty konsumsi untuk tiap komponen yang benar-benar dipakai pada MO ini.
                            </p>
                        </div>

                        <div v-if="components.length === 0" class="rounded-md border border-dashed border-sidebar-border/70 px-4 py-6 text-sm text-muted-foreground">
                            BOM ini tidak memiliki komponen part untuk dikonsumsi.
                        </div>

                        <div v-for="(component, index) in components" :key="component.bom_item_id" class="grid gap-3 rounded-md border border-sidebar-border/50 p-3 lg:grid-cols-[1.2fr_1fr_140px]">
                            <div>
                                <div class="font-medium">{{ component.part_number }} - {{ component.part_name }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Qty per BOM: {{ component.bom_quantity }} | Recommended: {{ component.recommended_quantity }}
                                </div>
                            </div>

                            <div class="grid gap-1">
                                <Label :for="`warehouse-${component.bom_item_id}`">Warehouse</Label>
                                <select
                                    :id="`warehouse-${component.bom_item_id}`"
                                    v-model="form.consumptions[index].warehouse_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option :value="null">- pilih warehouse -</option>
                                    <option v-for="stock in component.stocks" :key="stock.warehouse_id" :value="stock.warehouse_id">
                                        {{ stock.warehouse_code }} - {{ stock.warehouse_name }} (stok: {{ stock.quantity }})
                                    </option>
                                </select>
                                <InputError :message="form.errors[`consumptions.${index}.warehouse_id` as keyof typeof form.errors]" />
                            </div>

                            <div class="grid gap-1">
                                <Label :for="`qty-${component.bom_item_id}`">Consumed Qty</Label>
                                <Input :id="`qty-${component.bom_item_id}`" v-model="form.consumptions[index].quantity" type="number" min="0" step="1" />
                                <InputError :message="form.errors[`consumptions.${index}.quantity` as keyof typeof form.errors]" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/work-orders/report">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Submit Report</Button>
                    </div>
                </form>

                <div class="space-y-6">
                    <div class="rounded-lg border border-sidebar-border/70 p-4">
                        <h3 class="text-sm font-semibold">MO Summary</h3>
                        <dl class="mt-3 grid gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">WO Number</dt>
                                <dd class="font-mono font-semibold">{{ workOrder.wo_number }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Current Status</dt>
                                <dd>{{ statusLabels[workOrder.status] ?? workOrder.status }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Planned Qty</dt>
                                <dd>{{ workOrder.quantity }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Scheduled Date</dt>
                                <dd>{{ workOrder.scheduled_date ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-lg border border-sidebar-border/70 p-4">
                        <h3 class="text-sm font-semibold">Recent Reports</h3>
                        <div v-if="recentReports.length === 0" class="mt-3 text-sm text-muted-foreground">
                            Belum ada report sebelumnya.
                        </div>
                        <div v-else class="mt-3 space-y-3">
                            <div v-for="report in recentReports" :key="report.id" class="rounded-md border border-sidebar-border/50 p-3">
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <div class="font-medium">
                                        {{ statusLabels[report.previous_status ?? ''] ?? report.previous_status ?? '-' }} →
                                        {{ statusLabels[report.new_status] ?? report.new_status }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">{{ report.created_at }}</div>
                                </div>
                                <div class="mt-2 text-xs text-muted-foreground">
                                    Good: {{ report.good_quantity }} | Reject: {{ report.reject_quantity }}
                                    <span v-if="report.reported_by">| By: {{ report.reported_by }}</span>
                                </div>
                                <div v-if="report.notes" class="mt-2 text-sm">{{ report.notes }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
