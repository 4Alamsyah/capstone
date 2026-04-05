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

type Props = {
    settings: {
        wo_prefix: string;
    };
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'App Settings', href: '/settings/app' },
];

const form = useForm({
    wo_prefix: props.settings.wo_prefix,
});

const submit = () => {
    form.patch('/settings/app');
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="App Settings" />

        <h1 class="sr-only">App Settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Application Settings"
                    description="Configure application-wide preferences"
                />

                <form class="space-y-6" @submit.prevent="submit">
                    <!-- Work Order settings -->
                    <div class="rounded-lg border border-sidebar-border/70 p-4">
                        <h3 class="mb-4 text-sm font-semibold">Work Order</h3>

                        <div class="grid gap-2 max-w-xs">
                            <Label for="wo_prefix">WO Number Prefix</Label>
                            <Input
                                id="wo_prefix"
                                v-model="form.wo_prefix"
                                placeholder="e.g. WO"
                                class="uppercase"
                                maxlength="20"
                            />
                            <p class="text-xs text-muted-foreground">
                                Generated format: <strong>{{ form.wo_prefix || 'WO' }}-YYYYMM-00001</strong>
                            </p>
                            <InputError :message="form.errors.wo_prefix" />
                        </div>
                    </div>

                    <div v-if="status === 'settings-updated'" class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                        Settings saved.
                    </div>

                    <div class="flex">
                        <Button type="submit" :disabled="form.processing">Save Settings</Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
