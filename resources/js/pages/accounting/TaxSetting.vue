<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    taxRate: number;
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'Tax Setting', href: '/accounting/tax-setting' },
];

const form = useForm({
    tax_rate: String(props.taxRate),
});

const submit = () => {
    form.patch('/accounting/tax-setting');
};
</script>

<template>
    <Head title="Tax Setting" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
            <Heading
                title="Tax Setting"
                description="Atur persentase pajak (%) yang otomatis dipakai untuk menghitung tax amount pada Sales Invoice."
            />

            <div
                v-if="props.status === 'tax-rate-updated'"
                class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700"
            >
                Tax rate berhasil disimpan.
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <form class="max-w-xs space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="tax_rate">Tax Rate (%)</Label>
                        <Input id="tax_rate" v-model="form.tax_rate" type="number" min="0" max="100" step="any" />
                        <InputError :message="form.errors.tax_rate" />
                        <p class="text-xs text-muted-foreground">
                            Contoh: isi 11 untuk PPN 11%. Isi 0 kalau invoice tidak dikenakan pajak.
                        </p>
                    </div>

                    <Button type="submit" :disabled="form.processing">Save Tax Rate</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
