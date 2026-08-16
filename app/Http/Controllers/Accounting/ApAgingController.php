<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ApInvoice;
use Inertia\Inertia;
use Inertia\Response;

class ApAgingController extends Controller
{
    public function index(): Response
    {
        $today = now()->startOfDay();

        $apInvoices = ApInvoice::query()
            ->with('supplier:id,name')
            ->whereIn('status', [ApInvoice::STATUS_APPROVED, ApInvoice::STATUS_PARTIALLY_PAID])
            ->withSum('payments as amount_paid', 'amount')
            ->orderBy('due_date')
            ->get()
            ->map(function (ApInvoice $apInvoice) use ($today): array {
                $amountPaid = (float) ($apInvoice->amount_paid ?? 0);
                $balanceDue = (float) $apInvoice->total_amount - $amountPaid;
                $dueDate = $apInvoice->due_date ?? $apInvoice->invoice_date;
                $daysOverdue = ($dueDate && $dueDate->lt($today)) ? (int) $dueDate->diffInDays($today) : 0;

                return [
                    'id' => $apInvoice->id,
                    'ap_invoice_number' => $apInvoice->ap_invoice_number,
                    'supplier_name' => $apInvoice->supplier?->name,
                    'invoice_date' => $apInvoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $apInvoice->due_date?->format('Y-m-d'),
                    'currency_code' => $apInvoice->currency_code,
                    'total_amount' => (string) $apInvoice->total_amount,
                    'amount_paid' => (string) $amountPaid,
                    'balance_due' => (string) $balanceDue,
                    'days_overdue' => $daysOverdue,
                    'aging_bucket' => $this->agingBucket($daysOverdue),
                ];
            })
            ->values();

        $buckets = ['Not Due', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'];

        $grouped = collect($buckets)->mapWithKeys(function (string $bucket) use ($apInvoices): array {
            $rows = $apInvoices->where('aging_bucket', $bucket)->values();

            return [
                $bucket => [
                    'rows' => $rows,
                    'total_balance' => (string) $rows->sum(fn (array $row): float => (float) $row['balance_due']),
                ],
            ];
        });

        return Inertia::render('accounting/ApAging', [
            'buckets' => $grouped,
            'grandTotal' => (string) $apInvoices->sum(fn (array $row): float => (float) $row['balance_due']),
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
