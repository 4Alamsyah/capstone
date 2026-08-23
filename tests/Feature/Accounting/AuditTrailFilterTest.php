<?php

use App\Models\AccountingAuditTrail;
use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\User;

function auditActor(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function makeTrail(array $attributes = []): AccountingAuditTrail
{
    return AccountingAuditTrail::query()->create(array_merge([
        'user_id' => null,
        'action' => 'Created chart of account',
        'subject_type' => ChartOfAccount::class,
        'subject_id' => 1,
        'details' => '1000',
        'happened_at' => now(),
    ], $attributes));
}

test('audit trail index exposes detail fields, filter options and stats', function () {
    $admin = auditActor();
    makeTrail(['user_id' => $admin->id, 'details' => 'ACC-1000']);

    $this->actingAs($admin)
        ->get('/accounting/audit-trails')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('accounting/AuditTrails')
            ->has('trails', 1)
            ->where('trails.0.actor', $admin->name)
            ->where('trails.0.actor_email', $admin->email)
            ->where('trails.0.category', 'created')
            ->where('trails.0.subject_label', 'Chart Of Account')
            ->where('trails.0.subject_type', ChartOfAccount::class)
            ->where('trails.0.details', 'ACC-1000')
            ->has('trails.0.happened_at_full')
            ->has('trails.0.recorded_at')
            ->has('filterOptions.users', 1)
            ->has('filterOptions.subjectTypes', 1)
            ->has('filterOptions.actions', 1)
            ->where('stats.total', 1)
            ->where('stats.today', 1)
            ->where('stats.actors', 1)
            ->where('pagination.last_page', 1)
        );
});

test('audit trails can be filtered by subject type', function () {
    $admin = auditActor();
    makeTrail(['user_id' => $admin->id, 'subject_type' => ChartOfAccount::class]);
    makeTrail(['user_id' => $admin->id, 'subject_type' => FiscalPeriod::class, 'action' => 'Created fiscal period']);

    $this->actingAs($admin)
        ->get('/accounting/audit-trails?subject_type='.urlencode(FiscalPeriod::class))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('trails', 1)
            ->where('trails.0.subject_label', 'Fiscal Period')
        );
});

test('audit trails can be filtered by actor and by action', function () {
    $admin = auditActor();
    $other = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Other Person']);

    makeTrail(['user_id' => $admin->id, 'action' => 'Created chart of account']);
    makeTrail(['user_id' => $other->id, 'action' => 'Deleted journal entry']);

    $this->actingAs($admin)
        ->get("/accounting/audit-trails?user_id={$other->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('trails', 1)
            ->where('trails.0.actor', 'Other Person')
            ->where('trails.0.category', 'deleted')
        );

    $this->actingAs($admin)
        ->get('/accounting/audit-trails?action='.urlencode('Created chart of account'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('trails', 1)->where('trails.0.category', 'created'));
});

test('audit trails can be filtered by date range', function () {
    $admin = auditActor();
    makeTrail(['user_id' => $admin->id, 'happened_at' => now()->subDays(10), 'details' => 'OLD']);
    makeTrail(['user_id' => $admin->id, 'happened_at' => now(), 'details' => 'NEW']);

    $this->actingAs($admin)
        ->get('/accounting/audit-trails?date_from='.now()->subDay()->toDateString())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('trails', 1)
            ->where('trails.0.details', 'NEW')
            ->where('stats.total', 1)
        );
});

test('audit trail search is case insensitive and matches the acting user name', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Budi Santoso']);
    makeTrail(['user_id' => $admin->id, 'action' => 'Created chart of account']);
    makeTrail(['user_id' => null, 'action' => 'Deleted journal entry', 'details' => 'JE-9']);

    // Lower-case needle against a capitalised action — fails under a plain
    // case-sensitive LIKE on PostgreSQL.
    $this->actingAs($admin)
        ->get('/accounting/audit-trails?search=created')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('trails', 1));

    $this->actingAs($admin)
        ->get('/accounting/audit-trails?search=budi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('trails', 1)->where('trails.0.actor', 'Budi Santoso'));
});

test('audit trail pagination respects per_page', function () {
    $admin = auditActor();

    foreach (range(1, 12) as $i) {
        makeTrail(['user_id' => $admin->id, 'subject_id' => $i]);
    }

    $this->actingAs($admin)
        ->get('/accounting/audit-trails')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('trails', 10)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 12)
        );

    $this->actingAs($admin)
        ->get('/accounting/audit-trails?per_page=25')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('trails', 12)->where('pagination.last_page', 1));
});

test('audit trail rejects an invalid date range', function () {
    $admin = auditActor();

    $this->actingAs($admin)
        ->get('/accounting/audit-trails?date_from=2026-05-01&date_to=2026-04-01')
        ->assertSessionHasErrors('date_to');
});
