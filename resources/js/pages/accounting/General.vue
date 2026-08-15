<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Accounting', href: '/accounting/general' },
    { title: 'General', href: '/accounting/general' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Accounting - General" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                title="Accounting General"
                description="Pusat menu accounting untuk jurnal, akun, periode fiskal, dan audit."
            />

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    href="/accounting/manual-journal"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">Manual Journal</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Input transaksi jurnal umum dan validasi debit-kredit.
                    </p>
                </Link>

                <Link
                    href="/accounting/chart-of-accounts"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">Chart of Accounts</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Kelola daftar akun dan struktur kode akun perusahaan.
                    </p>
                </Link>

                <Link
                    href="/accounting/fiscal-periods"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">Fiscal Period</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Buka dan tutup periode akuntansi secara terkontrol.
                    </p>
                </Link>

                <Link
                    href="/accounting/journal-entries"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">Journal Entries</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Daftar header jurnal untuk penelusuran transaksi.
                    </p>
                </Link>

                <Link
                    href="/accounting/journal-report"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">Report Jurnal</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Laporan jurnal umum kronologis dengan filter tanggal, periode, status, dan akun.
                    </p>
                </Link>

                <Link
                    href="/accounting/journal-lines"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">Journal Lines</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Detail baris debit dan kredit dari setiap jurnal.
                    </p>
                </Link>

                <Link
                    href="/accounting/audit-trails"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">Audit Trails</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Riwayat aktivitas untuk memantau perubahan data accounting.
                    </p>
                </Link>

                <Link
                    href="/accounting/tax-setting"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">Tax Setting</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Atur persentase pajak yang otomatis dipakai di Sales Invoice.
                    </p>
                </Link>

                <Link
                    href="/accounting/gl-setting"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">GL Setting</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Pemetaan Chart of Account untuk auto-posting jurnal AR.
                    </p>
                </Link>

                <Link
                    href="/accounting/ar-aging"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">AR Aging</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Laporan umur piutang customer yang belum lunas.
                    </p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
