<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';

type CurrencyItem = {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    is_active: boolean;
    is_default: boolean;
};

type Props = {
    settings: {
        default_currency_code: string;
    };
    currencies: CurrencyItem[];
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'General Settings', href: '/settings/general' },
];

const settingsForm = useForm({
    default_currency_code: props.settings.default_currency_code,
});

const currencyForm = useForm({
    code: '',
    name: '',
    symbol: '',
    is_active: true,
    set_as_default: false,
});

const submitSettings = () => {
    settingsForm.patch('/settings/general');
};

const addCurrency = () => {
    currencyForm.post('/settings/general/currencies', {
        onSuccess: () => {
            currencyForm.reset();
            currencyForm.is_active = true;
            currencyForm.set_as_default = false;
        },
    });
};

const setDefaultCurrency = (currency: CurrencyItem) => {
    settingsForm.patch(`/settings/general/currencies/${currency.id}/default`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="General Settings" />

        <h1 class="sr-only">General Settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="General Settings"
                    description="Manage global app settings and currency defaults"
                />

                <form class="space-y-6" @submit.prevent="submitSettings">
                    <div class="rounded-lg border border-sidebar-border/70 p-4">
                        <h3 class="mb-4 text-sm font-semibold">Application Defaults</h3>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="default_currency_code">Default Currency</Label>
                                <select
                                    id="default_currency_code"
                                    v-model="settingsForm.default_currency_code"
                                    class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option v-for="currency in props.currencies" :key="currency.id" :value="currency.code">
                                        {{ currency.code }} - {{ currency.name }}
                                    </option>
                                </select>
                                <InputError :message="settingsForm.errors.default_currency_code" />
                            </div>

                            <div class="grid gap-2">
                                <div class="grid gap-2">
                                    <p class="text-sm font-medium">Work Order Format</p>
                                    <p class="text-xs text-muted-foreground">
                                        WO format configuration is available in <a href="/settings/app" class="font-semibold text-blue-600 hover:underline">App Settings</a>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex">
                            <Button type="submit" :disabled="settingsForm.processing">Save General Settings</Button>
                        </div>
                    </div>
                </form>

                <form class="space-y-6" @submit.prevent="addCurrency">
                    <div class="rounded-lg border border-sidebar-border/70 p-4">
                        <h3 class="mb-4 text-sm font-semibold">Add Currency</h3>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="currency_code">Currency Code</Label>
                                <Input id="currency_code" v-model="currencyForm.code" placeholder="IDR" maxlength="3" class="uppercase" />
                                <InputError :message="currencyForm.errors.code" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="currency_name">Currency Name</Label>
                                <Input id="currency_name" v-model="currencyForm.name" placeholder="Indonesian Rupiah" maxlength="100" />
                                <InputError :message="currencyForm.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="currency_symbol">Symbol</Label>
                                <Input id="currency_symbol" v-model="currencyForm.symbol" placeholder="Rp" maxlength="10" />
                                <InputError :message="currencyForm.errors.symbol" />
                            </div>

                            <div class="grid gap-2">
                                <Label>Options</Label>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input v-model="currencyForm.is_active" type="checkbox" class="h-4 w-4 rounded border-input" />
                                    Active
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input v-model="currencyForm.set_as_default" type="checkbox" class="h-4 w-4 rounded border-input" />
                                    Set as default
                                </label>
                            </div>
                        </div>

                        <div class="mt-4 flex">
                            <Button type="submit" :disabled="currencyForm.processing">Add Currency</Button>
                        </div>
                    </div>
                </form>

                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-4 text-sm font-semibold">Currency List</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                            <thead class="bg-sidebar-accent/40 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Code</th>
                                    <th class="px-3 py-2 font-medium">Name</th>
                                    <th class="px-3 py-2 font-medium">Symbol</th>
                                    <th class="px-3 py-2 font-medium">Status</th>
                                    <th class="px-3 py-2 font-medium">Default</th>
                                    <th class="px-3 py-2 font-medium text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="currency in props.currencies" :key="currency.id">
                                    <td class="px-3 py-2 font-mono font-semibold">{{ currency.code }}</td>
                                    <td class="px-3 py-2">{{ currency.name }}</td>
                                    <td class="px-3 py-2">{{ currency.symbol || '-' }}</td>
                                    <td class="px-3 py-2">{{ currency.is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="px-3 py-2">{{ currency.is_default ? 'Yes' : 'No' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <Button
                                            v-if="!currency.is_default"
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            @click="setDefaultCurrency(currency)"
                                        >
                                            Set Default
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!props.currencies.length">
                                    <td colspan="6" class="px-3 py-6 text-center text-muted-foreground">
                                        No currencies found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="status === 'settings-updated' || status === 'currency-added' || status === 'default-currency-updated'" class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                    Settings saved.
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
