<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalReportController extends Controller
{
    private const EXPORT_HEADERS = ['Date', 'Entry Number', 'Fiscal Period', 'Status', 'Account Code', 'Account Name', 'Line Description', 'Debit', 'Credit'];

    public function index(Request $request): Response
    {
        $filters = $this->resolveFilters($request);

        $entries = $this->baseEntryQuery($filters)
            ->orderBy('entry_date')
            ->orderBy('entry_number')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('accounting/JournalReport', [
            'entries' => collect($entries->items())->map(fn (JournalEntry $entry): array => $this->mapEntry($entry))->values(),
            'fiscalPeriods' => FiscalPeriod::query()->orderByDesc('code')->get(['id', 'code', 'status']),
            'accounts' => ChartOfAccount::query()->orderBy('code')->get(['id', 'code', 'name', 'status']),
            'filters' => $filters,
            'totals' => $this->aggregateTotals($filters),
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'from' => $entries->firstItem(),
                'to' => $entries->lastItem(),
                'prev_page_url' => $entries->previousPageUrl(),
                'next_page_url' => $entries->nextPageUrl(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);

        $entries = $this->baseEntryQuery($filters)
            ->orderBy('entry_date')
            ->orderBy('entry_number')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Journal Report');
        $sheet->fromArray(self::EXPORT_HEADERS, null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $rows = [];
        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $debit = $line->line_type === 'debit' ? (float) $line->amount : 0.0;
                $credit = $line->line_type === 'credit' ? (float) $line->amount : 0.0;
                $debitTotal += $debit;
                $creditTotal += $credit;

                $rows[] = [
                    optional($entry->entry_date)->format('Y-m-d'),
                    $entry->entry_number,
                    $entry->fiscalPeriod?->code,
                    $entry->status,
                    $line->chartOfAccount?->code,
                    $line->chartOfAccount?->name,
                    $line->description,
                    $debit ?: null,
                    $credit ?: null,
                ];
            }
        }

        $rows[] = ['', '', '', '', '', '', 'TOTAL', $debitTotal, $creditTotal];

        $sheet->fromArray($rows, null, 'A2');
        $totalRow = count($rows) + 1;
        $sheet->getStyle("A{$totalRow}:I{$totalRow}")->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'journal-report-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function pdf(Request $request): HttpResponse
    {
        $filters = $this->resolveFilters($request);

        $entries = $this->baseEntryQuery($filters)
            ->orderBy('entry_date')
            ->orderBy('entry_number')
            ->get()
            ->map(fn (JournalEntry $entry): array => $this->mapEntry($entry));

        $pdf = Pdf::loadView('documents.journal-report', [
            'entries' => $entries,
            'filters' => $filters,
            'totals' => $this->aggregateTotals($filters),
            'fiscalPeriodLabel' => $filters['fiscal_period_id']
                ? FiscalPeriod::find($filters['fiscal_period_id'])?->code
                : null,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Journal-Report-'.now()->format('Y-m-d-His').'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();
        $search = trim((string) $request->string('search'));

        return [
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
            'fiscal_period_id' => $request->integer('fiscal_period_id') ?: null,
            'status' => in_array($status, ['draft', 'posted'], true) ? $status : null,
            'account_id' => $request->integer('account_id') ?: null,
            'search' => $search,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['date_from'], fn (Builder $q) => $q->whereDate('entry_date', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn (Builder $q) => $q->whereDate('entry_date', '<=', $filters['date_to']))
            ->when($filters['fiscal_period_id'], fn (Builder $q) => $q->where('fiscal_period_id', $filters['fiscal_period_id']))
            ->when($filters['status'], fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['account_id'], function (Builder $q) use ($filters): void {
                $q->whereHas('lines', fn (Builder $lineQuery) => $lineQuery->where('chart_of_account_id', $filters['account_id']));
            })
            ->when($filters['search'] !== '', function (Builder $q) use ($filters): void {
                $q->where(function (Builder $inner) use ($filters): void {
                    $inner->where('entry_number', 'like', "%{$filters['search']}%")
                        ->orWhere('description', 'like', "%{$filters['search']}%");
                });
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseEntryQuery(array $filters): Builder
    {
        return $this->applyFilters(
            JournalEntry::query()->with(['fiscalPeriod:id,code', 'lines.chartOfAccount:id,code,name']),
            $filters
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEntry(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
            'description' => $entry->description,
            'status' => $entry->status,
            'fiscal_period_code' => $entry->fiscalPeriod?->code,
            'lines' => $entry->lines->map(fn (JournalLine $line): array => [
                'id' => $line->id,
                'chart_of_account_code' => $line->chartOfAccount?->code,
                'chart_of_account_name' => $line->chartOfAccount?->name,
                'line_type' => $line->line_type,
                'amount' => (float) $line->amount,
                'description' => $line->description,
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float>
     */
    private function aggregateTotals(array $filters): array
    {
        $sums = JournalLine::query()
            ->whereHas('journalEntry', fn (Builder $q) => $this->applyFilters($q, $filters))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN line_type = 'debit' THEN amount ELSE 0 END), 0) as debit_total,
                COALESCE(SUM(CASE WHEN line_type = 'credit' THEN amount ELSE 0 END), 0) as credit_total
            ")
            ->first();

        return [
            'debit_total' => (float) $sums->debit_total,
            'credit_total' => (float) $sums->credit_total,
        ];
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
