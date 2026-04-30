<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\AccountingAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalLineController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $lines = JournalLine::query()
            ->with(['journalEntry:id,entry_number,entry_date', 'chartOfAccount:id,code,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('journalEntry', function ($entryQuery) use ($search): void {
                    $entryQuery->where('entry_number', 'like', "%{$search}%");
                })->orWhereHas('chartOfAccount', function ($accountQuery) use ($search): void {
                    $accountQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('accounting/JournalLines', [
            'lines' => collect($lines->items())->map(fn (JournalLine $line): array => [
                'id' => $line->id,
                'journal_entry_id' => $line->journal_entry_id,
                'journal_entry_number' => $line->journalEntry?->entry_number,
                'chart_of_account_id' => $line->chart_of_account_id,
                'chart_of_account_code' => $line->chartOfAccount?->code,
                'chart_of_account_name' => $line->chartOfAccount?->name,
                'line_type' => $line->line_type,
                'amount' => (float) $line->amount,
                'description' => $line->description,
            ])->values(),
            'entries' => JournalEntry::query()->orderByDesc('entry_number')->get(['id', 'entry_number']),
            'accounts' => ChartOfAccount::query()->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page' => $lines->currentPage(),
                'last_page' => $lines->lastPage(),
                'total' => $lines->total(),
                'from' => $lines->firstItem(),
                'to' => $lines->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'journal_entry_id' => ['required', 'integer', 'exists:journal_entries,id'],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'line_type' => ['required', 'string', 'in:debit,credit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
        ]);

        $line = JournalLine::query()->create($validated);
        AccountingAuditLogger::record('Created journal line', $line, (string) $line->id);

        return back();
    }

    public function update(Request $request, JournalLine $journalLine): RedirectResponse
    {
        $validated = $request->validate([
            'journal_entry_id' => ['required', 'integer', 'exists:journal_entries,id'],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'line_type' => ['required', 'string', 'in:debit,credit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
        ]);

        $journalLine->update($validated);
        AccountingAuditLogger::record('Updated journal line', $journalLine, (string) $journalLine->id);

        return back();
    }

    public function destroy(JournalLine $journalLine): RedirectResponse
    {
        AccountingAuditLogger::record('Deleted journal line', $journalLine, (string) $journalLine->id);
        $journalLine->delete();

        return back();
    }
}
