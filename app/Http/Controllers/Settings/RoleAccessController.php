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
        $this->authorizeManagementAccess($request);

        return Inertia::render('settings/RoleAccess', [
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'permissions'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'permissions' => $user->resolvedPermissions(),
                ])
                ->values(),
            'roles' => [
                User::ROLE_ADMIN,
                User::ROLE_STAFF,
                User::ROLE_GM,
                User::ROLE_DIRECTOR,
            ],
            'permissionLabels' => User::permissionLabels(),
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
        $this->authorizeManagementAccess($request);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,staff,gm,director'],
            'permissions' => ['required', 'array'],
        ]);

        $allowedKeys = array_keys(User::permissionLabels());
        $normalizedPermissions = User::permissionsTemplateForRole((string) $validated['role']);

        foreach ($allowedKeys as $key) {
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
        $this->authorizeManagementAccess($request);

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
        ]);

        return back()->with('status', 'user-created')->with('created_user_id', (string) $user->id);
    }

    /**
     * Restrict access to management roles only.
     */
    private function authorizeManagementAccess(Request $request): void
    {
        $authUser = $request->user();

        if (! $authUser instanceof User || ! $authUser->isManagement() || ! $authUser->hasPermission(User::PERMISSION_MENU_ROLE_ACCESS)) {
            throw new AuthorizationException('Hanya GM/Director yang dapat mengatur role dan akses pengguna.');
        }
    }
}
