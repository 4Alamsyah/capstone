<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Part = {
    id: number;
    part_number: string;
    name: string;
    safety_stock: number;
    total_stock?: number;
};

type Line = {
    part_id: number;
    quantity: number;
    unit: string;
    remarks: string;
};

type Props = {
    parts: Part[];
    nextPvNumber: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'List Voucher', href: '/purchase/voucher' },
    { title: 'Register Voucher', href: '/purchase/voucher/create' },
];

const form = useForm({
    source: 'manual',
    required_date: '',
    notes: '',
    lines: [] as Line[],
});

const selectedPartId = ref<number | null>(null);
const selectedQuantity = ref<number>(0);
const selectedUnit = ref('PCS');
const selectedRemarks = ref('');

const selectedPart = computed(() => {
    if (!selectedPartId.value) {
        return null;
    }

    return props.parts.find((p) => p.id === selectedPartId.value) ?? null;
});

const addLine = () => {
    if (!selectedPartId.value || selectedQuantity.value <= 0) {
        window.alert('Pilih part dan masukkan quantity');
        return;
    }

    const existingIndex = form.lines.findIndex((l) => l.part_id === selectedPartId.value);

    if (existingIndex >= 0) {
        form.lines[existingIndex].quantity = selectedQuantity.value;
        form.lines[existingIndex].unit = selectedUnit.value;
        form.lines[existingIndex].remarks = selectedRemarks.value;
    } else {
        form.lines.push({
            part_id: selectedPartId.value,
            quantity: selectedQuantity.value,
            unit: selectedUnit.value,
            remarks: selectedRemarks.value,
        });
    }

    resetAddForm();
};

const removeLine = (index: number) => {
    form.lines.splice(index, 1);
};

const resetAddForm = () => {
    selectedPartId.value = null;
    selectedQuantity.value = 0;
    selectedUnit.value = 'PCS';
    selectedRemarks.value = '';
};

const handlePartChange = () => {
    if (!selectedPart.value) {
        return;
    }

    const deficit = (selectedPart.value.safety_stock || 0) - (selectedPart.value.total_stock || 0);
    selectedQuantity.value = deficit > 0 ? deficit : 1;
};

const submit = () => {
    form.post('/purchase/voucher');
};
</script>

<template>
    <Head title="Register Purchase Voucher" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Register Voucher" description="Buat permintaan pembelian baru secara manual." />
                <Button variant="outline" as-child>
                    <Link href="/purchase/voucher">Back to List</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-5 flex flex-wrap items-center gap-3 rounded-md bg-muted/40 px-3 py-2">
                    <span class="text-xs text-muted-foreground">PV Number (auto)</span>
                    <span class="font-mono text-sm font-semibold">{{ props.nextPvNumber }}</span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="required_date">Required Date</Label>
                            <Input id="required_date" v-model="form.required_date" type="date" />
                            <InputError :message="form.errors.required_date" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Catatan tambahan (opsional)"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <!-- Add Item Panel -->
                    <div class="rounded-md border border-sidebar-border/70 p-3">
                        <div class="mb-3 text-sm font-semibold">Tambah Item</div>
                        <div class="space-y-3">
                            <div class="grid gap-2">
                                <Label for="part_select">Part <span class="text-destructive">*</span></Label>
                                <select
                                    id="part_select"
                                    v-model.number="selectedPartId"
                                    @change="handlePartChange"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option :value="null">- pilih part -</option>
                                    <option v-for="part in props.parts" :key="part.id" :value="part.id">
                                        {{ part.part_number }} - {{ part.name }}
                                        (Safety: {{ part.safety_stock }}, Stock: {{ part.total_stock || 0 }})
                                    </option>
                                </select>
                            </div>

                            <div class="grid gap-3 md:grid-cols-[1fr_0.5fr_1fr_auto]">
                                <div class="grid gap-2">
                                    <Label for="quantity">Quantity <span class="text-destructive">*</span></Label>
                                    <Input id="quantity" v-model.number="selectedQuantity" type="number" step="0.0001" min="0" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="unit">Unit</Label>
                                    <Input id="unit" v-model="selectedUnit" placeholder="PCS" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="item_remarks">Remarks</Label>
                                    <Input id="item_remarks" v-model="selectedRemarks" placeholder="Opsional" />
                                </div>
                                <div class="flex items-end">
                                    <Button type="button" variant="outline" @click="addLine">+ Add</Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lines Table -->
                    <div class="rounded-md border border-sidebar-border/70 p-3">
                        <div class="mb-3 text-sm font-semibold">Items ({{ form.lines.length }})</div>
                        <div v-if="form.lines.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                            Belum ada item. Tambahkan item di atas.
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                        <th class="py-2 pr-3">Part</th>
                                        <th class="py-2 pr-3 text-right">Quantity</th>
                                        <th class="py-2 pr-3 text-center">Unit</th>
                                        <th class="py-2 pr-3">Remarks</th>
                                        <th class="py-2 pr-3 text-center">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(line, index) in form.lines"
                                        :key="index"
                                        class="border-b border-sidebar-border/40 last:border-0"
                                    >
                                        <td class="py-2 pr-3 font-mono text-xs">
                                            {{ props.parts.find((p) => p.id === line.part_id)?.part_number ?? '-' }}
                                            — {{ props.parts.find((p) => p.id === line.part_id)?.name ?? '-' }}
                                        </td>
                                        <td class="py-2 pr-3 text-right font-mono">{{ formatQty(line.quantity) }}</td>
                                        <td class="py-2 pr-3 text-center">{{ line.unit }}</td>
                                        <td class="py-2 pr-3 text-muted-foreground">{{ line.remarks || '-' }}</td>
                                        <td class="py-2 pr-3 text-center">
                                            <button
                                                type="button"
                                                @click="removeLine(index)"
                                                class="text-destructive hover:opacity-75"
                                            >
                                                <X class="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <InputError :message="form.errors.lines" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/purchase/voucher">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.lines.length === 0 || form.processing">
                            Buat Voucher
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
