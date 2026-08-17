<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('nav.accounting'), href: '/accounting/general' },
    { title: t('nav.general'), href: '/accounting/general' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Accounting - General" />

        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                :title="t('accounting.general.heading_title')"
                :description="t('accounting.general.heading_description')"
            />

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    href="/accounting/manual-journal"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.manual_journal_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.manual_journal_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/chart-of-accounts"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.coa_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.coa_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/fiscal-periods"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.fiscal_period_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.fiscal_period_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/journal-entries"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.journal_entries_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.journal_entries_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/journal-report"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.journal_report_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.journal_report_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/journal-lines"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.journal_lines_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.journal_lines_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/audit-trails"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.audit_trails_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.audit_trails_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/tax-setting"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.tax_setting_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.tax_setting_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/gl-setting"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.gl_setting_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.gl_setting_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/ar-aging"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.ar_aging_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.ar_aging_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/ap-aging"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.ap_aging_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.ap_aging_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/profit-loss"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.profit_loss_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.profit_loss_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/balance-sheet"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.balance_sheet_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.balance_sheet_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/fx-revaluation"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.fx_revaluation_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.fx_revaluation_desc') }}
                    </p>
                </Link>

                <Link
                    href="/accounting/exchange-rates"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-5 transition hover:border-primary/40 hover:bg-muted/50"
                >
                    <h3 class="text-sm font-semibold text-foreground">{{ t('accounting.general.exchange_rates_title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('accounting.general.exchange_rates_desc') }}
                    </p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
