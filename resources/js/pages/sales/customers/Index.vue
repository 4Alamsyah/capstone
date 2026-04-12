<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type CustomerItem = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    shipping_address: string | null;
    payment_terms: string | null;
    currency_code: string;
    created_at: string;
};

type CurrencyOption = {
    code: string;
    name: string;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    customers: CustomerItem[];
    filters: {
        search: string;
    };
    defaultCurrency: string;
    currencies: CurrencyOption[];
    paymentTermsOptions: string[];
    pagination: PaginationMeta;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Sales',
        href: '/sales/customer-orders',
    },
    {
        title: 'Customer Register',
        href: '/sales/customers',
    },
];

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    shipping_address: '',
    payment_terms: '',
    currency_code: '',
});

const searchForm = useForm({
    search: props.filters.search ?? '',
});

const editId = ref<number | null>(null);

const editForm = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    shipping_address: '',
    payment_terms: '',
    currency_code: '',
});

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No customer data';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} customers`;
});

const submit = () => {
    form.post('/sales/customers', {
        onSuccess: () => {
            form.reset();
            form.currency_code = '';
        },
    });
};

const submitSearch = () => {
    searchForm.get('/sales/customers', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    submitSearch();
};

const startEdit = (customer: CustomerItem) => {
    editId.value = customer.id;
    editForm.name = customer.name;
    editForm.email = customer.email ?? '';
    editForm.phone = customer.phone ?? '';
    editForm.address = customer.address ?? '';
    editForm.shipping_address = customer.shipping_address ?? '';
    editForm.payment_terms = customer.payment_terms ?? '';
    editForm.currency_code = customer.currency_code ?? '';
    editForm.clearErrors();
};

const cancelEdit = () => {
    editId.value = null;
    editForm.reset();
    editForm.currency_code = '';
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!editId.value) {
        return;
    }

    editForm.put(`/sales/customers/${editId.value}`, {
        onSuccess: () => {
            cancelEdit();
        },
    });
};

const deleteCustomer = (customer: CustomerItem) => {
    if (!window.confirm(`Hapus customer ${customer.name}?`)) {
        return;
    }

    useForm({}).delete(`/sales/customers/${customer.id}`);
};
</script>

<template>
    <Head title="Customer Register" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <Heading
                title="Customer Register"
                description="Data customer untuk Register CO. Shipping address, payment terms, dan currency akan terisi otomatis."
            />

            <div class="grid gap-6 lg:grid-cols-[440px_1fr]">
                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-4 text-sm font-semibold">Add Customer</h3>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="name">Customer Name</Label>
                            <Input id="name" v-model="form.name" placeholder="Nama customer" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="form.email" type="email" placeholder="Email customer" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="form.phone" placeholder="No telepon" />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="payment_terms">Payment Terms</Label>
                            <select
                                id="payment_terms"
                                v-model="form.payment_terms"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">- pilih payment terms -</option>
                                <option v-for="term in props.paymentTermsOptions" :key="`create-${term}`" :value="term">{{ term }}</option>
                            </select>
                            <InputError :message="form.errors.payment_terms" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="currency_code">Currency (Optional)</Label>
                            <select
                                id="currency_code"
                                v-model="form.currency_code"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">Use default ({{ props.defaultCurrency }})</option>
                                <option v-for="currency in props.currencies" :key="currency.code" :value="currency.code">
                                    {{ currency.code }} - {{ currency.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.currency_code" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address">Address</Label>
                            <textarea
                                id="address"
                                v-model="form.address"
                                rows="2"
                                class="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                placeholder="Alamat invoice"
                            />
                            <InputError :message="form.errors.address" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="shipping_address">Shipping Address</Label>
                            <textarea
                                id="shipping_address"
                                v-model="form.shipping_address"
                                rows="2"
                                class="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                placeholder="Alamat pengiriman default"
                            />
                            <InputError :message="form.errors.shipping_address" />
                        </div>

                        <Button type="submit" class="w-full" :disabled="form.processing">Save Customer</Button>
                    </form>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold">Customer List</h3>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" as-child>
                                <Link href="/sales/customer-orders/create">+ Register CO</Link>
                            </Button>
                        </div>
                    </div>

                    <form class="mb-4 flex w-full flex-wrap items-center gap-2" @submit.prevent="submitSearch">
                        <Input v-model="searchForm.search" placeholder="Search customer..." class="w-full sm:w-96" />
                        <Button type="submit" variant="outline">Search</Button>
                        <Button v-if="searchForm.search" type="button" variant="ghost" @click="clearSearch">Clear</Button>
                    </form>

                    <div v-if="editId !== null" class="mb-4 rounded-md border border-sidebar-border/70 p-3">
                        <h4 class="mb-3 text-sm font-semibold">Edit Customer</h4>

                        <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submitEdit">
                            <div class="grid gap-2">
                                <Label for="edit-name">Customer Name</Label>
                                <Input id="edit-name" v-model="editForm.name" />
                                <InputError :message="editForm.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-email">Email</Label>
                                <Input id="edit-email" v-model="editForm.email" type="email" />
                                <InputError :message="editForm.errors.email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-phone">Phone</Label>
                                <Input id="edit-phone" v-model="editForm.phone" />
                                <InputError :message="editForm.errors.phone" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-payment-terms">Payment Terms</Label>
                                <select
                                    id="edit-payment-terms"
                                    v-model="editForm.payment_terms"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">- pilih payment terms -</option>
                                    <option v-for="term in props.paymentTermsOptions" :key="`edit-${term}`" :value="term">{{ term }}</option>
                                </select>
                                <InputError :message="editForm.errors.payment_terms" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-currency">Currency (Optional)</Label>
                                <select
                                    id="edit-currency"
                                    v-model="editForm.currency_code"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">Use default ({{ props.defaultCurrency }})</option>
                                    <option v-for="currency in props.currencies" :key="`edit-${currency.code}`" :value="currency.code">
                                        {{ currency.code }} - {{ currency.name }}
                                    </option>
                                </select>
                                <InputError :message="editForm.errors.currency_code" />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="edit-address">Address</Label>
                                <textarea
                                    id="edit-address"
                                    v-model="editForm.address"
                                    rows="2"
                                    class="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                />
                                <InputError :message="editForm.errors.address" />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="edit-shipping-address">Shipping Address</Label>
                                <textarea
                                    id="edit-shipping-address"
                                    v-model="editForm.shipping_address"
                                    rows="2"
                                    class="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                />
                                <InputError :message="editForm.errors.shipping_address" />
                            </div>

                            <div class="flex gap-2 md:col-span-2">
                                <Button type="submit" :disabled="editForm.processing">Update</Button>
                                <Button type="button" variant="outline" @click="cancelEdit">Cancel</Button>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                            <thead class="bg-sidebar-accent/40 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Name</th>
                                    <th class="px-3 py-2 font-medium">Email</th>
                                    <th class="px-3 py-2 font-medium">Terms</th>
                                    <th class="px-3 py-2 font-medium">Currency</th>
                                    <th class="px-3 py-2 font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr v-for="customer in customers" :key="customer.id" class="align-top">
                                    <td class="px-3 py-2 font-medium">
                                        {{ customer.name }}
                                        <div class="text-xs text-muted-foreground">{{ customer.phone || '-' }}</div>
                                    </td>
                                    <td class="px-3 py-2">{{ customer.email || '-' }}</td>
                                    <td class="px-3 py-2">{{ customer.payment_terms || '-' }}</td>
                                    <td class="px-3 py-2">{{ customer.currency_code }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-2">
                                            <Button type="button" variant="outline" size="sm" @click="startEdit(customer)">
                                                Edit
                                            </Button>
                                            <Button type="button" variant="destructive" size="sm" @click="deleteCustomer(customer)">
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!customers.length">
                                    <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">
                                        Customer tidak ditemukan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-sidebar-border/70 pt-4">
                        <p class="text-sm text-muted-foreground">{{ paginationText }}</p>
                        <div class="flex items-center gap-2">
                            <Button v-if="pagination.prev_page_url" variant="outline" as-child>
                                <Link :href="pagination.prev_page_url">Previous</Link>
                            </Button>
                            <span class="text-sm text-muted-foreground">
                                Page {{ pagination.current_page }} / {{ pagination.last_page }}
                            </span>
                            <Button v-if="pagination.next_page_url" variant="outline" as-child>
                                <Link :href="pagination.next_page_url">Next</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
