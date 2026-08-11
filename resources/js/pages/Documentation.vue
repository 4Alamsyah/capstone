<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Documentation', href: '/documentation' },
];

const modules = [
    {
        title: 'Master Data',
        points: [
            'Part: kelola part material dan tools, termasuk kategori, harga, dan safety stock.',
            'Supplier: kelola data supplier untuk pembelian dan relasi harga part.',
            'Work Center: definisi stasiun kerja untuk operation di BOM.',
        ],
    },
    {
        title: 'Inventory & Tool Tracking',
        points: [
            'Stock menampilkan saldo stok per gudang dan riwayat pergerakan.',
            'Material dipakai untuk konsumsi produksi; tools dipinjam dan dikembalikan via tool loan.',
            'Stock movement mencatat transaksi arrival, consume, dan return untuk audit.',
        ],
    },
    {
        title: 'Production (MO/Manufacture Order)',
        points: [
            'MO bisa dibuat manual dari menu Manufacture Order.',
            'PO yang disetujui otomatis dapat membentuk MO jika item PO punya BOM aktif.',
            'Detail MO menampilkan sumber PO bila MO berasal dari approval PO.',
        ],
    },
    {
        title: 'Sales',
        points: [
            'Alur customer order dari register, confirm, hingga delivery.',
            'Invoice dapat dibuat dan payment wajib melalui approval manajemen.',
            'Delivery order dapat di-generate saat status order Delivered.',
        ],
    },
    {
        title: 'Purchase',
        points: [
            'PO dibuat dalam status pending approval.',
            'Approval/reject PO hanya untuk role manajemen sesuai permission.',
            'Setelah approved, arrival report dapat dicatat untuk menambah stok.',
        ],
    },
    {
        title: 'Role & Permission',
        points: [
            'Role Access mengatur menu yang boleh diakses tiap user.',
            'Hak approval PO dan invoice payment diatur berbasis permission.',
            'Role admin memiliki akses penuh ke seluruh menu dan aksi approval.',
        ],
    },
];

const quickFlows = [
    'Buat Part dan BOM terlebih dahulu untuk item yang diproduksi.',
    'Buat PO lalu minta approval manajemen.',
    'Jika approved, sistem akan membuat MO otomatis (jika BOM tersedia).',
    'Lakukan report arrival untuk update stok masuk dari pembelian.',
    'Jalankan report MO untuk konsumsi material dan update status produksi.',
    'Lanjutkan ke sales delivery dan invoice sesuai progres order.',
];

const detailedFlows = [
    {
        title: 'Flow A: Purchase -> Approval -> Auto MO -> Arrival',
        steps: [
            'User Purchasing membuat PO dari menu Purchase dengan item part yang dibutuhkan.',
            'PO masuk status Pending Approval dan menunggu approval manajemen (GM/Director/Admin).',
            'Ketika PO di-approve, sistem otomatis membuat MO (Manufacture Order) untuk item yang memiliki BOM aktif.',
            'Nomor MO otomatis tampil di kolom Project pada List PO sebagai referensi proyek produksi.',
            'Tim warehouse/purchasing input Report Arrival untuk barang datang agar stok bertambah.',
            'Tim produksi lanjut menjalankan Report MO untuk konsumsi material sesuai proses produksi.',
        ],
    },
    {
        title: 'Flow B: Manual MO (Tanpa PO)',
        steps: [
            'Planner/PPIC membuat MO langsung dari menu Manufacture Order (Create MO).',
            'MO ini tidak memiliki referensi PO sehingga Source PO pada detail MO akan kosong.',
            'MO diproses normal: update status, input report, dan konsumsi material dari stok.',
            'Semua aktivitas tetap tercatat di Log MO untuk keperluan traceability.',
        ],
    },
    {
        title: 'Flow C: Sales -> Delivery -> Invoice -> Payment Approval',
        steps: [
            'Sales membuat Customer Order lalu konfirmasi order sesuai kebutuhan pelanggan.',
            'Saat status order mencapai Delivered, invoice bisa dibuat/tersedia untuk proses penagihan.',
            'Tim finance mengajukan payment request pada invoice.',
            'Manajemen melakukan approve/reject payment sesuai kebijakan approval.',
            'Invoice dianggap selesai setelah payment approved dan diproses sebagai paid.',
        ],
    },
];

const flowDiagrams = [
    {
        title: 'Diagram A: Purchase to Auto MO',
        steps: ['Create PO', 'Management Approval', 'Auto Create MO', 'Report Arrival', 'Run MO Report'],
    },
    {
        title: 'Diagram B: Manual MO',
        steps: ['Create Manual MO', 'No Source PO', 'Update Status', 'Consume Material', 'Log Recorded'],
    },
    {
        title: 'Diagram C: Sales to Payment',
        steps: ['Register CO', 'Delivery', 'Generate Invoice', 'Payment Request', 'Management Approval', 'Paid'],
    },
];
</script>

<template>
    <Head title="Documentation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
            <Heading
                title="Application Documentation"
                description="Panduan ringkas modul dan alur utama aplikasi ERP manufaktur ini."
            />

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <h2 class="mb-3 text-sm font-semibold">Quick Start Flow</h2>
                <ol class="list-decimal space-y-2 pl-5 text-sm text-muted-foreground">
                    <li v-for="step in quickFlows" :key="step">{{ step }}</li>
                </ol>
            </div>

            <div class="grid gap-4">
                <section
                    v-for="diagram in flowDiagrams"
                    :key="diagram.title"
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <h2 class="mb-3 text-sm font-semibold">{{ diagram.title }}</h2>
                    <div class="flex flex-col gap-2 md:flex-row md:flex-wrap md:items-center">
                        <template v-for="(step, index) in diagram.steps" :key="`${diagram.title}-${step}`">
                            <div class="rounded-md border border-sidebar-border/70 bg-muted/30 px-3 py-2 text-sm font-medium">
                                {{ step }}
                            </div>
                            <span
                                v-if="index < diagram.steps.length - 1"
                                class="px-1 text-xs text-muted-foreground"
                            >
                                ->
                            </span>
                        </template>
                    </div>
                </section>
            </div>

            <div class="grid gap-4">
                <section
                    v-for="flow in detailedFlows"
                    :key="flow.title"
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <h2 class="mb-3 text-sm font-semibold">{{ flow.title }}</h2>
                    <ol class="list-decimal space-y-2 pl-5 text-sm text-muted-foreground">
                        <li v-for="step in flow.steps" :key="step">{{ step }}</li>
                    </ol>
                </section>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <section
                    v-for="module in modules"
                    :key="module.title"
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <h3 class="mb-2 text-sm font-semibold">{{ module.title }}</h3>
                    <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        <li v-for="point in module.points" :key="point">{{ point }}</li>
                    </ul>
                </section>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4 text-sm text-muted-foreground">
                <p>
                    Catatan: halaman ini adalah dokumentasi internal aplikasi. Jika Anda butuh SOP yang lebih detail per divisi
                    (Purchasing, PPIC, Warehouse, Finance), kontennya bisa diperluas pada halaman ini tanpa mengubah modul inti.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
