<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

type AccountOption = {
    id: number;
    code: string;
    name: string;
};

type Mapping = {
    gl_ar_account_id: number | null;
    gl_sales_revenue_account_id: number | null;
    gl_sales_tax_payable_account_id: number | null;
    gl_cash_bank_account_id: number | null;
    gl_ap_account_id: number | null;
    gl_purchase_expense_account_id: number | null;
    gl_purchase_tax_input_account_id: number | null;
    gl_realized_fx_gain_account_id: number | null;
    gl_realized_fx_loss_account_id: number | null;
    gl_unrealized_fx_gain_account_id: number | null;
    gl_unrealized_fx_loss_account_id: number | null;
};

type Props = {
    mapping: Mapping;
    accounts: AccountOption[];
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.gl_setting'), href: '/accounting/gl-setting' },
];

const form = useForm({
    gl_ar_account_id: (props.mapping.gl_ar_account_id ?? '') as unknown as number,
    gl_sales_revenue_account_id: (props.mapping.gl_sales_revenue_account_id ?? '') as unknown as number,
    gl_sales_tax_payable_account_id: (props.mapping.gl_sales_tax_payable_account_id ?? '') as unknown as number,
    gl_cash_bank_account_id: (props.mapping.gl_cash_bank_account_id ?? '') as unknown as number,
    gl_ap_account_id: (props.mapping.gl_ap_account_id ?? '') as unknown as number,
    gl_purchase_expense_account_id: (props.mapping.gl_purchase_expense_account_id ?? '') as unknown as number,
    gl_purchase_tax_input_account_id: (props.mapping.gl_purchase_tax_input_account_id ?? '') as unknown as number,
    gl_realized_fx_gain_account_id: (props.mapping.gl_realized_fx_gain_account_id ?? '') as unknown as number,
    gl_realized_fx_loss_account_id: (props.mapping.gl_realized_fx_loss_account_id ?? '') as unknown as number,
    gl_unrealized_fx_gain_account_id: (props.mapping.gl_unrealized_fx_gain_account_id ?? '') as unknown as number,
    gl_unrealized_fx_loss_account_id: (props.mapping.gl_unrealized_fx_loss_account_id ?? '') as unknown as number,
});

const submit = () => {
    form.patch('/accounting/gl-setting');
};
</script>

<template>
    <Head title="GL Setting" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
            <Heading
                :title="t('accounting.gl_setting.heading_title')"
                :description="t('accounting.gl_setting.heading_description')"
            />

            <div
                v-if="props.status === 'gl-mapping-updated'"
                class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700"
            >
                {{ t('accounting.gl_setting.success_message') }}
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <form class="max-w-md space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="gl_ar_account_id">{{ t('accounting.gl_setting.ar_account_label') }}</Label>
                        <select
                            id="gl_ar_account_id"
                            v-model="form.gl_ar_account_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gl_ar_account_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="gl_sales_revenue_account_id">{{ t('accounting.gl_setting.sales_revenue_label') }}</Label>
                        <select
                            id="gl_sales_revenue_account_id"
                            v-model="form.gl_sales_revenue_account_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gl_sales_revenue_account_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="gl_sales_tax_payable_account_id">{{ t('accounting.gl_setting.sales_tax_payable_label') }}</Label>
                        <select
                            id="gl_sales_tax_payable_account_id"
                            v-model="form.gl_sales_tax_payable_account_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gl_sales_tax_payable_account_id" />
                        <p class="text-xs text-muted-foreground">{{ t('accounting.gl_setting.sales_tax_payable_hint') }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="gl_cash_bank_account_id">{{ t('accounting.gl_setting.cash_bank_label') }}</Label>
                        <select
                            id="gl_cash_bank_account_id"
                            v-model="form.gl_cash_bank_account_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gl_cash_bank_account_id" />
                        <p class="text-xs text-muted-foreground">{{ t('accounting.gl_setting.cash_bank_hint') }}</p>
                    </div>

                    <div class="border-t border-sidebar-border/70 pt-4">
                        <h3 class="mb-3 text-sm font-semibold">{{ t('accounting.gl_setting.ap_section_heading') }}</h3>

                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="gl_ap_account_id">{{ t('accounting.gl_setting.ap_account_label') }}</Label>
                                <select
                                    id="gl_ap_account_id"
                                    v-model="form.gl_ap_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_ap_account_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="gl_purchase_expense_account_id">{{ t('accounting.gl_setting.purchase_expense_label') }}</Label>
                                <select
                                    id="gl_purchase_expense_account_id"
                                    v-model="form.gl_purchase_expense_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_purchase_expense_account_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="gl_purchase_tax_input_account_id">{{ t('accounting.gl_setting.purchase_tax_input_label') }}</Label>
                                <select
                                    id="gl_purchase_tax_input_account_id"
                                    v-model="form.gl_purchase_tax_input_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_purchase_tax_input_account_id" />
                                <p class="text-xs text-muted-foreground">{{ t('accounting.gl_setting.purchase_tax_input_hint') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border/70 pt-4">
                        <h3 class="mb-3 text-sm font-semibold">{{ t('accounting.gl_setting.fx_section_heading') }}</h3>
                        <p class="mb-3 text-xs text-muted-foreground">{{ t('accounting.gl_setting.fx_section_hint') }}</p>

                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="gl_realized_fx_gain_account_id">{{ t('accounting.gl_setting.realized_fx_gain_label') }}</Label>
                                <select
                                    id="gl_realized_fx_gain_account_id"
                                    v-model="form.gl_realized_fx_gain_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_realized_fx_gain_account_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="gl_realized_fx_loss_account_id">{{ t('accounting.gl_setting.realized_fx_loss_label') }}</Label>
                                <select
                                    id="gl_realized_fx_loss_account_id"
                                    v-model="form.gl_realized_fx_loss_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_realized_fx_loss_account_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="gl_unrealized_fx_gain_account_id">{{ t('accounting.gl_setting.unrealized_fx_gain_label') }}</Label>
                                <select
                                    id="gl_unrealized_fx_gain_account_id"
                                    v-model="form.gl_unrealized_fx_gain_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_unrealized_fx_gain_account_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="gl_unrealized_fx_loss_account_id">{{ t('accounting.gl_setting.unrealized_fx_loss_label') }}</Label>
                                <select
                                    id="gl_unrealized_fx_loss_account_id"
                                    v-model="form.gl_unrealized_fx_loss_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">{{ t('accounting.gl_setting.select_account_option') }}</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_unrealized_fx_loss_account_id" />
                            </div>
                        </div>
                    </div>

                    <Button type="submit" :disabled="form.processing">{{ t('accounting.gl_setting.save_mapping_button') }}</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
