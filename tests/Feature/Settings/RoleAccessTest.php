<?php

use App\Models\User;

test('role access page renders with the new permission matrix', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

    $response = $this->actingAs($admin)->get('/settings/role-access');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/RoleAccess')
        ->has('moduleGroups.parts.submodules')
        ->has('permissionLevels.full')
        ->has('approvePermissionLabels')
        ->has('users', 2)
        ->where('canEdit', true)
        ->where('canCreateUser', true)
    );
});

test('a user with only view access to role_access can view the page read-only but not mutate it', function () {
    $permissions = User::permissionsTemplateForRole(User::ROLE_GM);
    $permissions['module.settings.role_access'] = User::LEVEL_VIEW;

    $viewer = User::factory()->create([
        'role' => User::ROLE_GM,
        'permissions' => $permissions,
    ]);
    $other = User::factory()->create(['role' => User::ROLE_STAFF]);

    // The core bug: "view" access used to 403 even the read-only page load,
    // because the controller hardcoded an "edit" minimum for every action.
    $this->actingAs($viewer)
        ->get('/settings/role-access')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/RoleAccess')
            ->where('canEdit', false)
            ->where('canCreateUser', false)
        );

    $this->actingAs($viewer)
        ->patch("/settings/role-access/{$other->id}", [
            'role' => User::ROLE_STAFF,
            'permissions' => User::permissionsTemplateForRole(User::ROLE_STAFF),
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->patch("/settings/role-access/{$other->id}/status", ['is_active' => false])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post('/settings/role-access', [
            'name' => 'New User',
            'email' => 'new-user@example.test',
            'role' => User::ROLE_STAFF,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertForbidden();
});

test('non-management staff cannot access role access page', function () {
    $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

    $this->actingAs($staff)->get('/settings/role-access')->assertForbidden();
});

test('admin can update a user sub-module level and it is enforced', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $staff = User::factory()->create([
        'role' => User::ROLE_STAFF,
        'permissions' => User::permissionsTemplateForRole(User::ROLE_STAFF),
    ]);

    $permissions = User::permissionsTemplateForRole(User::ROLE_STAFF);
    $permissions['module.parts.master'] = User::LEVEL_PROHIBITED;

    $this->actingAs($admin)
        ->patch("/settings/role-access/{$staff->id}", [
            'role' => User::ROLE_STAFF,
            'permissions' => $permissions,
        ])
        ->assertRedirect();

    $staff->refresh();
    expect($staff->hasAccess('module.parts.master', User::LEVEL_VIEW))->toBeFalse();

    $this->actingAs($staff)->get('/parts')->assertForbidden();
});

test('admin status endpoint actually flips is_active', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

    $this->actingAs($admin)
        ->patch("/settings/role-access/{$staff->id}/status", ['is_active' => false])
        ->assertRedirect();

    expect($staff->fresh()->is_active)->toBeFalse();
});

test('an inactive user is force-logged-out on their very next request', function () {
    // Laravel's non-Octane request lifecycle re-resolves the authenticated user
    // from the DB fresh on every real HTTP request (a new container per request),
    // so a deactivation that lands between two requests is picked up on the next
    // one. That per-request re-resolution can't be exercised via two chained
    // TestCase calls sharing one in-process app/guard, so this asserts the
    // middleware's actual condition directly: is_active=false => bounced to login.
    $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => false]);

    $this->actingAs($staff)->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

test('a deactivated user cannot log back in', function () {
    $staff = User::factory()->create([
        'role' => User::ROLE_STAFF,
        'password' => bcrypt('password123'),
        'is_active' => false,
    ]);

    $this->post('/login', [
        'email' => $staff->email,
        'password' => 'password123',
    ])->assertSessionHasErrors();

    $this->assertGuest();
});

test('admin cannot deactivate their own account', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->patch("/settings/role-access/{$admin->id}/status", ['is_active' => false]);

    expect($admin->fresh()->is_active)->toBeTrue();
});
