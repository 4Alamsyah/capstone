<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\AccountingAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $entries = JournalEntry::query()
            ->with(['fiscalPeriod:id,code', 'lines.chartOfAccount:id,code,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('entry_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest('entry_date')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('accounting/JournalEntries', [
            'entries' => collect($entries->items())->map(function (JournalEntry $entry): array {
                return [
                    'id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                    'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
                    'description' => $entry->description,
                    'status' => $entry->status,
                    'fiscal_period_id' => $entry->fiscal_period_id,
                    'fiscal_period_code' => $entry->fiscalPeriod?->code,
                    'lines' => $entry->lines->map(fn (JournalLine $line): array => [
                        'id' => $line->id,
                        'chart_of_account_id' => $line->chart_of_account_id,
                        'chart_of_account_code' => $line->chartOfAccount?->code,
                        'chart_of_account_name' => $line->chartOfAccount?->name,
                        'line_type' => $line->line_type,
                        'amount' => (float) $line->amount,
                        'description' => $line->description,
                    ])->values(),
                ];
            })->values(),
            'fiscalPeriods' => FiscalPeriod::query()->orderByDesc('code')->get(['id', 'code', 'status']),
            'accounts' => ChartOfAccount::query()->orderBy('code')->get(['id', 'code', 'name', 'status']),
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
                'from' => $entries->firstItem(),
                'to' => $entries->lastItem(),
            ],
        ]);
    }

    public function manualJournal(): Response
    {
        return Inertia::render('accounting/ManualJournal', [
            'fiscalPeriods' => FiscalPeriod::query()->orderByDesc('code')->get(['id', 'code', 'status']),
            'accounts' => ChartOfAccount::query()->orderBy('code')->get(['id', 'code', 'name', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_number' => ['required', 'string', 'max:50', 'unique:journal_entries,entry_number'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,posted'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.line_type' => ['required', 'string', 'in:debit,credit'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
            'lines.*.description' => ['nullable', 'string'],
        ]);

        $debitTotal = collect($validated['lines'])->where('line_type', 'debit')->sum('amount');
        $creditTotal = collect($validated['lines'])->where('line_type', 'credit')->sum('amount');

        if ((float) $debitTotal !== (float) $creditTotal) {
            throw ValidationException::withMessages([
                'lines' => 'Total debit dan credit harus sama.',
            ]);
        }

        DB::transaction(function () use ($validated): void {
            $entry = JournalEntry::query()->create([
                'entry_number' => $validated['entry_number'],
                'fiscal_period_id' => $validated['fiscal_period_id'],
                'entry_date' => $validated['entry_date'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'posted_at' => $validated['status'] === 'posted' ? now() : null,
            ]);

            foreach ($validated['lines'] as $line) {
                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'line_type' => $line['line_type'],
                    'amount' => $line['amount'],
                    'description' => $line['description'] ?? null,
                ]);
            }

            AccountingAuditLogger::record('Created journal entry', $entry, $entry->entry_number);
        });

        return back();
    }

    public function update(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $validated = $request->validate([
            'entry_number' => ['required', 'string', 'max:50', 'unique:journal_entries,entry_number,' . $journalEntry->id],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,posted'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.line_type' => ['required', 'string', 'in:debit,credit'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
            'lines.*.description' => ['nullable', 'string'],
        ]);

        $debitTotal = collect($validated['lines'])->where('line_type', 'debit')->sum('amount');
        $creditTotal = collect($validated['lines'])->where('line_type', 'credit')->sum('amount');

        if ((float) $debitTotal !== (float) $creditTotal) {
            throw ValidationException::withMessages([
                'lines' => 'Total debit dan credit harus sama.',
            ]);
        }

        DB::transaction(function () use ($validated, $journalEntry): void {
            $journalEntry->update([
                'entry_number' => $validated['entry_number'],
                'fiscal_period_id' => $validated['fiscal_period_id'],
                'entry_date' => $validated['entry_date'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'posted_at' => $validated['status'] === 'posted' ? now() : null,
            ]);

            $journalEntry->lines()->delete();

            foreach ($validated['lines'] as $line) {
                JournalLine::query()->create([
                    'journal_entry_id' => $journalEntry->id,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'line_type' => $line['line_type'],
                    'amount' => $line['amount'],
                    'description' => $line['description'] ?? null,
                ]);
            }

            AccountingAuditLogger::record('Updated journal entry', $journalEntry, $journalEntry->entry_number);
        });

        return back();
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        AccountingAuditLogger::record('Deleted journal entry', $journalEntry, $journalEntry->entry_number);
        $journalEntry->delete();

        return back();
    }
}
