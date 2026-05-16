<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';

type WoComponent = {
    type: 'prefix' | 'year' | 'month' | 'sequential';
    format: string;
};

type WoFormat = {
    prefix: string;
    separator: string;
    components: WoComponent[];
};

type Props = {
    settings: {
        wo_format: WoFormat;
    };
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'App Settings', href: '/settings/app' },
];

const form = useForm({
    wo_format: props.settings.wo_format,
});

const yearFormats = ['YYYY', 'YY'];
const monthFormats = ['MM', 'M'];
const separators = ['-', '_', '', '.', '/'];

const componentLabels: Record<string, string> = {
    prefix: 'Prefix',
    year: 'Year',
    month: 'Month',
    sequential: 'Sequential Number',
};

const preview = computed(() => generatePreview());

const getComponentFormatOptions = (type: string): string[] => {
    switch (type) {
        case 'year':
            return yearFormats;
        case 'month':
            return monthFormats;
        case 'sequential':
            return Array.from({ length: 8 }, (_, i) => String(i + 1));
        default:
            return [];
    }
};

const moveComponent = (index: number, direction: 'up' | 'down') => {
    const components = form.wo_format.components;
    if (direction === 'up' && index > 0) {
        [components[index], components[index - 1]] = [components[index - 1], components[index]];
    } else if (direction === 'down' && index < components.length - 1) {
        [components[index], components[index + 1]] = [components[index + 1], components[index]];
    }
};

const removeComponent = (index: number) => {
    form.wo_format.components.splice(index, 1);
};

const availableComponentTypes: Array<{ type: WoComponent['type']; label: string }> = [
    { type: 'prefix', label: 'Prefix' },
    { type: 'year', label: 'Year' },
    { type: 'month', label: 'Month' },
    { type: 'sequential', label: 'Sequential' },
];

const getAvailableComponents = () => {
    const usedTypes = form.wo_format.components.map(c => c.type);
    return availableComponentTypes.filter(c => !usedTypes.includes(c.type));
};

const addComponent = (type: WoComponent['type']) => {
    const defaults: Record<WoComponent['type'], WoComponent> = {
        prefix: { type: 'prefix', format: 'raw' },
        year: { type: 'year', format: 'YYYY' },
        month: { type: 'month', format: 'MM' },
        sequential: { type: 'sequential', format: '5' },
    };
    form.wo_format.components.push(defaults[type]);
};

const generatePreview = (): string => {
    const parts: string[] = [];
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');

    for (const component of form.wo_format.components) {
        let value = '';

        if (component.type === 'prefix') {
            value = form.wo_format.prefix;
        } else if (component.type === 'year') {
            value = component.format === 'YY' ? String(year).slice(-2) : String(year);
        } else if (component.type === 'month') {
            value = component.format === 'M' ? String(now.getMonth() + 1) : month;
        } else if (component.type === 'sequential') {
            const digits = parseInt(component.format) || 5;
            value = String(1).padStart(digits, '0');
        }

        if (value) {
            parts.push(value);
        }
    }

    return parts.length > 0 ? parts.join(form.wo_format.separator) : 'WO-202605-00001';
};

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
                    <!-- Work Order Format settings -->
                    <div class="rounded-lg border border-sidebar-border/70 p-4">
                        <h3 class="mb-4 text-sm font-semibold">Work Order Format</h3>

                        <!-- Prefix Input -->
                        <div class="mb-6 grid gap-2 max-w-xs">
                            <Label for="wo_prefix">WO Number Prefix</Label>
                            <Input
                                id="wo_prefix"
                                v-model="form.wo_format.prefix"
                                placeholder="e.g. WO"
                                class="uppercase"
                                maxlength="20"
                            />
                            <InputError :message="form.errors['wo_format.prefix']" />
                        </div>

                        <!-- Format Components -->
                        <div class="mb-6">
                            <div class="mb-3 flex items-center justify-between">
                                <Label class="block">Format Components (Order matters)</Label>
                                <div v-if="getAvailableComponents().length > 0" class="flex gap-1">
                                    <button
                                        v-for="comp in getAvailableComponents()"
                                        :key="comp.type"
                                        type="button"
                                        @click="addComponent(comp.type)"
                                        class="rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100"
                                    >
                                        + {{ comp.label }}
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(component, index) in form.wo_format.components"
                                    :key="index"
                                    class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 p-3"
                                >
                                    <div class="flex-1">
                                        <p class="text-sm font-medium">{{ componentLabels[component.type] }}</p>
                                        <p class="text-xs text-gray-500">
                                            Format: <span class="font-mono">{{ component.format }}</span>
                                        </p>
                                    </div>

                                    <!-- Format selector -->
                                    <select
                                        v-model="component.format"
                                        class="rounded-md border border-gray-300 px-2 py-1 text-sm"
                                    >
                                        <option v-for="fmt in getComponentFormatOptions(component.type)" :key="fmt" :value="fmt">
                                            {{ fmt }}
                                        </option>
                                    </select>

                                    <!-- Move buttons -->
                                    <div class="flex gap-1">
                                        <button
                                            type="button"
                                            :disabled="index === 0"
                                            @click="moveComponent(index, 'up')"
                                            class="rounded p-1 hover:bg-gray-200 disabled:opacity-50"
                                        >
                                            <ArrowUp class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="index === form.wo_format.components.length - 1"
                                            @click="moveComponent(index, 'down')"
                                            class="rounded p-1 hover:bg-gray-200 disabled:opacity-50"
                                        >
                                            <ArrowDown class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <!-- Remove button -->
                                    <button
                                        type="button"
                                        :disabled="form.wo_format.components.length === 1"
                                        @click="removeComponent(index)"
                                        class="rounded p-1 text-red-500 hover:bg-red-50 disabled:opacity-50"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <InputError :message="form.errors['wo_format.components']" />
                        </div>

                        <!-- Separator -->
                        <div class="mb-6 grid gap-2 max-w-xs">
                            <Label for="separator">Separator</Label>
                            <select v-model="form.wo_format.separator" id="separator" class="rounded-md border border-gray-300 px-3 py-2">
                                <option v-for="sep in separators" :key="sep" :value="sep">
                                    {{ sep === '' ? '(none)' : sep }}
                                </option>
                            </select>
                            <InputError :message="form.errors['wo_format.separator']" />
                        </div>

                        <!-- Preview -->
                        <div class="mb-4 rounded-md bg-blue-50 p-3">
                            <p class="text-xs text-blue-600">Preview:</p>
                            <p class="font-mono text-lg font-semibold text-blue-900">{{ preview }}</p>
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
