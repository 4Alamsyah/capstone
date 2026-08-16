<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

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
};

type Props = {
    mapping: Mapping;
    accounts: AccountOption[];
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'GL Setting', href: '/accounting/gl-setting' },
];

const form = useForm({
    gl_ar_account_id: (props.mapping.gl_ar_account_id ?? '') as unknown as number,
    gl_sales_revenue_account_id: (props.mapping.gl_sales_revenue_account_id ?? '') as unknown as number,
    gl_sales_tax_payable_account_id: (props.mapping.gl_sales_tax_payable_account_id ?? '') as unknown as number,
    gl_cash_bank_account_id: (props.mapping.gl_cash_bank_account_id ?? '') as unknown as number,
    gl_ap_account_id: (props.mapping.gl_ap_account_id ?? '') as unknown as number,
    gl_purchase_expense_account_id: (props.mapping.gl_purchase_expense_account_id ?? '') as unknown as number,
    gl_purchase_tax_input_account_id: (props.mapping.gl_purchase_tax_input_account_id ?? '') as unknown as number,
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
                title="GL Setting"
                description="Pemetaan Chart of Account yang dipakai untuk auto-posting jurnal AR (Accounts Receivable) dan AP (Accounts Payable)."
            />

            <div
                v-if="props.status === 'gl-mapping-updated'"
                class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700"
            >
                GL Account Mapping berhasil disimpan.
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <form class="max-w-md space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="gl_ar_account_id">Accounts Receivable Account</Label>
                        <select
                            id="gl_ar_account_id"
                            v-model="form.gl_ar_account_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">- pilih akun -</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gl_ar_account_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="gl_sales_revenue_account_id">Sales Revenue Account</Label>
                        <select
                            id="gl_sales_revenue_account_id"
                            v-model="form.gl_sales_revenue_account_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">- pilih akun -</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gl_sales_revenue_account_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="gl_sales_tax_payable_account_id">Sales Tax Payable Account</Label>
                        <select
                            id="gl_sales_tax_payable_account_id"
                            v-model="form.gl_sales_tax_payable_account_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">- pilih akun -</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gl_sales_tax_payable_account_id" />
                        <p class="text-xs text-muted-foreground">Hanya dipakai kalau tax rate invoice &gt; 0.</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="gl_cash_bank_account_id">Cash / Bank Account</Label>
                        <select
                            id="gl_cash_bank_account_id"
                            v-model="form.gl_cash_bank_account_id"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">- pilih akun -</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">
                                {{ account.code }} - {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gl_cash_bank_account_id" />
                        <p class="text-xs text-muted-foreground">Dipakai untuk semua metode pembayaran (cash/transfer/cheque), baik penerimaan AR maupun pembayaran AP.</p>
                    </div>

                    <div class="border-t border-sidebar-border/70 pt-4">
                        <h3 class="mb-3 text-sm font-semibold">Accounts Payable (AP)</h3>

                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="gl_ap_account_id">Accounts Payable Account</Label>
                                <select
                                    id="gl_ap_account_id"
                                    v-model="form.gl_ap_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">- pilih akun -</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_ap_account_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="gl_purchase_expense_account_id">Purchase Expense / Inventory Account</Label>
                                <select
                                    id="gl_purchase_expense_account_id"
                                    v-model="form.gl_purchase_expense_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">- pilih akun -</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_purchase_expense_account_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="gl_purchase_tax_input_account_id">Purchase Tax Input Account</Label>
                                <select
                                    id="gl_purchase_tax_input_account_id"
                                    v-model="form.gl_purchase_tax_input_account_id"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">- pilih akun -</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} - {{ account.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.gl_purchase_tax_input_account_id" />
                                <p class="text-xs text-muted-foreground">Hanya dipakai kalau tax rate AP invoice &gt; 0.</p>
                            </div>
                        </div>
                    </div>

                    <Button type="submit" :disabled="form.processing">Save Mapping</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
