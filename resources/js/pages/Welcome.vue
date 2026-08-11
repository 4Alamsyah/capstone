<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link
            href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        />
    </Head>
    <div
        class="relative min-h-screen overflow-hidden bg-[#08111f] text-white"
    >
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.28),_transparent_30%),radial-gradient(circle_at_top_right,_rgba(16,185,129,0.18),_transparent_24%),linear-gradient(180deg,_#08111f_0%,_#0b1728_55%,_#08111f_100%)]" />
        <div class="absolute -left-20 top-24 h-72 w-72 rounded-full bg-cyan-400/15 blur-3xl" />
        <div class="absolute -right-24 top-32 h-80 w-80 rounded-full bg-emerald-400/10 blur-3xl" />
        <div class="relative mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-6 lg:px-8">
            <header class="flex items-center justify-between gap-4 py-2 text-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 shadow-[0_0_0_1px_rgba(255,255,255,0.08)] backdrop-blur">
                        <span class="text-base font-bold tracking-[0.2em] text-cyan-300">ERP</span>
                    </div>
                    <div>
                        <p class="font-semibold tracking-[0.22em] text-white/90">CAPSTONE ERP</p>
                        <p class="text-white/55">Manufacturing, inventory, finance</p>
                    </div>
                </div>

                <nav class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-full border border-white/15 bg-white/10 px-5 py-2 font-medium text-white/90 backdrop-blur transition hover:border-cyan-300/40 hover:bg-cyan-300/10"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-full border border-white/10 px-5 py-2 font-medium text-white/80 transition hover:border-white/20 hover:bg-white/5 hover:text-white"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-full bg-cyan-400 px-5 py-2 font-semibold text-slate-950 transition hover:bg-cyan-300"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[1.1fr_0.9fr] lg:gap-14">
                <section class="max-w-2xl">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/10 px-4 py-2 text-sm text-cyan-100 backdrop-blur">
                        <span class="h-2 w-2 rounded-full bg-cyan-300" />
                        Sistem ERP terpadu untuk operasional yang lebih cepat dan rapi
                    </div>

                    <h1 class="max-w-2xl text-5xl font-bold leading-[1.05] tracking-[-0.04em] text-white sm:text-6xl" style="font-family: 'Space Grotesk', sans-serif;">
                        Satukan produksi, stok, pembelian, dan keuangan dalam satu dashboard.
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-300">
                        ERP ini membantu tim melihat status manufacture order, persediaan, invoice, dan alur procurement secara real-time supaya keputusan lebih cepat dan akurat.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-4">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 font-semibold text-slate-950 transition hover:bg-cyan-100"
                        >
                            Buka Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 font-semibold text-slate-950 transition hover:bg-cyan-100"
                            >
                                Masuk ke Sistem
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-6 py-3 font-semibold text-white transition hover:border-cyan-300/40 hover:bg-cyan-300/10"
                            >
                                Daftar Akun
                            </Link>
                        </template>
                    </div>

                    <div class="mt-10 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-3xl border border-white/10 bg-white/6 p-5 backdrop-blur">
                            <p class="text-3xl font-bold text-white">24/7</p>
                            <p class="mt-2 text-sm text-slate-300">Akses data operasional kapan saja</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/6 p-5 backdrop-blur">
                            <p class="text-3xl font-bold text-white">1x</p>
                            <p class="mt-2 text-sm text-slate-300">Sumber data terpusat untuk semua divisi</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/6 p-5 backdrop-blur">
                            <p class="text-3xl font-bold text-white">+8</p>
                            <p class="mt-2 text-sm text-slate-300">Modul inti untuk bisnis manufaktur</p>
                        </div>
                    </div>
                </section>

                <section class="relative">
                    <div class="absolute inset-0 rounded-[2rem] bg-cyan-400/10 blur-2xl" />
                    <div class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-[#0d1b31]/95 p-5 shadow-[0_30px_120px_rgba(0,0,0,0.45)] backdrop-blur-xl">
                        <div class="flex items-center justify-between border-b border-white/10 pb-4">
                            <div>
                                <p class="text-sm font-medium text-cyan-200">Operational Command Center</p>
                                <p class="text-xs text-slate-400">Live overview of ERP activity</p>
                            </div>
                            <div class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                                All systems nominal
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                    <p class="text-sm text-slate-400">Manufacture Orders</p>
                                    <p class="mt-2 text-4xl font-bold text-white">128</p>
                                    <p class="mt-2 text-sm text-emerald-300">+14% vs last week</p>
                                </div>
                                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                    <p class="text-sm text-slate-400">Inventory Health</p>
                                    <p class="mt-2 text-4xl font-bold text-white">92%</p>
                                    <p class="mt-2 text-sm text-cyan-300">Reorder alerts under control</p>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-400/15 via-white/5 to-emerald-400/10 p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-white">ERP Modules</p>
                                        <p class="text-xs text-slate-400">Connected processes in one place</p>
                                    </div>
                                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs text-white/70">Sync realtime</span>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4 text-sm text-slate-200">Production planning</div>
                                    <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4 text-sm text-slate-200">Inventory & warehouse</div>
                                    <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4 text-sm text-slate-200">Purchasing & suppliers</div>
                                    <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4 text-sm text-slate-200">Finance & invoicing</div>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">MRP</p>
                                    <p class="mt-2 text-sm text-white/85">Material planning dan kebutuhan produksi</p>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">CRM</p>
                                    <p class="mt-2 text-sm text-white/85">Data pelanggan, order, dan invoice</p>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">SCM</p>
                                    <p class="mt-2 text-sm text-white/85">Rantai pasok dari supplier sampai gudang</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <section class="grid gap-4 border-t border-white/10 py-8 text-sm text-slate-300 sm:grid-cols-3">
                <div>
                    <p class="font-semibold text-white">Real-time visibility</p>
                    <p class="mt-1">Pantau status operasional tanpa pindah aplikasi.</p>
                </div>
                <div>
                    <p class="font-semibold text-white">Workflow terintegrasi</p>
                    <p class="mt-1">Alur approval, order, dan produksi saling terhubung.</p>
                </div>
                <div>
                    <p class="font-semibold text-white">Siap berkembang</p>
                    <p class="mt-1">Dirancang untuk menambah modul saat bisnis bertumbuh.</p>
                </div>
            </section>
        </div>
    </div>
</template>
