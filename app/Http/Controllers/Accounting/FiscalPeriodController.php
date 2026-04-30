<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Support\AccountingAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FiscalPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $periods = FiscalPeriod::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('accounting/FiscalPeriods', [
            'periods' => collect($periods->items())->map(fn (FiscalPeriod $period): array => [
                'id' => $period->id,
                'code' => $period->code,
                'start_date' => optional($period->start_date)->format('Y-m-d'),
                'end_date' => optional($period->end_date)->format('Y-m-d'),
                'status' => $period->status,
            ])->values(),
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page' => $periods->currentPage(),
                'last_page' => $periods->lastPage(),
                'total' => $periods->total(),
                'from' => $periods->firstItem(),
                'to' => $periods->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:fiscal_periods,code'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'in:open,closed'],
        ]);

        $period = FiscalPeriod::query()->create($validated);
        AccountingAuditLogger::record('Created fiscal period', $period, $period->code);

        return back();
    }

    public function update(Request $request, FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:fiscal_periods,code,' . $fiscalPeriod->id],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'in:open,closed'],
        ]);

        $fiscalPeriod->update($validated);
        AccountingAuditLogger::record('Updated fiscal period', $fiscalPeriod, $fiscalPeriod->code);

        return back();
    }

    public function destroy(FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        AccountingAuditLogger::record('Deleted fiscal period', $fiscalPeriod, $fiscalPeriod->code);
        $fiscalPeriod->delete();

        return back();
    }
}
