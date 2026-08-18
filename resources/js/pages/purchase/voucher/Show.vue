<script setup lang="ts">
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatQty } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Part = { id: number; part_number: string; name: string };
type SupplierPrice = { supplier_id: number; supplier_name: string; purchase_price: number };
type PVItem = {
    id: number;
    part_id: number;
    part: Part;
    quantity: number;
    unit: string;
    stock_on_hand: number;
    remarks?: string;
    is_purchasable: boolean;
    supplier_prices: SupplierPrice[];
};
type CO = { id: number; co_number: string };
type UserRef = { id: number; name: string };
type Supplier = { id: number; name: string };
type Currency = { code: string; name: string; symbol: string };

type PurchaseVoucher = {
    id: number;
    pv_number: string;
    status: number;
    source: string;
    items: PVItem[];
    customer_order?: CO;
    created_at: string;
    creator?: UserRef;
    submitted_at?: string;
    approved_at?: string;
    approved_by?: UserRef;
    rejected_at?: string;
    rejected_by?: UserRef;
    approval_notes?: string;
    required_date?: string;
    notes?: string;
};

type Props = {
    purchaseVoucher: PurchaseVoucher;
    suppliers: Supplier[];
    currencies: Currency[];
    defaultCurrency: string;
    canManageApprovals: boolean;
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'List Voucher', href: '/purchase/voucher' },
    { title: props.purchaseVoucher.pv_number, href: `/purchase/voucher/${props.purchaseVoucher.id}` },
];

const convertForm = useForm({
    supplier_id: '',
    order_date: new Date().toISOString().split('T')[0],
    expected_date: '',
    currency_code: props.defaultCurrency,
    notes: '',
    lines: [] as Array<{ purchase_voucher_item_id: number; unit_price: number; remarks: string }>,
});

const approvalForm = useForm({
    approval_notes: '',
});

const selectedItemsForConvert = ref<Record<number, boolean>>({});
const unitPrices = ref<Record<number, number>>({});
const poRemarks = ref<Record<number, string>>({});

const findSupplierPrice = (item: PVItem, supplierId: number | string): SupplierPrice | undefined =>
    item.supplier_prices.find((sp) => sp.supplier_id === Number(supplierId));

const hasNonPurchasableItems = computed(() => props.purchaseVoucher.items.some((item) => !item.is_purchasable));

// When the supplier changes, pre-fill known prices for that supplier so the user
// only has to type a price for parts that genuinely don't have one on file yet.
watch(
    () => convertForm.supplier_id,
    (supplierId) => {
        if (!supplierId) {
            return;
        }

        for (const item of props.purchaseVoucher.items) {
            const match = findSupplierPrice(item, supplierId);

            if (match) {
                unitPrices.value[item.id] = match.purchase_price;
            }
        }
    },
);

const statusColors: Record<number, string> = {
    1: 'bg-amber-100 text-amber-700',
    2: 'bg-blue-100 text-blue-700',
    3: 'bg-emerald-100 text-emerald-700',
    4: 'bg-green-100 text-green-700',
    8: 'bg-rose-100 text-rose-700',
    9: 'bg-gray-100 text-gray-700',
};

const sourceLabels: Record<string, string> = {
    manual: 'Manual',
    co_confirmation: 'From CO',
    stock_recommendation: 'From Stock',
};

const submitForApproval = () => {
    router.post(`/purchase/voucher/${props.purchaseVoucher.id}/submit`, {}, { preserveScroll: true });
};

const approve = () => {
    approvalForm.post(`/purchase/voucher/${props.purchaseVoucher.id}/approve`, { preserveScroll: true });
};

const reject = () => {
    if (!approvalForm.approval_notes.trim()) {
        window.alert('Masukkan alasan rejection');
        return;
    }

    approvalForm.post(`/purchase/voucher/${props.purchaseVoucher.id}/reject`, { preserveScroll: true });
};

const prepareConvertForm = () => {
    convertForm.lines = props.purchaseVoucher.items
        .filter((item) => selectedItemsForConvert.value[item.id])
        .map((item) => ({
            purchase_voucher_item_id: item.id,
            unit_price: unitPrices.value[item.id] || 0,
            remarks: poRemarks.value[item.id] || '',
        }));
};

const convertToPo = () => {
    if (!convertForm.supplier_id) {
        window.alert('Pilih supplier');
        return;
    }

    const selectedItems = props.purchaseVoucher.items.filter((item) => selectedItemsForConvert.value[item.id]);

    if (selectedItems.some((item) => !item.is_purchasable)) {
        window.alert('Ada item yang merupakan part manufacture (punya BOM aktif) dan tidak boleh dibuatkan PO. Batalkan pilihannya terlebih dahulu.');
        return;
    }

    if (selectedItems.some((item) => !unitPrices.value[item.id] || unitPrices.value[item.id] <= 0)) {
        window.alert('Isi harga satuan untuk semua item yang dipilih.');
        return;
    }

    prepareConvertForm();
    convertForm.post(`/purchase/voucher/${props.purchaseVoucher.id}/convert-to-po`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Voucher ${purchaseVoucher.pv_number}`" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading :title="purchaseVoucher.pv_number" description="Detail purchase voucher pembelian." />
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                        :class="statusColors[purchaseVoucher.status]"
                    >
                        {{ statusLabels[String(purchaseVoucher.status)] ?? purchaseVoucher.status }}
                    </span>
                    <Button variant="outline" as-child>
                        <Link href="/purchase/voucher">Back to List</Link>
                    </Button>
                </div>
            </div>

            <!-- Info Card -->
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-3 text-sm font-semibold">Informasi Voucher</div>
                <div class="grid gap-4 text-sm md:grid-cols-4">
                    <div>
                        <span class="text-muted-foreground">Source</span>
                        <div class="mt-1 font-medium">{{ sourceLabels[purchaseVoucher.source] ?? purchaseVoucher.source }}</div>
                        <div v-if="purchaseVoucher.customer_order" class="text-xs text-muted-foreground">
                            ({{ purchaseVoucher.customer_order.co_number }})
                        </div>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Dibuat</span>
                        <div class="mt-1 font-medium">{{ new Date(purchaseVoucher.created_at).toLocaleDateString('id-ID') }}</div>
                        <div class="text-xs text-muted-foreground">oleh {{ purchaseVoucher.creator?.name ?? '-' }}</div>
                    </div>
                    <div v-if="purchaseVoucher.required_date">
                        <span class="text-muted-foreground">Required Date</span>
                        <div class="mt-1 font-medium">{{ new Date(purchaseVoucher.required_date).toLocaleDateString('id-ID') }}</div>
                    </div>
                    <div v-if="purchaseVoucher.notes">
                        <span class="text-muted-foreground">Notes</span>
                        <div class="mt-1 text-sm">{{ purchaseVoucher.notes }}</div>
                    </div>
                </div>

                <div v-if="purchaseVoucher.approval_notes" class="mt-4 rounded-md border border-sidebar-border/50 bg-muted/30 px-3 py-2 text-sm">
                    <span class="font-medium">Catatan Approval:</span> {{ purchaseVoucher.approval_notes }}
                </div>
            </div>

            <!-- Items Table -->
            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-3 text-sm font-semibold">Items ({{ purchaseVoucher.items.length }})</div>
                <div v-if="hasNonPurchasableItems" class="mb-3 rounded-md border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    Voucher ini berisi part manufacture (punya BOM aktif). Part manufacture tidak boleh dibuatkan PO — hanya part purchase yang bisa diconvert.
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">Part</th>
                                <th class="py-2 pr-3 text-right">Quantity</th>
                                <th class="py-2 pr-3 text-center">Unit</th>
                                <th class="py-2 pr-3 text-right">Stock on Hand</th>
                                <th class="py-2 pr-3">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in purchaseVoucher.items"
                                :key="item.id"
                                class="border-b border-sidebar-border/40 last:border-0"
                            >
                                <td class="py-2 pr-3 font-mono text-xs">
                                    {{ item.part.part_number }} — {{ item.part.name }}
                                    <span v-if="!item.is_purchasable" class="ml-1 inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-medium text-rose-700">
                                        Manufacture — tidak bisa di-PO
                                    </span>
                                </td>
                                <td class="py-2 pr-3 text-right font-mono">{{ formatQty(item.quantity) }}</td>
                                <td class="py-2 pr-3 text-center">{{ item.unit }}</td>
                                <td class="py-2 pr-3 text-right font-mono text-muted-foreground">{{ item.stock_on_hand }}</td>
                                <td class="py-2 pr-3 text-muted-foreground">{{ item.remarks ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action: Draft → Submit -->
            <div v-if="purchaseVoucher.status === 1" class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-3 text-sm font-semibold">Submit untuk Approval</div>
                <Button @click="submitForApproval">Submit Voucher</Button>
            </div>

            <!-- Action: Submitted → Approve/Reject (approver only) -->
            <div v-if="purchaseVoucher.status === 2 && canManageApprovals" class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-3 text-sm font-semibold">Approval</div>
                <div class="mb-4 grid gap-2">
                    <Label for="approval_notes">Catatan Approval</Label>
                    <textarea
                        id="approval_notes"
                        v-model="approvalForm.approval_notes"
                        rows="3"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        placeholder="Opsional untuk approve; wajib diisi untuk reject..."
                    />
                </div>
                <div class="flex gap-2">
                    <Button @click="approve">Approve</Button>
                    <Button variant="destructive" @click="reject">Reject</Button>
                </div>
            </div>

            <!-- Action: Approved → Convert to PO -->
            <div v-if="purchaseVoucher.status === 3" class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-3 text-sm font-semibold">Convert ke Purchase Order</div>
                <p class="mb-4 text-sm text-muted-foreground">Pilih item dan masukkan harga satuan untuk membuat purchase order.</p>

                <div class="space-y-5">
                    <!-- Item selection with unit prices -->
                    <div class="rounded-md border border-sidebar-border/50 p-3">
                        <div class="mb-2 text-xs font-semibold text-muted-foreground">Pilih Item untuk Diconvert</div>
                        <div class="space-y-2">
                            <label
                                v-for="item in purchaseVoucher.items"
                                :key="item.id"
                                class="flex flex-wrap items-center gap-3 rounded-md border border-sidebar-border/40 p-3"
                                :class="{ 'opacity-50': !item.is_purchasable }"
                            >
                                <input
                                    v-model="selectedItemsForConvert[item.id]"
                                    type="checkbox"
                                    class="rounded border-input"
                                    :disabled="!item.is_purchasable"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="font-mono text-sm font-medium">
                                        {{ item.part.part_number }} — {{ item.part.name }}
                                        <span v-if="!item.is_purchasable" class="ml-1 text-xs font-sans font-normal text-rose-600">
                                            (manufacture — tidak bisa di-PO)
                                        </span>
                                    </div>
                                    <div class="text-xs text-muted-foreground">Qty: {{ formatQty(item.quantity) }} {{ item.unit }}</div>
                                    <div v-if="item.is_purchasable && convertForm.supplier_id" class="text-xs">
                                        <span v-if="findSupplierPrice(item, convertForm.supplier_id)" class="text-muted-foreground">
                                            Harga tercatat: {{ findSupplierPrice(item, convertForm.supplier_id)?.purchase_price.toLocaleString('id-ID') }}
                                        </span>
                                        <span v-else class="text-amber-600">
                                            Belum ada harga untuk supplier ini — akan disimpan sebagai harga baru di part.
                                        </span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Input
                                        v-model.number="unitPrices[item.id]"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="Harga satuan"
                                        class="w-36"
                                        :disabled="!selectedItemsForConvert[item.id] || !item.is_purchasable"
                                    />
                                    <Input
                                        v-model="poRemarks[item.id]"
                                        type="text"
                                        placeholder="Remarks"
                                        class="w-40"
                                        :disabled="!selectedItemsForConvert[item.id] || !item.is_purchasable"
                                    />
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- PO Details -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="supplier">Supplier <span class="text-destructive">*</span></Label>
                            <select
                                id="supplier"
                                v-model="convertForm.supplier_id"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">- pilih supplier -</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="order_date">Order Date <span class="text-destructive">*</span></Label>
                            <Input id="order_date" v-model="convertForm.order_date" type="date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="expected_date">Expected Date</Label>
                            <Input id="expected_date" v-model="convertForm.expected_date" type="date" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="currency">Currency</Label>
                        <select
                            id="currency"
                            v-model="convertForm.currency_code"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option v-for="curr in currencies" :key="curr.code" :value="curr.code">
                                {{ curr.code }} — {{ curr.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="po_notes">Catatan PO</Label>
                        <textarea
                            id="po_notes"
                            v-model="convertForm.notes"
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Opsional"
                        />
                    </div>

                    <div class="flex gap-2">
                        <Button
                            @click="convertToPo"
                            :disabled="Object.values(selectedItemsForConvert).every((v) => !v) || convertForm.processing"
                        >
                            Buat PO dari Item Terpilih
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
