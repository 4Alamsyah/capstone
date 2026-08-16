<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BalanceSheetController extends Controller
{
    public function index(Request $request): Response
    {
        $asOfDate = $request->string('as_of_date')->trim()->toString();
        $asOfDate = $asOfDate !== '' ? $asOfDate : now()->format('Y-m-d');

        $balances = $this->accountBalances([
            ChartOfAccount::TYPE_ASSET,
            ChartOfAccount::TYPE_LIABILITY,
            ChartOfAccount::TYPE_EQUITY,
            ChartOfAccount::TYPE_REVENUE,
            ChartOfAccount::TYPE_EXPENSE,
        ], $asOfDate);

        $assets = $balances->get(ChartOfAccount::TYPE_ASSET, collect());
        $liabilities = $balances->get(ChartOfAccount::TYPE_LIABILITY, collect());
        $equityAccounts = $balances->get(ChartOfAccount::TYPE_EQUITY, collect());
        $revenue = $balances->get(ChartOfAccount::TYPE_REVENUE, collect());
        $expense = $balances->get(ChartOfAccount::TYPE_EXPENSE, collect());

        $totalAssets = (float) $assets->sum('balance');
        $totalLiabilities = (float) $liabilities->sum('balance');
        $totalEquityAccounts = (float) $equityAccounts->sum('balance');
        $currentYearEarnings = (float) $revenue->sum('balance') - (float) $expense->sum('balance');
        $totalEquity = $totalEquityAccounts + $currentYearEarnings;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;

        return Inertia::render('accounting/BalanceSheet', [
            'asOfDate' => $asOfDate,
            'assets' => $assets->values(),
            'liabilities' => $liabilities->values(),
            'equity' => $equityAccounts->values(),
            'currentYearEarnings' => (string) $currentYearEarnings,
            'totals' => [
                'total_assets' => (string) $totalAssets,
                'total_liabilities' => (string) $totalLiabilities,
                'total_equity' => (string) $totalEquity,
                'total_liabilities_and_equity' => (string) $totalLiabilitiesAndEquity,
                'is_balanced' => abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01,
            ],
            'unclassifiedCount' => ChartOfAccount::query()->whereNull('account_type')->count(),
        ]);
    }

    /**
     * Compute each account's balance (as of the given date, posted entries
     * only), grouped by account_type. Asset/Expense accounts are
     * debit-normal (balance = debit - credit); Liability/Equity/Revenue
     * accounts are credit-normal (balance = credit - debit).
     *
     * @param  array<int, string>  $types
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function accountBalances(array $types, string $asOfDate): Collection
    {
        $asOf = Carbon::parse($asOfDate)->endOfDay();

        $accounts = ChartOfAccount::query()
            ->whereIn('account_type', $types)
            ->withSum(['journalLines as debit_total' => function ($query) use ($asOf): void {
                $query->where('line_type', 'debit')
                    ->whereHas('journalEntry', function ($entryQuery) use ($asOf): void {
                        $entryQuery->where('status', 'posted')->where('entry_date', '<=', $asOf);
                    });
            }], 'amount')
            ->withSum(['journalLines as credit_total' => function ($query) use ($asOf): void {
                $query->where('line_type', 'credit')
                    ->whereHas('journalEntry', function ($entryQuery) use ($asOf): void {
                        $entryQuery->where('status', 'posted')->where('entry_date', '<=', $asOf);
                    });
            }], 'amount')
            ->orderBy('code')
            ->get();

        return $accounts
            ->map(function (ChartOfAccount $account): array {
                $debitTotal = (float) ($account->debit_total ?? 0);
                $creditTotal = (float) ($account->credit_total ?? 0);
                $balance = $account->isDebitNormal() ? $debitTotal - $creditTotal : $creditTotal - $debitTotal;

                return [
                    'account_type' => $account->account_type,
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $balance,
                ];
            })
            ->groupBy('account_type')
            ->map(fn ($rows) => $rows->map(fn (array $row): array => [
                'id' => $row['id'],
                'code' => $row['code'],
                'name' => $row['name'],
                'balance' => (string) $row['balance'],
            ]));
    }
}
