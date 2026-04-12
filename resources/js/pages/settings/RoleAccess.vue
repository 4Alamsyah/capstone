<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';

type PermissionMap = Record<string, boolean>;

type UserAccessItem = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'staff' | 'gm' | 'director';
    permissions: PermissionMap;
};

type Props = {
    users: UserAccessItem[];
    roles: Array<'admin' | 'staff' | 'gm' | 'director'>;
    permissionLabels: Record<string, string>;
    permissionTemplates: Record<'admin' | 'staff' | 'gm' | 'director', PermissionMap>;
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Role Access', href: '/settings/role-access' },
];

const selectedUserId = ref<number | null>(props.users[0]?.id ?? null);

const selectedUser = computed(() => props.users.find((user) => user.id === selectedUserId.value) ?? null);

const form = useForm({
    role: (selectedUser.value?.role ?? 'staff') as 'admin' | 'staff' | 'gm' | 'director',
    permissions: { ...(selectedUser.value?.permissions ?? {}) } as PermissionMap,
});

const createUserForm = useForm({
    name: '',
    email: '',
    role: 'staff' as 'admin' | 'staff' | 'gm' | 'director',
    password: '',
    password_confirmation: '',
});

watch(selectedUser, (user) => {
    if (!user) {
        return;
    }

    form.role = user.role;
    form.permissions = { ...user.permissions };
    form.clearErrors();
});

const roleLabel = (role: 'admin' | 'staff' | 'gm' | 'director'): string => {
    if (role === 'admin') {
        return 'Admin';
    }

    if (role === 'gm') {
        return 'GM';
    }

    if (role === 'director') {
        return 'Director';
    }

    return 'Staff';
};

const applyRoleTemplate = () => {
    form.permissions = { ...(props.permissionTemplates[form.role] ?? {}) };
};

const submit = () => {
    if (!selectedUser.value) {
        return;
    }

    form.patch(`/settings/role-access/${selectedUser.value.id}`, {
        preserveScroll: true,
    });
};

const submitCreateUser = () => {
    createUserForm.post('/settings/role-access', {
        preserveScroll: true,
        onSuccess: () => {
            createUserForm.reset('name', 'email', 'password', 'password_confirmation');
            createUserForm.role = 'staff';
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Role Access" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Role & Access Control"
                    description="Atur menu yang bisa dilihat user dan hak approval invoice/PO."
                />

                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-3 text-sm font-semibold">Tambah User</h3>
                    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitCreateUser">
                        <div class="grid gap-2">
                            <Label for="new-user-name">Name</Label>
                            <Input id="new-user-name" v-model="createUserForm.name" placeholder="Nama user" />
                            <InputError :message="createUserForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="new-user-email">Email</Label>
                            <Input id="new-user-email" v-model="createUserForm.email" type="email" placeholder="email@company.com" />
                            <InputError :message="createUserForm.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="new-user-role">Role</Label>
                            <select
                                id="new-user-role"
                                v-model="createUserForm.role"
                                class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            >
                                <option v-for="role in roles" :key="role" :value="role">
                                    {{ roleLabel(role) }}
                                </option>
                            </select>
                            <InputError :message="createUserForm.errors.role" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="new-user-password">Password</Label>
                            <Input id="new-user-password" v-model="createUserForm.password" type="password" placeholder="Password baru" />
                            <InputError :message="createUserForm.errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="new-user-password-confirmation">Confirm Password</Label>
                            <Input
                                id="new-user-password-confirmation"
                                v-model="createUserForm.password_confirmation"
                                type="password"
                                placeholder="Ulangi password"
                            />
                        </div>

                        <div class="md:col-span-2 flex items-center gap-3">
                            <Button type="submit" :disabled="createUserForm.processing">
                                Create User
                            </Button>
                            <span v-if="status === 'user-created'" class="text-sm text-green-700">
                                User baru berhasil dibuat.
                            </span>
                        </div>
                    </form>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="user-select">Pilih User</Label>
                            <select
                                id="user-select"
                                v-model.number="selectedUserId"
                                class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            >
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="role">Role</Label>
                            <select
                                id="role"
                                v-model="form.role"
                                class="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            >
                                <option v-for="role in roles" :key="role" :value="role">
                                    {{ roleLabel(role) }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3 flex">
                        <Button type="button" variant="outline" @click="applyRoleTemplate">
                            Apply {{ roleLabel(form.role) }} Template
                        </Button>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 p-4">
                    <h3 class="mb-3 text-sm font-semibold">Menu Visibility & Approval Permissions</h3>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            v-for="(label, key) in permissionLabels"
                            :key="key"
                            class="inline-flex items-center gap-2 rounded-md border border-sidebar-border/50 px-3 py-2 text-sm"
                        >
                            <input
                                v-model="form.permissions[key]"
                                type="checkbox"
                                class="h-4 w-4 rounded border-input"
                            />
                            <span>{{ label }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button type="button" :disabled="form.processing || !selectedUser" @click="submit">
                        Save Role & Access
                    </Button>
                    <span
                        v-if="status === 'role-access-updated'"
                        class="text-sm text-green-700"
                    >
                        Role dan akses berhasil diperbarui.
                    </span>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
