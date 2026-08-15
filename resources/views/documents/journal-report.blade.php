<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Journal Report {{ now()->format('Y-m-d') }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Times New Roman', Times, serif;
            color: #111;
            font-size: 12px;
            line-height: 1.25;
        }

        .page {
            width: 100%;
        }

        .bar {
            background: #6d2ea0;
            color: #fff;
            padding: 3px 6px;
            font-weight: 700;
        }

        .title {
            margin: 10px 0 4px;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
        }

        .filters-summary {
            margin: 0 0 10px;
            font-size: 11px;
            text-align: center;
            color: #333;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items th,
        .items td {
            border: 1px solid #666;
            padding: 4px 5px;
        }

        .items th {
            background: #6d2ea0;
            color: #fff;
            font-size: 12px;
            text-align: center;
        }

        .col-date { width: 12%; }
        .col-entry { width: 14%; }
        .col-account { width: 24%; }
        .col-desc { width: 30%; }
        .col-amount { width: 10%; text-align: right; }

        .entry-header td {
            background: #f0eaf7;
            font-weight: 700;
            padding: 4px 6px;
        }

        .amount {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .empty-row td {
            text-align: center;
            padding: 16px;
            color: #666;
        }

        tfoot td {
            font-weight: 700;
            border-top: 2px solid #666;
        }

        .toolbar {
            text-align: right;
            margin: 0 0 8px;
        }

        .btn {
            border: 1px solid #666;
            background: #fff;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        @media print {
            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">Print Report</button>
    </div>

    <div class="page">
        <div class="bar">PT. Denki Automa Indonesia</div>

        <div class="title">LAPORAN JURNAL UMUM</div>
        <div class="filters-summary">
            Tanggal: {{ $filters['date_from'] ?? '-' }} s/d {{ $filters['date_to'] ?? '-' }}
            &middot; Periode: {{ $fiscalPeriodLabel ?? 'Semua' }}
            &middot; Status: {{ $filters['status'] ? ucfirst($filters['status']) : 'Semua' }}
            @if(!empty($filters['search']))
                &middot; Cari: "{{ $filters['search'] }}"
            @endif
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th class="col-date">Date</th>
                    <th class="col-entry">Entry No.</th>
                    <th class="col-account">Account</th>
                    <th class="col-desc">Description</th>
                    <th class="col-amount">Debit</th>
                    <th class="col-amount">Credit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr class="entry-header">
                        <td colspan="6">
                            {{ $entry['entry_date'] }} &middot; {{ $entry['entry_number'] }}
                            @if($entry['fiscal_period_code']) &middot; {{ $entry['fiscal_period_code'] }} @endif
                            &middot; {{ ucfirst($entry['status']) }}
                            @if($entry['description']) &mdash; {{ $entry['description'] }} @endif
                        </td>
                    </tr>
                    @foreach ($entry['lines'] as $line)
                        <tr>
                            <td></td>
                            <td></td>
                            <td>{{ $line['chart_of_account_code'] }} - {{ $line['chart_of_account_name'] }}</td>
                            <td>{{ $line['description'] ?? '-' }}</td>
                            <td class="amount">{{ $line['line_type'] === 'debit' ? number_format($line['amount'], 2, '.', ',') : '' }}</td>
                            <td class="amount">{{ $line['line_type'] === 'credit' ? number_format($line['amount'], 2, '.', ',') : '' }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr class="empty-row">
                        <td colspan="6">Tidak ada data jurnal untuk filter ini.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right;">Grand Total</td>
                    <td class="amount">{{ number_format($totals['debit_total'], 2, '.', ',') }}</td>
                    <td class="amount">{{ number_format($totals['credit_total'], 2, '.', ',') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
