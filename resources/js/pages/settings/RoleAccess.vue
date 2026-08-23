<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, type Component } from 'vue';
import {
    Calculator,
    ChevronDown,
    Factory,
    LayoutGrid,
    Package,
    PackageSearch,
    ShieldCheck,
    Settings as SettingsIcon,
    ShoppingCart,
    UserPlus,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';

type Role = 'admin' | 'staff' | 'gm' | 'director';

type PermissionLevel = 'prohibited' | 'view' | 'edit' | 'full';

type PermissionMap = Record<string, PermissionLevel | boolean>;

type UserAccessItem = {
    id: number;
    name: string;
    email: string;
    role: Role;
    permissions: PermissionMap;
    is_active: boolean;
};

type ModuleGroup = {
    label: string;
    submodules: Record<string, string>;
};

type Props = {
    users: UserAccessItem[];
    roles: Role[];
    moduleGroups: Record<string, ModuleGroup>;
    permissionLevels: Record<PermissionLevel, string>;
    approvePermissionLabels: Record<string, string>;
    permissionTemplates: Record<Role, PermissionMap>;
    canEdit: boolean;
    canCreateUser: boolean;
    status?: string;
};

const props = defineProps<Props>();

const page = usePage<{ auth?: { user?: { id: number } | null } }>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Role Access', href: '/settings/role-access' },
];

/**
 * The full level names ("Read, Edit, Delete & Create") are far too long to sit
 * inside a control on every row, so the matrix uses these short labels and
 * explains them once in the legend above.
 */
const LEVEL_META: Record<PermissionLevel, { short: string; active: string; dot: string }> = {
    prohibited: {
        short: 'None',
        active: 'bg-rose-500 text-white shadow-sm',
        dot: 'bg-rose-500',
    },
    view: {
        short: 'View',
        active: 'bg-sky-500 text-white shadow-sm',
        dot: 'bg-sky-500',
    },
    edit: {
        short: 'Edit',
        active: 'bg-amber-500 text-white shadow-sm',
        dot: 'bg-amber-500',
    },
    full: {
        short: 'Full',
        active: 'bg-emerald-600 text-white shadow-sm',
        dot: 'bg-emerald-600',
    },
};

const MODULE_ICONS: Record<string, Component> = {
    dashboard: LayoutGrid,
    parts: PackageSearch,
    manufacturing: Factory,
    sales: ShoppingCart,
    purchase: Package,
    accounting: Calculator,
    settings: SettingsIcon,
};

const levelOrder = computed(() => Object.keys(props.permissionLevels) as PermissionLevel[]);

const submoduleKeys = computed(() =>
    Object.values(props.moduleGroups).flatMap((module) => Object.keys(module.submodules)),
);

const approveKeys = computed(() => Object.keys(props.approvePermissionLabels));

const selectedUserId = ref<number | null>(props.users[0]?.id ?? null);

const selectedUser = computed(() => props.users.find((user) => user.id === selectedUserId.value) ?? null);

const isSelf = computed(() => selectedUser.value !== null && selectedUser.value.id === page.props.auth?.user?.id);

const showCreateUser = ref(false);

const form = useForm({
    role: (selectedUser.value?.role ?? 'staff') as Role,
    permissions: { ...(selectedUser.value?.permissions ?? {}) } as PermissionMap,
});

const statusForm = useForm({
    is_active: selectedUser.value?.is_active ?? true,
});

const createUserForm = useForm({
    name: '',
    email: '',
    role: 'staff' as Role,
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

    statusForm.is_active = user.is_active;
    statusForm.clearErrors();
});

const roleLabel = (role: Role): string => {
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

const levelOf = (key: string): PermissionLevel => {
    const value = form.permissions[key];

    return typeof value === 'string' ? value : 'prohibited';
};

const setLevel = (key: string, level: PermissionLevel) => {
    if (!props.canEdit) {
        return;
    }

    form.permissions[key] = level;
};

const setModuleLevel = (moduleKey: string, level: PermissionLevel) => {
    if (!props.canEdit) {
        return;
    }

    Object.keys(props.moduleGroups[moduleKey]?.submodules ?? {}).forEach((key) => {
        form.permissions[key] = level;
    });
};

/** Counts per level across every sub-module, shown in the save bar. */
const levelCounts = computed(() => {
    const counts: Record<PermissionLevel, number> = { prohibited: 0, view: 0, edit: 0, full: 0 };

    submoduleKeys.value.forEach((key) => {
        counts[levelOf(key)] += 1;
    });

    return counts;
});

const isDirty = computed(() => {
    const user = selectedUser.value;

    if (!user) {
        return false;
    }

    if (form.role !== user.role) {
        return true;
    }

    const levelChanged = submoduleKeys.value.some((key) => form.permissions[key] !== user.permissions[key]);
    const approveChanged = approveKeys.value.some(
        (key) => Boolean(form.permissions[key]) !== Boolean(user.permissions[key]),
    );

    return levelChanged || approveChanged;
});

const applyRoleTemplate = () => {
    form.permissions = { ...(props.permissionTemplates[form.role] ?? {}) };
};

const resetChanges = () => {
    if (!selectedUser.value) {
        return;
    }

    form.role = selectedUser.value.role;
    form.permissions = { ...selectedUser.value.permissions };
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
            showCreateUser.value = false;
        },
    });
};

const toggleStatus = () => {
    if (!selectedUser.value || isSelf.value) {
        return;
    }

    statusForm.is_active = !selectedUser.value.is_active;
    statusForm.patch(`/settings/role-access/${selectedUser.value.id}/status`, {
        preserveScroll: true,
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
                    description="Atur level akses per sub-modul, hak approval, dan status aktif user."
                />

                <div
                    v-if="!canEdit"
                    class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
                >
                    <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Anda hanya memiliki akses <strong>Read Only</strong> ke halaman ini. Perubahan tidak dapat disimpan.</span>
                </div>

                <!-- Create user (collapsed by default so the matrix stays the focus) -->
                <div v-if="canCreateUser" class="overflow-hidden rounded-xl border border-sidebar-border/70">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-muted/50"
                        @click="showCreateUser = !showCreateUser"
                    >
                        <span class="flex items-center gap-2 text-sm font-semibold">
                            <UserPlus class="h-4 w-4 text-muted-foreground" />
                            Tambah User
                        </span>
                        <span class="flex items-center gap-2">
                            <span v-if="status === 'user-created'" class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                User baru berhasil dibuat.
                            </span>
                            <ChevronDown
                                class="h-4 w-4 text-muted-foreground transition-transform"
                                :class="showCreateUser && 'rotate-180'"
                            />
                        </span>
                    </button>

                    <form
                        v-show="showCreateUser"
                        class="grid gap-4 border-t border-sidebar-border/70 p-4 md:grid-cols-2"
                        @submit.prevent="submitCreateUser"
                    >
                        <div class="grid min-w-0 gap-2">
                            <Label for="new-user-name">Name</Label>
                            <Input id="new-user-name" v-model="createUserForm.name" placeholder="Nama user" />
                            <InputError :message="createUserForm.errors.name" />
                        </div>

                        <div class="grid min-w-0 gap-2">
                            <Label for="new-user-email">Email</Label>
                            <Input id="new-user-email" v-model="createUserForm.email" type="email" placeholder="email@company.com" />
                            <InputError :message="createUserForm.errors.email" />
                        </div>

                        <div class="grid min-w-0 gap-2">
                            <Label for="new-user-role">Role</Label>
                            <select
                                id="new-user-role"
                                v-model="createUserForm.role"
                                class="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            >
                                <option v-for="role in roles" :key="role" :value="role">
                                    {{ roleLabel(role) }}
                                </option>
                            </select>
                            <InputError :message="createUserForm.errors.role" />
                        </div>

                        <div class="grid min-w-0 gap-2">
                            <Label for="new-user-password">Password</Label>
                            <Input id="new-user-password" v-model="createUserForm.password" type="password" placeholder="Password baru" />
                            <InputError :message="createUserForm.errors.password" />
                        </div>

                        <div class="grid min-w-0 gap-2">
                            <Label for="new-user-password-confirmation">Confirm Password</Label>
                            <Input
                                id="new-user-password-confirmation"
                                v-model="createUserForm.password_confirmation"
                                type="password"
                                placeholder="Ulangi password"
                            />
                        </div>

                        <div class="flex items-end md:col-span-2">
                            <Button type="submit" :disabled="createUserForm.processing">
                                Create User
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- User selector -->
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid min-w-0 gap-2">
                            <Label for="user-select">Pilih User</Label>
                            <select
                                id="user-select"
                                v-model.number="selectedUserId"
                                class="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            >
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} — {{ roleLabel(user.role) }}{{ user.is_active ? '' : ' (Non-aktif)' }}
                                </option>
                            </select>
                            <p class="truncate text-xs text-muted-foreground">{{ selectedUser?.email }}</p>
                        </div>

                        <div class="grid min-w-0 gap-2">
                            <Label for="role">Role</Label>
                            <select
                                id="role"
                                v-model="form.role"
                                :disabled="!canEdit"
                                class="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <option v-for="role in roles" :key="role" :value="role">
                                    {{ roleLabel(role) }}
                                </option>
                            </select>
                            <button
                                v-if="canEdit"
                                type="button"
                                class="w-fit text-xs font-medium text-primary underline-offset-4 hover:underline"
                                @click="applyRoleTemplate"
                            >
                                Apply {{ roleLabel(form.role) }} template
                            </button>
                        </div>

                        <div class="grid min-w-0 gap-2">
                            <Label>Status Akun</Label>
                            <div class="flex h-10 items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="selectedUser?.is_active
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400'"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="selectedUser?.is_active ? 'bg-emerald-500' : 'bg-rose-500'"
                                    />
                                    {{ selectedUser?.is_active ? 'Aktif' : 'Non-aktif' }}
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!canEdit || !selectedUser || isSelf || statusForm.processing"
                                    :title="isSelf ? 'Anda tidak dapat menonaktifkan akun sendiri' : undefined"
                                    @click="toggleStatus"
                                >
                                    {{ selectedUser?.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </Button>
                            </div>
                            <p v-if="status === 'user-status-updated'" class="text-xs text-emerald-600 dark:text-emerald-400">
                                Status user berhasil diperbarui.
                            </p>
                            <p v-else-if="isSelf" class="text-xs text-muted-foreground">
                                Ini akun Anda sendiri.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Permission matrix -->
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold">Akses per Sub-modul</h3>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                            <span
                                v-for="level in levelOrder"
                                :key="level"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span class="h-2 w-2 rounded-full" :class="LEVEL_META[level].dot" />
                                <strong class="font-medium text-foreground">{{ LEVEL_META[level].short }}</strong>
                                {{ permissionLevels[level] }}
                            </span>
                        </div>
                    </div>

                    <div class="grid items-start gap-4 lg:grid-cols-2">
                        <div
                            v-for="(module, moduleKey) in moduleGroups"
                            :key="moduleKey"
                            class="overflow-hidden rounded-xl border border-sidebar-border/70"
                        >
                            <div class="flex items-center justify-between gap-3 border-b border-sidebar-border/70 bg-muted/40 px-4 py-2.5">
                                <span class="flex items-center gap-2 text-sm font-semibold">
                                    <component :is="MODULE_ICONS[moduleKey] ?? LayoutGrid" class="h-4 w-4 text-muted-foreground" />
                                    {{ module.label }}
                                </span>
                                <div v-if="canEdit" class="flex items-center gap-1">
                                    <span class="mr-1 hidden text-[11px] text-muted-foreground sm:inline">Set semua:</span>
                                    <button
                                        v-for="level in levelOrder"
                                        :key="level"
                                        type="button"
                                        class="rounded px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground transition hover:bg-background hover:text-foreground"
                                        :title="`Set semua sub-modul ${module.label} ke ${permissionLevels[level]}`"
                                        @click="setModuleLevel(moduleKey, level)"
                                    >
                                        {{ LEVEL_META[level].short }}
                                    </button>
                                </div>
                            </div>

                            <div class="divide-y divide-sidebar-border/50">
                                <div
                                    v-for="(subLabel, subKey) in module.submodules"
                                    :key="subKey"
                                    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2.5"
                                >
                                    <span class="min-w-0 flex-1 text-sm leading-snug">{{ subLabel }}</span>

                                    <div
                                        class="inline-flex shrink-0 rounded-lg bg-muted p-0.5"
                                        role="group"
                                        :aria-label="`Level akses ${subLabel}`"
                                    >
                                        <button
                                            v-for="level in levelOrder"
                                            :key="level"
                                            type="button"
                                            :disabled="!canEdit"
                                            :aria-pressed="levelOf(subKey) === level"
                                            :title="permissionLevels[level]"
                                            class="rounded-md px-2.5 py-1 text-xs font-medium transition disabled:cursor-not-allowed"
                                            :class="levelOf(subKey) === level
                                                ? LEVEL_META[level].active
                                                : 'text-muted-foreground hover:text-foreground disabled:opacity-50 disabled:hover:text-muted-foreground'"
                                            @click="setLevel(subKey, level)"
                                        >
                                            {{ LEVEL_META[level].short }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Approval permissions -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold">Approval Permissions</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="(label, key) in approvePermissionLabels"
                            :key="key"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2.5 text-sm transition hover:bg-muted/40 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60 has-[:checked]:border-emerald-500/50 has-[:checked]:bg-emerald-50 dark:has-[:checked]:bg-emerald-500/10"
                        >
                            <input
                                v-model="form.permissions[key]"
                                type="checkbox"
                                :disabled="!canEdit"
                                class="h-4 w-4 shrink-0 rounded border-input accent-emerald-600"
                            />
                            <span class="min-w-0 leading-snug">{{ label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Sticky save bar -->
                <div
                    v-if="canEdit"
                    class="sticky bottom-0 -mx-1 flex flex-wrap items-center justify-between gap-3 border-t border-sidebar-border/70 bg-background/95 px-1 py-3 backdrop-blur"
                >
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        <span
                            v-for="level in levelOrder"
                            :key="level"
                            class="flex items-center gap-1.5"
                        >
                            <span class="h-2 w-2 rounded-full" :class="LEVEL_META[level].dot" />
                            {{ LEVEL_META[level].short }}: <strong class="font-semibold text-foreground">{{ levelCounts[level] }}</strong>
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <span v-if="status === 'role-access-updated' && !isDirty" class="text-sm text-emerald-600 dark:text-emerald-400">
                            Tersimpan.
                        </span>
                        <span v-else-if="isDirty" class="text-sm text-amber-600 dark:text-amber-400">
                            Ada perubahan belum disimpan
                        </span>
                        <Button
                            v-if="isDirty"
                            type="button"
                            variant="ghost"
                            size="sm"
                            :disabled="form.processing"
                            @click="resetChanges"
                        >
                            Batal
                        </Button>
                        <Button type="button" :disabled="form.processing || !selectedUser || !isDirty" @click="submit">
                            Save Role &amp; Access
                        </Button>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
