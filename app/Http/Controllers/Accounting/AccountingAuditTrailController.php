<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingAuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingAuditTrailController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $trails = AccountingAuditTrail::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('action', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%");
                });
            })
            ->latest('happened_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('accounting/AuditTrails', [
            'trails' => collect($trails->items())->map(fn (AccountingAuditTrail $trail): array => [
                'id' => $trail->id,
                'actor' => $trail->user?->name ?? 'System',
                'action' => $trail->action,
                'subject' => $trail->subject_type . '#' . $trail->subject_id,
                'details' => $trail->details,
                'time' => optional($trail->happened_at)->format('Y-m-d H:i'),
            ])->values(),
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page' => $trails->currentPage(),
                'last_page' => $trails->lastPage(),
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
}
