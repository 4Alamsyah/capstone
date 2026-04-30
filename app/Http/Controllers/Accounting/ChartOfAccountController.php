<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingAuditTrail;
use App\Models\ChartOfAccount;
use App\Support\AccountingAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $accounts = ChartOfAccount::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('accounting/ChartOfAccounts', [
            'accounts' => collect($accounts->items())->map(fn (ChartOfAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'category' => $account->category,
                'status' => $account->status,
            ])->values(),
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'total' => $accounts->total(),
                'from' => $accounts->firstItem(),
                'to' => $accounts->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $account = ChartOfAccount::query()->create($validated);
        AccountingAuditLogger::record('Created chart of account', $account, $account->code);

        return back();
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:chart_of_accounts,code,' . $chartOfAccount->id],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $chartOfAccount->update($validated);
        AccountingAuditLogger::record('Updated chart of account', $chartOfAccount, $chartOfAccount->code);

        return back();
    }

    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        AccountingAuditLogger::record('Deleted chart of account', $chartOfAccount, $chartOfAccount->code);
        $chartOfAccount->delete();

        return back();
    }
}
