<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type BomOption = {
    id: number;
    name: string;
    part: { id: number; part_number: string; name: string };
};

type Props = {
    boms: BomOption[];
    nextWoNumber: string;
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manufacture Order', href: '/work-orders' },
    { title: 'Create', href: '/work-orders/create' },
];

const form = useForm({
    bom_id: '' as unknown as number,
    quantity: '1',
    scheduled_date: '',
    notes: '',
});

const submit = () => {
    form.post('/work-orders');
};
</script>

<template>
    <Head title="Create Manufacture Order" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <Heading title="Create Manufacture Order" description="Buat MO baru berdasarkan BOM yang sudah aktif." />
                <Button variant="outline" as-child>
                    <Link href="/work-orders">← Back</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <!-- Preview MO number -->
                <div class="mb-5 flex items-center gap-3 rounded-md bg-muted/40 px-3 py-2">
                    <span class="text-xs text-muted-foreground">MO Number (auto)</span>
                    <span class="font-mono text-sm font-semibold">{{ nextWoNumber }}</span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="bom_id">BOM / Product <span class="text-destructive">*</span></Label>
                        <select
                            id="bom_id"
                            v-model="form.bom_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">— pilih BOM —</option>
                            <option v-for="bom in boms" :key="bom.id" :value="bom.id">
                                {{ bom.part.part_number }} – {{ bom.part.name }} ({{ bom.name }})
                            </option>
                        </select>
                        <InputError :message="form.errors.bom_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="quantity">Quantity <span class="text-destructive">*</span></Label>
                        <Input id="quantity" v-model="form.quantity" type="number" min="0.0001" step="any" placeholder="1" />
                        <InputError :message="form.errors.quantity" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="scheduled_date">Scheduled Date</Label>
                        <Input id="scheduled_date" v-model="form.scheduled_date" type="date" />
                        <InputError :message="form.errors.scheduled_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="opsional"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/work-orders">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Create Manufacture Order</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
