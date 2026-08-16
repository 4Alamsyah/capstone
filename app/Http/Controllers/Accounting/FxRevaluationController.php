<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ApInvoice;
use App\Models\AppSetting;
use App\Models\ExchangeRate;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\AccountingAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FxRevaluationController extends Controller
{
    public function index(Request $request): Response
    {
        $revaluationDate = $request->string('revaluation_date')->trim()->toString();
        $revaluationDate = $revaluationDate !== '' ? $revaluationDate : now()->format('Y-m-d');

        $baseCurrencyCode = (string) AppSetting::get('default_currency_code', 'IDR');
        $preview = $this->buildPreview($revaluationDate, $baseCurrencyCode);

        return Inertia::render('accounting/FxRevaluation', [
            'revaluationDate' => $revaluationDate,
            'baseCurrencyCode' => $baseCurrencyCode,
            'arLines' => $preview['ar'],
            'apLines' => $preview['ap'],
            'totalGainLoss' => (string) $preview['total'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'revaluation_date' => ['required', 'date'],
        ]);

        $revaluationDate = $validated['revaluation_date'];
        $baseCurrencyCode = (string) AppSetting::get('default_currency_code', 'IDR');

        $fiscalPeriod = FiscalPeriod::findOpenPeriodForDate($revaluationDate);

        if (! $fiscalPeriod) {
            throw ValidationException::withMessages([
                'revaluation_date' => 'Tidak ada Fiscal Period terbuka untuk tanggal ini. Buka dulu di Accounting > Fiscal Periods.',
            ]);
        }

        $preview = $this->buildPreview($revaluationDate, $baseCurrencyCode);
        $adjustments = collect($preview['ar'])->concat($preview['ap'])->filter(fn (array $row): bool => abs((float) $row['difference']) >= 0.01);

        if ($adjustments->isEmpty()) {
            return back()->with('success', 'Tidak ada penyesuaian FX yang diperlukan pada tanggal ini.');
        }

        $arAccountId = (int) AppSetting::get('gl_ar_account_id');
        $apAccountId = (int) AppSetting::get('gl_ap_account_id');
        $gainAccountId = (int) AppSetting::get('gl_unrealized_fx_gain_account_id');
        $lossAccountId = (int) AppSetting::get('gl_unrealized_fx_loss_account_id');

        if (! $arAccountId || ! $apAccountId) {
            throw ValidationException::withMessages([
                'revaluation_date' => 'GL Account Mapping untuk AR/AP belum diatur. Buka dulu di Accounting > GL Setting.',
            ]);
        }

        DB::transaction(function () use ($adjustments, $fiscalPeriod, $revaluationDate, $arAccountId, $apAccountId, $gainAccountId, $lossAccountId): void {
            $entry = JournalEntry::query()->create([
                'fiscal_period_id' => $fiscalPeriod->id,
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $revaluationDate,
                'description' => 'Unrealized FX revaluation as of '.$revaluationDate,
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            foreach ($adjustments as $row) {
                $difference = (float) $row['difference'];
                $controlAccountId = $row['type'] === 'ar' ? $arAccountId : $apAccountId;

                // AR: value up = gain (debit AR, credit gain). AP: liability up = loss (credit AP, debit loss).
                $isGain = $row['type'] === 'ar' ? $difference > 0 : $difference < 0;

                if ($isGain && ! $gainAccountId) {
                    throw ValidationException::withMessages([
                        'revaluation_date' => 'GL Account Mapping untuk Unrealized FX Gain belum diatur. Buka dulu di Accounting > GL Setting.',
                    ]);
                }

                if (! $isGain && ! $lossAccountId) {
                    throw ValidationException::withMessages([
                        'revaluation_date' => 'GL Account Mapping untuk Unrealized FX Loss belum diatur. Buka dulu di Accounting > GL Setting.',
                    ]);
                }

                $amount = abs($difference);
                $controlLineType = $row['type'] === 'ar'
                    ? ($difference > 0 ? 'debit' : 'credit')
                    : ($difference > 0 ? 'credit' : 'debit');
                $offsetAccountId = $isGain ? $gainAccountId : $lossAccountId;
                $offsetLineType = $controlLineType === 'debit' ? 'credit' : 'debit';

                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'chart_of_account_id' => $controlAccountId,
                    'line_type' => $controlLineType,
                    'amount' => $amount,
                    'description' => 'FX revaluation: '.$row['document_number'],
                ]);

                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'chart_of_account_id' => $offsetAccountId,
                    'line_type' => $offsetLineType,
                    'amount' => $amount,
                    'description' => 'FX revaluation: '.$row['document_number'].' ('.($isGain ? 'gain' : 'loss').')',
                ]);

                if ($row['type'] === 'ar') {
                    Invoice::query()->where('id', $row['id'])->update(['carrying_exchange_rate' => $row['new_rate']]);
                } else {
                    ApInvoice::query()->where('id', $row['id'])->update(['carrying_exchange_rate' => $row['new_rate']]);
                }
            }

            AccountingAuditLogger::record('Unrealized FX revaluation posted', $entry, $entry->entry_number);
        });

        return to_route('accounting.fx-revaluation.index')
            ->with('success', $adjustments->count().' penyesuaian FX berhasil diposting.');
    }

    /**
     * @return array{ar: array<int, array<string, mixed>>, ap: array<int, array<string, mixed>>, total: float}
     */
    private function buildPreview(string $revaluationDate, string $baseCurrencyCode): array
    {
        $arRows = Invoice::query()
            ->with('customer:id,name')
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID])
            ->where('currency_code', '!=', $baseCurrencyCode)
            ->withSum('payments as amount_paid', 'amount')
            ->get()
            ->map(function (Invoice $invoice) use ($revaluationDate, $baseCurrencyCode): ?array {
                return $this->mapRow(
                    type: 'ar',
                    id: $invoice->id,
                    documentNumber: $invoice->invoice_number,
                    partyName: $invoice->customer?->name,
                    currencyCode: $invoice->currency_code,
                    outstandingForeign: (float) $invoice->total_amount - (float) ($invoice->amount_paid ?? 0),
                    carryingRate: (float) ($invoice->carrying_exchange_rate ?? 1),
                    revaluationDate: $revaluationDate,
                    baseCurrencyCode: $baseCurrencyCode,
                );
            })
            ->filter()
            ->values();

        $apRows = ApInvoice::query()
            ->with('supplier:id,name')
            ->whereIn('status', [ApInvoice::STATUS_APPROVED, ApInvoice::STATUS_PARTIALLY_PAID])
            ->where('currency_code', '!=', $baseCurrencyCode)
            ->withSum('payments as amount_paid', 'amount')
            ->get()
            ->map(function (ApInvoice $apInvoice) use ($revaluationDate, $baseCurrencyCode): ?array {
                return $this->mapRow(
                    type: 'ap',
                    id: $apInvoice->id,
                    documentNumber: $apInvoice->ap_invoice_number,
                    partyName: $apInvoice->supplier?->name,
                    currencyCode: $apInvoice->currency_code,
                    outstandingForeign: (float) $apInvoice->total_amount - (float) ($apInvoice->amount_paid ?? 0),
                    carryingRate: (float) ($apInvoice->carrying_exchange_rate ?? 1),
                    revaluationDate: $revaluationDate,
                    baseCurrencyCode: $baseCurrencyCode,
                );
            })
            ->filter()
            ->values();

        $total = $arRows->sum('difference') - $apRows->sum(fn (array $row): float => (float) $row['difference']);

        return [
            'ar' => $arRows->all(),
            'ap' => $apRows->all(),
            'total' => round((float) $total, 2),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapRow(
        string $type,
        int $id,
        string $documentNumber,
        ?string $partyName,
        string $currencyCode,
        float $outstandingForeign,
        float $carryingRate,
        string $revaluationDate,
        string $baseCurrencyCode,
    ): ?array {
        if ($outstandingForeign <= 0) {
            return null;
        }

        $newRate = ExchangeRate::rateFor($currencyCode, $baseCurrencyCode, $revaluationDate);
        $oldBaseValue = round($outstandingForeign * $carryingRate, 2);
        $newBaseValue = round($outstandingForeign * $newRate, 2);
        $difference = round($newBaseValue - $oldBaseValue, 2);

        return [
            'type' => $type,
            'id' => $id,
            'document_number' => $documentNumber,
            'party_name' => $partyName,
            'currency_code' => $currencyCode,
            'outstanding_foreign' => (string) $outstandingForeign,
            'carrying_rate' => (string) $carryingRate,
            'new_rate' => $newRate,
            'old_base_value' => (string) $oldBaseValue,
            'new_base_value' => (string) $newBaseValue,
            'difference' => (string) $difference,
        ];
    }
}
