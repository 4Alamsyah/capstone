<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingAuditTrail;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AccountingAuditTrailController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $filters = [
            'search' => trim((string) ($validated['search'] ?? '')),
            'user_id' => $validated['user_id'] ?? null,
            'subject_type' => $validated['subject_type'] ?? null,
            'action' => $validated['action'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];

        $trails = $this->filteredQuery($filters)
            ->with('user:id,name,email')
            ->latest('happened_at')
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('accounting/AuditTrails', [
            'trails' => collect($trails->items())->map(fn (AccountingAuditTrail $trail): array => [
                'id' => $trail->id,
                'actor' => $trail->user?->name ?? 'System',
                'actor_email' => $trail->user?->email,
                'action' => $trail->action,
                'category' => $this->categorize($trail->action),
                'subject_type' => $trail->subject_type,
                'subject_label' => Str::headline(class_basename($trail->subject_type)),
                'subject_id' => $trail->subject_id,
                'details' => $trail->details,
                'time' => optional($trail->happened_at)->format('Y-m-d H:i'),
                'happened_at_full' => optional($trail->happened_at)->format('l, d F Y H:i:s'),
                'happened_at_iso' => optional($trail->happened_at)->toIso8601String(),
                'recorded_at' => optional($trail->created_at)->format('Y-m-d H:i:s'),
            ])->values(),
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'stats' => $this->stats($filters, $trails->total()),
            'pagination' => [
                'current_page' => $trails->currentPage(),
                'last_page' => $trails->lastPage(),
                'per_page' => $trails->perPage(),
                'total' => $trails->total(),
                'from' => $trails->firstItem(),
                'to' => $trails->lastItem(),
            ],
        ]);
    }

    public function destroy(AccountingAuditTrail $accountingAuditTrail): RedirectResponse
    {
        $accountingAuditTrail->delete();

        return back();
    }

    /**
     * Build the audit trail query with all active filters applied.
     *
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $search = (string) $filters['search'];

        return AccountingAuditTrail::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                // lower() keeps the match case-insensitive on both PostgreSQL
                // (where LIKE is case-sensitive) and SQLite.
                $needle = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $inner) use ($needle): void {
                    $inner->whereRaw('lower(action) like ?', [$needle])
                        ->orWhereRaw('lower(subject_type) like ?', [$needle])
                        ->orWhereRaw("lower(coalesce(details, '')) like ?", [$needle])
                        ->orWhereHas('user', function (Builder $user) use ($needle): void {
                            $user->whereRaw('lower(name) like ?', [$needle])
                                ->orWhereRaw('lower(email) like ?', [$needle]);
                        });
                });
            })
            ->when($filters['user_id'], fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['subject_type'], fn (Builder $query) => $query->where('subject_type', $filters['subject_type']))
            ->when($filters['action'], fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['date_from'], fn (Builder $query) => $query->whereDate('happened_at', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn (Builder $query) => $query->whereDate('happened_at', '<=', $filters['date_to']));
    }

    /**
     * Dropdown options built from what actually exists in the log, so the
     * filters can never offer a combination that returns nothing.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        $actorIds = AccountingAuditTrail::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        return [
            'users' => User::query()
                ->whereIn('id', $actorIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => [
                    'value' => $user->id,
                    'label' => $user->name,
                ])
                ->values(),
            'subjectTypes' => AccountingAuditTrail::query()
                ->select('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'label' => Str::headline(class_basename($type)),
                ])
                ->values(),
            'actions' => AccountingAuditTrail::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->values(),
        ];
    }

    /**
     * Headline figures for the currently filtered result set.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function stats(array $filters, int $total): array
    {
        return [
            'total' => $total,
            'today' => $this->filteredQuery($filters)->whereDate('happened_at', now()->toDateString())->count(),
            'actors' => $this->filteredQuery($filters)->distinct()->count('user_id'),
        ];
    }

    /**
     * Group a free-text action string into a coarse category, used only to
     * colour-code entries so a long log stays scannable.
     */
    private function categorize(string $action): string
    {
        $action = mb_strtolower($action);

        return match (true) {
            str_contains($action, 'delet') => 'deleted',
            str_contains($action, 'creat') => 'created',
            str_contains($action, 'updat') => 'updated',
            str_contains($action, 'payment') => 'payment',
            str_contains($action, 'approv'), str_contains($action, 'post'), str_contains($action, 'sent') => 'posted',
            default => 'other',
        };
    }
}
