<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type DashboardAnalytics = {
	summary: {
		totalSuppliers: number;
		totalParts: number;
		totalWorkCenters: number;
		totalCustomers: number;
		totalBoms: number;
		lowStockItems: number;
	};
	workOrders: {
		byStatus: {
			pending: number;
			inProgress: number;
			completed: number;
			cancelled: number;
		};
		total: number;
		recentWorkOrders: Array<{
			id: number;
			wo_number: string;
			status: string;
			bom_name: string;
		}>;
	};
	customerOrders: {
		byStatus: {
			registered: number;
			confirmed: number;
			picking: number;
			delivered: number;
		};
		total: number;
		monthlyTrend: {
			labels: string[];
			data: number[];
		};
		topCustomers: Array<{
			customer_name: string;
			orders_count: number;
		}>;
	};
	inventory: {
		total: number;
		lowStock: number;
		overstock: number;
		materialTotal: number;
		toolTotal: number;
		activeToolLoans: number;
		topStockItems: Array<{
			part_name: string;
			inventory_type: 'material' | 'tool' | null;
			quantity: number;
			warehouse: string;
		}>;
	};
	suppliers: {
		total: number;
		topSuppliers: Array<{
			supplier_name: string;
			parts_count: number;
		}>;
	};
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const analytics = ref<DashboardAnalytics | null>(null);
const loading = ref(true);
const errorMessage = ref('');

const statusLabel: Record<string, string> = {
	pending: 'Pending',
	in_progress: 'In Progress',
	completed: 'Completed',
	cancelled: 'Cancelled',
};

const statusBadgeClass: Record<string, string> = {
	pending: 'bg-amber-100 text-amber-700',
	in_progress: 'bg-blue-100 text-blue-700',
	completed: 'bg-green-100 text-green-700',
	cancelled: 'bg-rose-100 text-rose-700',
};

const formatNumber = (value: number) => new Intl.NumberFormat('id-ID').format(value ?? 0);

const summaryCards = computed(() => {
	if (!analytics.value) {
		return [];
	}

	const { summary } = analytics.value;

	return [
		{ label: 'Total Supplier', value: summary.totalSuppliers, href: '/suppliers' },
		{ label: 'Total Part', value: summary.totalParts, href: '/parts' },
		{ label: 'Work Center', value: summary.totalWorkCenters, href: '/work-centers' },
		{ label: 'Customer', value: summary.totalCustomers, href: '/sales/customers' },
		{ label: 'BOM Aktif', value: summary.totalBoms, href: '/bom' },
		{ label: 'Part Low Stock', value: summary.lowStockItems, href: '/parts/stock' },
	];
});

const maxMonthlyOrder = computed(() => {
	if (!analytics.value) {
		return 1;
	}

	return Math.max(...analytics.value.customerOrders.monthlyTrend.data, 1);
});

const refreshAnalytics = async () => {
	loading.value = true;
	errorMessage.value = '';

	try {
		const response = await fetch('/api/dashboard/analytics', {
			headers: {
				Accept: 'application/json',
			},
		});

		if (!response.ok) {
			throw new Error('Tidak bisa memuat analytics dashboard.');
		}

		analytics.value = (await response.json()) as DashboardAnalytics;
	} catch (error) {
		errorMessage.value = error instanceof Error ? error.message : 'Terjadi kesalahan saat mengambil data.';
	} finally {
		loading.value = false;
	}
};

onMounted(() => {
	refreshAnalytics();
});
</script>

<template>
	<Head title="Dashboard" />

	<AppLayout :breadcrumbs="breadcrumbs">
		<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
			<div class="flex flex-wrap items-start justify-between gap-3">
				<Heading
					title="Dashboard Analisis"
					description="Ringkasan performa modul Procurement, Inventory, Sales, dan Produksi."
				/>

				<Button variant="outline" :disabled="loading" @click="refreshAnalytics">
					{{ loading ? 'Memuat...' : 'Refresh Data' }}
				</Button>
			</div>

			<div
				v-if="errorMessage"
				class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
			>
				{{ errorMessage }}
			</div>

			<div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
				<div
					v-for="index in 6"
					:key="index"
					class="h-28 animate-pulse rounded-lg border border-sidebar-border/70 bg-muted/40"
				/>
			</div>

			<template v-else-if="analytics">
				<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
					<Link
						v-for="card in summaryCards"
						:key="card.label"
						:href="card.href"
						class="rounded-lg border border-sidebar-border/70 bg-card p-4 transition-colors hover:border-primary/50"
					>
						<p class="text-xs uppercase tracking-wide text-muted-foreground">{{ card.label }}</p>
						<p class="mt-2 text-3xl font-semibold">{{ formatNumber(card.value) }}</p>
					</Link>
				</div>

				<div class="grid gap-4 lg:grid-cols-2">
					<div class="rounded-lg border border-sidebar-border/70 p-4">
						<div class="mb-4 flex items-center justify-between">
							<h3 class="text-sm font-semibold">Status Manufacture Order</h3>
							<Link href="/work-orders" class="text-xs text-primary hover:underline">Lihat Modul</Link>
						</div>

						<div class="grid gap-3 sm:grid-cols-2">
							<div class="rounded-md bg-muted/40 p-3">
								<p class="text-xs text-muted-foreground">Total MO</p>
								<p class="mt-1 text-2xl font-semibold">{{ formatNumber(analytics.workOrders.total) }}</p>
							</div>
							<div class="rounded-md bg-muted/40 p-3">
								<p class="text-xs text-muted-foreground">Total Sales Order</p>
								<p class="mt-1 text-2xl font-semibold">{{ formatNumber(analytics.customerOrders.total) }}</p>
							</div>
						</div>

						<div class="mt-4 space-y-3">
							<div class="flex items-center justify-between text-sm">
								<span>Pending</span>
								<strong>{{ formatNumber(analytics.workOrders.byStatus.pending) }}</strong>
							</div>
							<div class="flex items-center justify-between text-sm">
								<span>In Progress</span>
								<strong>{{ formatNumber(analytics.workOrders.byStatus.inProgress) }}</strong>
							</div>
							<div class="flex items-center justify-between text-sm">
								<span>Completed</span>
								<strong>{{ formatNumber(analytics.workOrders.byStatus.completed) }}</strong>
							</div>
							<div class="flex items-center justify-between text-sm">
								<span>Cancelled</span>
								<strong>{{ formatNumber(analytics.workOrders.byStatus.cancelled) }}</strong>
							</div>
						</div>
					</div>

					<div class="rounded-lg border border-sidebar-border/70 p-4">
						<div class="mb-4 flex items-center justify-between">
							<h3 class="text-sm font-semibold">Tren Customer Order (6 Bulan)</h3>
							<Link href="/sales/customer-orders" class="text-xs text-primary hover:underline">Lihat Modul</Link>
						</div>

						<div class="space-y-3">
							<div
								v-for="(value, index) in analytics.customerOrders.monthlyTrend.data"
								:key="analytics.customerOrders.monthlyTrend.labels[index]"
								class="grid grid-cols-[86px_1fr_auto] items-center gap-3"
							>
								<span class="text-xs text-muted-foreground">{{ analytics.customerOrders.monthlyTrend.labels[index] }}</span>
								<div class="h-2 overflow-hidden rounded-full bg-muted">
									<div
										class="h-2 rounded-full bg-primary"
										:style="{ width: `${(value / maxMonthlyOrder) * 100}%` }"
									/>
								</div>
								<span class="text-sm font-medium">{{ value }}</span>
							</div>
						</div>
					</div>
				</div>

				<div class="grid gap-4 lg:grid-cols-3">
					<div class="rounded-lg border border-sidebar-border/70 p-4 lg:col-span-2">
						<div class="mb-4 flex items-center justify-between">
							<h3 class="text-sm font-semibold">Manufacture Order Terbaru</h3>
							<Link href="/work-orders" class="text-xs text-primary hover:underline">Lihat Semua</Link>
						</div>

						<div class="overflow-x-auto">
							<table class="w-full text-sm">
								<thead>
									<tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
										<th class="py-2 pr-3">MO Number</th>
										<th class="py-2 pr-3">BOM</th>
										<th class="py-2">Status</th>
									</tr>
								</thead>
								<tbody>
									<tr v-if="analytics.workOrders.recentWorkOrders.length === 0">
										<td colspan="3" class="py-6 text-center text-muted-foreground">Belum ada manufacture order.</td>
									</tr>
									<tr
										v-for="item in analytics.workOrders.recentWorkOrders"
										:key="item.id"
										class="border-b border-sidebar-border/40 last:border-0"
									>
										<td class="py-2 pr-3 font-mono">{{ item.wo_number }}</td>
										<td class="py-2 pr-3 text-muted-foreground">{{ item.bom_name }}</td>
										<td class="py-2">
											<span
												class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
												:class="statusBadgeClass[item.status] ?? 'bg-muted text-foreground'"
											>
												{{ statusLabel[item.status] ?? item.status }}
											</span>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<div class="rounded-lg border border-sidebar-border/70 p-4">
						<h3 class="mb-3 text-sm font-semibold">Kondisi Inventory</h3>

						<div class="space-y-3 text-sm">
							<div class="flex items-center justify-between rounded-md bg-muted/40 px-3 py-2">
								<span>Total Item Stok</span>
								<strong>{{ formatNumber(analytics.inventory.total) }}</strong>
							</div>
							<div class="flex items-center justify-between rounded-md bg-emerald-50 px-3 py-2 text-emerald-700">
								<span>Material Item</span>
								<strong>{{ formatNumber(analytics.inventory.materialTotal) }}</strong>
							</div>
							<div class="flex items-center justify-between rounded-md bg-indigo-50 px-3 py-2 text-indigo-700">
								<span>Tool Item</span>
								<strong>{{ formatNumber(analytics.inventory.toolTotal) }}</strong>
							</div>
							<div class="flex items-center justify-between rounded-md bg-amber-50 px-3 py-2 text-amber-700">
								<span>Low Stock</span>
								<strong>{{ formatNumber(analytics.inventory.lowStock) }}</strong>
							</div>
							<div class="flex items-center justify-between rounded-md bg-blue-50 px-3 py-2 text-blue-700">
								<span>Overstock</span>
								<strong>{{ formatNumber(analytics.inventory.overstock) }}</strong>
							</div>
							<div class="flex items-center justify-between rounded-md bg-orange-50 px-3 py-2 text-orange-700">
								<span>Tool Dipinjam</span>
								<strong>{{ formatNumber(analytics.inventory.activeToolLoans) }}</strong>
							</div>
						</div>

						<Link href="/parts/stock" class="mt-4 inline-flex text-xs text-primary hover:underline">
							Buka Modul Stock
						</Link>
					</div>
				</div>

				<div class="grid gap-4 lg:grid-cols-3">
					<div class="rounded-lg border border-sidebar-border/70 p-4">
						<div class="mb-3 flex items-center justify-between">
							<h3 class="text-sm font-semibold">Top Supplier</h3>
							<Link href="/suppliers" class="text-xs text-primary hover:underline">Lihat Modul</Link>
						</div>

						<ul class="space-y-2 text-sm">
							<li
								v-for="supplier in analytics.suppliers.topSuppliers"
								:key="supplier.supplier_name"
								class="flex items-center justify-between rounded-md bg-muted/40 px-3 py-2"
							>
								<span>{{ supplier.supplier_name }}</span>
								<span class="text-muted-foreground">{{ supplier.parts_count }} part</span>
							</li>
							<li
								v-if="analytics.suppliers.topSuppliers.length === 0"
								class="rounded-md bg-muted/40 px-3 py-2 text-muted-foreground"
							>
								Data supplier belum tersedia.
							</li>
						</ul>
					</div>

					<div class="rounded-lg border border-sidebar-border/70 p-4">
						<div class="mb-3 flex items-center justify-between">
							<h3 class="text-sm font-semibold">Top Customer</h3>
							<Link href="/sales/customers" class="text-xs text-primary hover:underline">Lihat Modul</Link>
						</div>

						<ul class="space-y-2 text-sm">
							<li
								v-for="customer in analytics.customerOrders.topCustomers"
								:key="customer.customer_name"
								class="flex items-center justify-between rounded-md bg-muted/40 px-3 py-2"
							>
								<span>{{ customer.customer_name }}</span>
								<span class="text-muted-foreground">{{ customer.orders_count }} order</span>
							</li>
							<li
								v-if="analytics.customerOrders.topCustomers.length === 0"
								class="rounded-md bg-muted/40 px-3 py-2 text-muted-foreground"
							>
								Data customer belum tersedia.
							</li>
						</ul>
					</div>

					<div class="rounded-lg border border-sidebar-border/70 p-4">
						<div class="mb-3 flex items-center justify-between">
							<h3 class="text-sm font-semibold">Top Stock Item</h3>
							<Link href="/parts/stock" class="text-xs text-primary hover:underline">Lihat Modul</Link>
						</div>

						<ul class="space-y-2 text-sm">
							<li
								v-for="stock in analytics.inventory.topStockItems"
								:key="`${stock.part_name}-${stock.warehouse}-${stock.inventory_type ?? 'unknown'}`"
								class="rounded-md bg-muted/40 px-3 py-2"
							>
								<div class="flex items-center justify-between">
									<span class="font-medium">{{ stock.part_name }}</span>
									<span>{{ formatNumber(stock.quantity) }}</span>
								</div>
								<p class="text-xs text-muted-foreground">
									{{ stock.warehouse }} • {{ stock.inventory_type === 'tool' ? 'Tool' : 'Material' }}
								</p>
							</li>
							<li
								v-if="analytics.inventory.topStockItems.length === 0"
								class="rounded-md bg-muted/40 px-3 py-2 text-muted-foreground"
							>
								Data stock belum tersedia.
							</li>
						</ul>
					</div>
				</div>
			</template>
		</div>
	</AppLayout>
</template>
