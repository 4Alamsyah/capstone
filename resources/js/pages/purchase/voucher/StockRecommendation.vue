<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type RecommendedPart = {
    id: number;
    part_number: string;
    name: string;
    safety_stock: number;
    total_stock: number;
    deficit: number;
};

type Props = {
    parts: RecommendedPart[];
    nextPvNumber: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'List Voucher', href: '/purchase/voucher' },
    { title: 'Stock Recommendation', href: '/purchase/voucher/stock-recommendations' },
];

const form = useForm({
    lines: [] as Array<{ part_id: number; quantity: number }>,
});

const selectedParts = ref<Record<number, boolean>>({});
const quantities = ref<Record<number, number>>({});

props.parts.forEach((part) => {
    quantities.value[part.id] = part.deficit;
});

const selectedPartCount = computed(() => Object.values(selectedParts.value).filter(Boolean).length);

const toggleAllSelection = () => {
    const allSelected = selectedPartCount.value === props.parts.length;

    props.parts.forEach((part) => {
        selectedParts.value[part.id] = !allSelected;
    });
};

const submit = () => {
    form.lines = props.parts
        .filter((part) => selectedParts.value[part.id])
        .map((part) => ({
            part_id: part.id,
            quantity: quantities.value[part.id] || part.deficit,
        }));

    if (form.lines.length === 0) {
        window.alert('Pilih minimal satu part');
        return;
    }

    form.post('/purchase/voucher/stock-recommendations');
};
</script>

<template>
    <Head title="Stock Recommendation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Stock Recommendation" description="Parts yang stoknya di bawah safety stock level." />
                <Button variant="outline" as-child>
                    <Link href="/purchase/voucher">Back to List</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
                    Sistem merekomendasikan parts berikut untuk di-order berdasarkan safety stock. Anda dapat menyesuaikan quantity sesuai kebutuhan.
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                :checked="selectedPartCount === props.parts.length && props.parts.length > 0"
                                @change="toggleAllSelection"
                                class="rounded border-input"
                            />
                            <span class="text-sm text-muted-foreground">
                                {{ selectedPartCount }} dari {{ props.parts.length }} terpilih
                            </span>
                        </div>
                        <span class="font-mono text-xs text-muted-foreground">PV: {{ nextPvNumber }}</span>
                    </div>

                    <div v-if="props.parts.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        Semua stok sudah mencukupi safety stock level.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                    <th class="w-10 py-2 pr-3"></th>
                                    <th class="py-2 pr-3">Part Number</th>
                                    <th class="py-2 pr-3">Nama Part</th>
                                    <th class="py-2 pr-3 text-right">Safety Stock</th>
                                    <th class="py-2 pr-3 text-right">Stok Saat Ini</th>
                                    <th class="py-2 pr-3 text-right">Deficit</th>
                                    <th class="py-2 pr-3 text-right">Order Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="part in props.parts"
                                    :key="part.id"
                                    class="border-b border-sidebar-border/40 last:border-0"
                                >
                                    <td class="py-2 pr-3">
                                        <input
                                            v-model="selectedParts[part.id]"
                                            type="checkbox"
                                            class="rounded border-input"
                                        />
                                    </td>
                                    <td class="py-2 pr-3 font-mono text-xs font-medium">{{ part.part_number }}</td>
                                    <td class="py-2 pr-3">{{ part.name }}</td>
                                    <td class="py-2 pr-3 text-right font-mono">{{ part.safety_stock }}</td>
                                    <td class="py-2 pr-3 text-right font-mono">{{ part.total_stock }}</td>
                                    <td class="py-2 pr-3 text-right font-mono font-medium text-rose-600">{{ part.deficit }}</td>
                                    <td class="py-2 pr-3 text-right">
                                        <input
                                            v-model.number="quantities[part.id]"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            :disabled="!selectedParts[part.id]"
                                            class="w-24 rounded-md border border-input bg-transparent px-2 py-1 text-right text-sm disabled:opacity-50"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/purchase/voucher">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="selectedPartCount === 0 || form.processing">
                            Buat PV untuk {{ selectedPartCount }} Item
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
