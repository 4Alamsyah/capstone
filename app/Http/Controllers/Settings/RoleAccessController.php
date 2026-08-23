<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RoleAccessController extends Controller
{
    /**
     * Show role and access configuration page.
     */
    public function edit(Request $request): Response
    {
        $this->authorizeManagementAccess($request, User::LEVEL_VIEW);

        $authUser = $request->user();

        return Inertia::render('settings/RoleAccess', [
            'canEdit' => $authUser instanceof User && $authUser->hasAccess(User::PERMISSION_MODULE_SETTINGS_ROLE_ACCESS, User::LEVEL_EDIT),
            'canCreateUser' => $authUser instanceof User && $authUser->hasAccess(User::PERMISSION_MODULE_SETTINGS_ROLE_ACCESS, User::LEVEL_FULL),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'permissions', 'is_active'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'permissions' => $user->resolvedPermissions(),
                    'is_active' => (bool) $user->is_active,
                ])
                ->values(),
            'roles' => [
                User::ROLE_ADMIN,
                User::ROLE_STAFF,
                User::ROLE_GM,
                User::ROLE_DIRECTOR,
            ],
            'moduleGroups' => User::MODULES,
            'permissionLevels' => User::permissionLevels(),
            'approvePermissionLabels' => User::approvePermissionLabels(),
            'permissionTemplates' => [
                User::ROLE_ADMIN => User::permissionsTemplateForRole(User::ROLE_ADMIN),
                User::ROLE_STAFF => User::permissionsTemplateForRole(User::ROLE_STAFF),
                User::ROLE_GM => User::permissionsTemplateForRole(User::ROLE_GM),
                User::ROLE_DIRECTOR => User::permissionsTemplateForRole(User::ROLE_DIRECTOR),
            ],
            'status' => session('status'),
        ]);
    }

    /**
     * Update role and permissions for a user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManagementAccess($request, User::LEVEL_EDIT);

        $validLevels = array_keys(User::permissionLevels());

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,staff,gm,director'],
            'permissions' => ['required', 'array'],
        ]);

        $normalizedPermissions = User::permissionsTemplateForRole((string) $validated['role']);

        foreach (User::submodulePermissionKeys() as $key) {
            $value = $validated['permissions'][$key] ?? null;
            $normalizedPermissions[$key] = in_array($value, $validLevels, true) ? $value : $normalizedPermissions[$key];
        }

        foreach (User::approvePermissionKeys() as $key) {
            $normalizedPermissions[$key] = (bool) ($validated['permissions'][$key] ?? false);
        }

        $user->update([
            'role' => $validated['role'],
            'permissions' => $normalizedPermissions,
        ]);

        return back()->with('status', 'role-access-updated');
    }

    /**
     * Create a new user from the role access page.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagementAccess($request, User::LEVEL_FULL);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:admin,staff,gm,director'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'permissions' => User::permissionsTemplateForRole((string) $validated['role']),
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return back()->with('status', 'user-created')->with('created_user_id', (string) $user->id);
    }

    /**
     * Toggle a user's active/non-active status.
     */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManagementAccess($request, User::LEVEL_EDIT);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->id === $request->user()?->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $user->update(['is_active' => $validated['is_active']]);

        return back()->with('status', 'user-status-updated');
    }

    /**
     * Restrict access to management roles with at least $minLevel access to
     * the Role Access sub-module itself.
     */
    private function authorizeManagementAccess(Request $request, string $minLevel): void
    {
        $authUser = $request->user();

        if (! $authUser instanceof User || ! $authUser->isManagement() || ! $authUser->hasAccess(User::PERMISSION_MODULE_SETTINGS_ROLE_ACCESS, $minLevel)) {
            throw new AuthorizationException('Anda tidak memiliki akses yang cukup untuk halaman Role Access ini.');
        }
    }
}
