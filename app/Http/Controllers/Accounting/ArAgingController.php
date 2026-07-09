<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Inertia\Inertia;
use Inertia\Response;

class ArAgingController extends Controller
{
    public function index(): Response
    {
        $today = now()->startOfDay();

        $invoices = Invoice::query()
            ->with('customer:id,name')
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID])
            ->withSum('payments as amount_paid', 'amount')
            ->orderBy('due_date')
            ->get()
            ->map(function (Invoice $invoice) use ($today): array {
                $amountPaid = (float) ($invoice->amount_paid ?? 0);
                $balanceDue = (float) $invoice->total_amount - $amountPaid;
                $dueDate = $invoice->due_date ?? $invoice->invoice_date;
                $daysOverdue = ($dueDate && $dueDate->lt($today)) ? (int) $dueDate->diffInDays($today) : 0;

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer?->name,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                    'currency_code' => $invoice->currency_code,
                    'total_amount' => (string) $invoice->total_amount,
                    'amount_paid' => (string) $amountPaid,
                    'balance_due' => (string) $balanceDue,
                    'days_overdue' => $daysOverdue,
                    'aging_bucket' => $this->agingBucket($daysOverdue),
                ];
            })
            ->values();

        $buckets = ['Not Due', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'];

        $grouped = collect($buckets)->mapWithKeys(function (string $bucket) use ($invoices): array {
            $rows = $invoices->where('aging_bucket', $bucket)->values();

            return [
                $bucket => [
                    'rows' => $rows,
                    'total_balance' => (string) $rows->sum(fn (array $row): float => (float) $row['balance_due']),
                ],
            ];
        });

        return Inertia::render('accounting/ArAging', [
            'buckets' => $grouped,
            'grandTotal' => (string) $invoices->sum(fn (array $row): float => (float) $row['balance_due']),
        ]);
    }

    private function agingBucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'Not Due',
            $daysOverdue <= 30 => '1-30 Days',
            $daysOverdue <= 60 => '31-60 Days',
            $daysOverdue <= 90 => '61-90 Days',
            default => '90+ Days',
        };
    }
}
