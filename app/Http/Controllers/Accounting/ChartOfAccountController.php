<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Support\AccountingAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChartOfAccountController extends Controller
{
    private const TEMPLATE_HEADERS = ['Code', 'Name', 'Type', 'Category', 'Status'];

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
                'account_type' => $account->account_type,
                'status' => $account->status,
            ])->values(),
            'typeLabels' => ChartOfAccount::typeLabels(),
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'total' => $accounts->total(),
                'from' => $accounts->firstItem(),
                'to' => $accounts->lastItem(),
            ],
            'importResult' => session('import_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'account_type' => ['required', 'string', 'in:'.implode(',', array_keys(ChartOfAccount::typeLabels()))],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $account = ChartOfAccount::query()->create($validated);
        AccountingAuditLogger::record('Created chart of account', $account, $account->code);

        return back();
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:chart_of_accounts,code,'.$chartOfAccount->id],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'account_type' => ['required', 'string', 'in:'.implode(',', array_keys(ChartOfAccount::typeLabels()))],
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

    public function export(Request $request): StreamedResponse
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
            ->orderBy('code')
            ->get(['code', 'name', 'category', 'account_type', 'status']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Chart of Accounts');
        $sheet->fromArray(self::TEMPLATE_HEADERS, null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $sheet->fromArray(
            $accounts->map(fn (ChartOfAccount $account): array => [
                $account->code,
                $account->name,
                $account->account_type,
                $account->category,
                $account->status,
            ])->all(),
            null,
            'A2'
        );

        foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'chart-of-accounts-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');
        $sheet->fromArray(self::TEMPLATE_HEADERS, null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $sheet->fromArray([
            ['1000-01', 'Kas Kecil', 'asset', 'Current Asset', 'active'],
            ['4000-01', 'Pendapatan Jasa', 'revenue', 'Revenue', 'active'],
        ], null, 'A2');

        $typeKeys = implode(',', array_keys(ChartOfAccount::typeLabels()));

        for ($row = 2; $row <= 200; $row++) {
            $typeValidation = $sheet->getCell("C{$row}")->getDataValidation();
            $typeValidation->setType(DataValidation::TYPE_LIST);
            $typeValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $typeValidation->setAllowBlank(true);
            $typeValidation->setShowDropDown(true);
            $typeValidation->setShowErrorMessage(true);
            $typeValidation->setErrorTitle('Type tidak valid');
            $typeValidation->setError("Gunakan salah satu: {$typeKeys}.");
            $typeValidation->setFormula1('"'.$typeKeys.'"');

            $statusValidation = $sheet->getCell("E{$row}")->getDataValidation();
            $statusValidation->setType(DataValidation::TYPE_LIST);
            $statusValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $statusValidation->setAllowBlank(true);
            $statusValidation->setShowDropDown(true);
            $statusValidation->setShowErrorMessage(true);
            $statusValidation->setErrorTitle('Status tidak valid');
            $statusValidation->setError('Gunakan "active" atau "inactive".');
            $statusValidation->setFormula1('"active,inactive"');
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'chart-of-accounts-import-template.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => 'File tidak dapat dibaca. Pastikan formatnya sesuai template.',
            ]);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $startIndex = isset($rows[0]) && $this->looksLikeHeaderRow($rows[0]) ? 1 : 0;

        $created = 0;
        $updated = 0;
        $errors = [];
        $seenCodes = [];

        DB::transaction(function () use ($rows, $startIndex, &$created, &$updated, &$errors, &$seenCodes): void {
            for ($i = $startIndex; $i < count($rows); $i++) {
                $lineNumber = $i + 1;
                $row = $rows[$i];

                $code = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? ''));
                $accountType = strtolower(trim((string) ($row[2] ?? '')));
                $category = trim((string) ($row[3] ?? ''));
                $status = strtolower(trim((string) ($row[4] ?? '')) ?: 'active');

                if ($code === '' && $name === '' && $category === '') {
                    continue;
                }

                if ($code === '') {
                    $errors[] = "Baris {$lineNumber}: Code wajib diisi.";

                    continue;
                }

                if ($name === '') {
                    $errors[] = "Baris {$lineNumber}: Name wajib diisi.";

                    continue;
                }

                if (! array_key_exists($accountType, ChartOfAccount::typeLabels())) {
                    $validTypes = implode('/', array_keys(ChartOfAccount::typeLabels()));
                    $errors[] = "Baris {$lineNumber}: Type '{$accountType}' tidak valid (gunakan {$validTypes}).";

                    continue;
                }

                if ($category === '') {
                    $errors[] = "Baris {$lineNumber}: Category wajib diisi.";

                    continue;
                }

                if (! in_array($status, ['active', 'inactive'], true)) {
                    $errors[] = "Baris {$lineNumber}: Status '{$status}' tidak valid (gunakan active/inactive).";

                    continue;
                }

                if (isset($seenCodes[$code])) {
                    $errors[] = "Baris {$lineNumber}: Code '{$code}' duplikat di file, baris ini dilewati.";

                    continue;
                }

                $seenCodes[$code] = true;

                $account = ChartOfAccount::query()->where('code', $code)->first();

                if ($account) {
                    $account->update([
                        'name' => $name,
                        'category' => $category,
                        'account_type' => $accountType,
                        'status' => $status,
                    ]);
                    AccountingAuditLogger::record('Updated chart of account via import', $account, $account->code);
                    $updated++;
                } else {
                    $account = ChartOfAccount::query()->create([
                        'code' => $code,
                        'name' => $name,
                        'category' => $category,
                        'account_type' => $accountType,
                        'status' => $status,
                    ]);
                    AccountingAuditLogger::record('Created chart of account via import', $account, $account->code);
                    $created++;
                }
            }
        });

        return back()->with('import_result', [
            'created' => $created,
            'updated' => $updated,
            'errors' => array_slice($errors, 0, 50),
            'errorCount' => count($errors),
        ]);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function looksLikeHeaderRow(array $row): bool
    {
        return strcasecmp(trim((string) ($row[0] ?? '')), 'code') === 0;
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
