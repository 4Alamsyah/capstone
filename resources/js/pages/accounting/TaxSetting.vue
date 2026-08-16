<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.tax_setting'), href: '/accounting/tax-setting' },
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
                :title="t('accounting.tax_setting.heading_title')"
                :description="t('accounting.tax_setting.heading_description')"
            />

            <div
                v-if="props.status === 'tax-rate-updated'"
                class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700"
            >
                {{ t('accounting.tax_setting.success_message') }}
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <form class="max-w-xs space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="tax_rate">{{ t('accounting.tax_setting.tax_rate_label') }}</Label>
                        <Input id="tax_rate" v-model="form.tax_rate" type="number" min="0" max="100" step="any" />
                        <InputError :message="form.errors.tax_rate" />
                        <p class="text-xs text-muted-foreground">
                            {{ t('accounting.tax_setting.tax_rate_hint') }}
                        </p>
                    </div>

                    <Button type="submit" :disabled="form.processing">{{ t('accounting.tax_setting.save_button') }}</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
