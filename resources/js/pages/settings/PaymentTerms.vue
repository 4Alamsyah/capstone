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
    paymentTerms: string[];
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Payment Terms', href: '/settings/general/payment-terms' },
];

const createForm = useForm({
    term: '',
});

const editForms = props.paymentTerms.map((term) =>
    useForm({
        term,
    }),
);

const addPaymentTerm = () => {
    createForm.post('/settings/general/payment-terms', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
        },
    });
};

const updatePaymentTerm = (index: number) => {
    editForms[index].patch(`/settings/general/payment-terms/${index}`, {
        preserveScroll: true,
    });
};

const deletePaymentTerm = (index: number, term: string) => {
    const confirmed = window.confirm(`Hapus payment term \"${term}\"?`);

    if (!confirmed) {
        return;
    }

    useForm({}).delete(`/settings/general/payment-terms/${index}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Payment Terms" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Payment Terms"
                    description="Kelola daftar payment terms yang akan dipakai di proses Sales."
                />

                <form class="space-y-4 rounded-lg border border-sidebar-border/70 p-4" @submit.prevent="addPaymentTerm">
                    <h3 class="text-sm font-semibold">Tambah Payment Term</h3>

                    <div class="grid gap-2">
                        <Label for="new-term">Term</Label>
                        <Input id="new-term" v-model="createForm.term" placeholder="Contoh: NET 30" maxlength="100" />
                        <InputError :message="createForm.errors.term" />
                    </div>

                    <div class="flex">
                        <Button type="submit" :disabled="createForm.processing">Add Payment Term</Button>
                    </div>
                </form>

                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-4 text-sm font-semibold">Daftar Payment Terms</h3>

                    <div v-if="!paymentTerms.length" class="rounded-md border border-dashed border-sidebar-border/70 px-3 py-6 text-center text-sm text-muted-foreground">
                        Belum ada payment terms.
                    </div>

                    <div v-else class="space-y-3">
                        <form
                            v-for="(term, index) in paymentTerms"
                            :key="`${index}-${term}`"
                            class="grid gap-3 rounded-md border border-sidebar-border/50 p-3 md:grid-cols-[1fr_auto_auto]"
                            @submit.prevent="updatePaymentTerm(index)"
                        >
                            <div class="grid gap-2">
                                <Label :for="`term-${index}`">Term</Label>
                                <Input :id="`term-${index}`" v-model="editForms[index].term" maxlength="100" />
                                <InputError :message="editForms[index].errors.term" />
                            </div>

                            <div class="flex items-end">
                                <Button type="submit" variant="outline" :disabled="editForms[index].processing">Save</Button>
                            </div>

                            <div class="flex items-end">
                                <Button type="button" variant="destructive" @click="deletePaymentTerm(index, term)">Delete</Button>
                            </div>
                        </form>
                    </div>
                </div>

                <div
                    v-if="status"
                    class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700"
                >
                    Perubahan payment terms berhasil disimpan.
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
