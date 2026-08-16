<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProfitLossController extends Controller
{
    public function index(Request $request): Response
    {
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateFrom = $dateFrom !== '' ? $dateFrom : now()->startOfMonth()->format('Y-m-d');

        $dateTo = $request->string('date_to')->trim()->toString();
        $dateTo = $dateTo !== '' ? $dateTo : now()->format('Y-m-d');

        $balances = $this->accountBalances([
            ChartOfAccount::TYPE_REVENUE,
            ChartOfAccount::TYPE_EXPENSE,
        ], $dateFrom, $dateTo);

        $revenue = $balances->get(ChartOfAccount::TYPE_REVENUE, collect());
        $expense = $balances->get(ChartOfAccount::TYPE_EXPENSE, collect());

        $totalRevenue = (float) $revenue->sum('balance');
        $totalExpense = (float) $expense->sum('balance');
        $netIncome = $totalRevenue - $totalExpense;

        return Inertia::render('accounting/ProfitLoss', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'revenue' => $revenue->values(),
            'expense' => $expense->values(),
            'totals' => [
                'total_revenue' => (string) $totalRevenue,
                'total_expense' => (string) $totalExpense,
                'net_income' => (string) $netIncome,
            ],
            'unclassifiedCount' => ChartOfAccount::query()->whereNull('account_type')->count(),
        ]);
    }

    /**
     * Compute each Revenue/Expense account's balance within the given date
     * range (posted entries only), grouped by account_type. Revenue accounts
     * are credit-normal (balance = credit - debit); Expense accounts are
     * debit-normal (balance = debit - credit).
     *
     * @param  array<int, string>  $types
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function accountBalances(array $types, string $dateFrom, string $dateTo): Collection
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        $accounts = ChartOfAccount::query()
            ->whereIn('account_type', $types)
            ->withSum(['journalLines as debit_total' => function ($query) use ($from, $to): void {
                $query->where('line_type', 'debit')
                    ->whereHas('journalEntry', function ($entryQuery) use ($from, $to): void {
                        $entryQuery->where('status', 'posted')->whereBetween('entry_date', [$from, $to]);
                    });
            }], 'amount')
            ->withSum(['journalLines as credit_total' => function ($query) use ($from, $to): void {
                $query->where('line_type', 'credit')
                    ->whereHas('journalEntry', function ($entryQuery) use ($from, $to): void {
                        $entryQuery->where('status', 'posted')->whereBetween('entry_date', [$from, $to]);
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
