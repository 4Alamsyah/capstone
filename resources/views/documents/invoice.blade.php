<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_number }}</title>
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
            font-size: 13px;
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

        .header {
            width: 100%;
            border: 1px solid #666;
            border-top: 0;
            border-bottom: 0;
            padding: 6px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header-table td {
            vertical-align: top;
            padding: 0;
        }

        .left-col {
            width: 58%;
            padding-right: 12px;
        }

        .right-col {
            width: 42%;
            text-align: right;
        }

        .company-name {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 3px;
        }

        .small {
            font-size: 11px;
        }

        .block-title {
            margin-top: 8px;
            font-weight: 700;
        }

        .meta {
            margin-top: 10px;
        }

        .meta-row {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .meta-row td {
            padding: 0;
            vertical-align: top;
        }

        .meta-label {
            width: 58%;
            font-weight: 700;
        }

        .meta-value {
            width: 42%;
            text-align: right;
            font-weight: 700;
        }

        .tax-rate {
            margin: 8px 0;
            border: 1px solid #666;
            width: 280px;
            border-collapse: collapse;
        }

        .tax-rate td {
            padding: 3px 6px;
            border-right: 1px solid #666;
        }

        .tax-rate td:last-child {
            border-right: 0;
            text-align: center;
            font-weight: 700;
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
            font-size: 14px;
            text-align: center;
        }

        .col-qty { width: 14%; text-align: center; }
        .col-desc { width: 53%; }
        .col-unit { width: 17%; text-align: right; }
        .col-amount { width: 16%; text-align: right; }

        .summary-wrap {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .summary-wrap td {
            vertical-align: top;
            padding: 0;
        }

        .summary-left {
            width: 67%;
            border-left: 1px solid #666;
            border-bottom: 1px solid #666;
            border-right: 1px solid #666;
            padding: 8px 6px;
            min-height: 190px;
        }

        .summary-right {
            width: 33%;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .summary-table td {
            border: 1px solid #666;
            padding: 4px 6px;
            font-weight: 700;
        }

        .summary-table .label { width: 55%; }
        .summary-table .currency { width: 10%; text-align: center; }
        .summary-table .value { width: 35%; text-align: right; }

        .row-title {
            font-weight: 700;
            margin: 8px 0 3px;
        }

        .amount-words {
            border: 1px solid #666;
            padding: 3px 6px;
            font-style: italic;
            font-weight: 700;
            min-height: 24px;
        }

        .footer-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .footer-table td {
            vertical-align: top;
        }

        .account-box {
            width: 72%;
            border: 1px solid #666;
            border-collapse: collapse;
        }

        .account-box td {
            padding: 3px 6px;
            font-style: italic;
            font-weight: 700;
        }

        .regards {
            text-align: left;
            padding-left: 12px;
        }

        .signature-space {
            height: 76px;
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

        .thanks {
            margin-top: 8px;
            background: #6d2ea0;
            color: #fff;
            font-style: italic;
            font-weight: 700;
            padding: 3px 6px;
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
        <button class="btn" onclick="window.print()">Print Invoice</button>
    </div>

    <div class="page">
        <div class="bar">PT. Denki Automa Indonesia</div>

        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="left-col">
                        <div class="small"><strong>Jl. Hydayah M126 No. 5</strong></div>
                        <div class="small"><strong>Wanajaya, Cibitung, Bekasi 17520</strong></div>

                        <div class="block-title">SOLD TO:</div>
                        <div><strong>{{ $invoice->customer?->name ?? '-' }}</strong></div>
                        <div>{{ $invoice->customer?->address ?? '-' }}</div>
                        <div>{{ $invoice->customer?->shipping_address ?? '-' }}</div>
                    </td>
                    <td class="right-col">
                        <div><strong>PROJECT CODE</strong> {{ $invoice->customerOrder?->project_code ?? '-' }}</div>

                        <div class="meta">
                            <table class="meta-row">
                                <tr>
                                    <td class="meta-label">INVOICE NUMBER</td>
                                    <td class="meta-value">{{ $invoice->invoice_number }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">INVOICE DATE</td>
                                    <td class="meta-value">{{ $invoice->invoice_date?->format('d/m/Y') ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">YOUR ORDER NO.</td>
                                    <td class="meta-value">{{ $invoice->customerOrder?->co_number ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">TERMS</td>
                                    <td class="meta-value">{{ $invoice->customer?->payment_terms ?? 'Bank Transfer' }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="block-title">SHIPPED TO:</div>
            <div><strong>{{ $invoice->customer?->name ?? '-' }}</strong></div>
        </div>

        <table class="tax-rate">
            <tr>
                <td><strong>Tax Rate:</strong></td>
                <td>{{ $invoice->subtotal > 0 ? number_format((((float) $invoice->tax_amount / (float) $invoice->subtotal) * 100), 2) : '0.00' }}%</td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th class="col-qty">QUANTITY</th>
                    <th class="col-desc">DESCRIPTION</th>
                    <th class="col-unit">UNIT PRICE</th>
                    <th class="col-amount">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $rows = $invoice->items->values();
                    $minimumRows = 8;
                    $displayRows = max($minimumRows, $rows->count());
                @endphp

                @for ($i = 0; $i < $displayRows; $i++)
                    @php $item = $rows->get($i); @endphp
                    <tr>
                        <td class="col-qty">{{ $item ? rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') : '' }}</td>
                        <td class="col-desc">{{ $item ? ($item->description ?: trim(($item->part?->part_number ?? '-') . ' - ' . ($item->part?->name ?? '-'))) : '' }}</td>
                        <td class="col-unit">{{ $item ? number_format((float) $item->unit_price, 2, '.', ',') : '' }}</td>
                        <td class="col-amount">{{ $item ? number_format((float) $item->line_total, 2, '.', ',') : '' }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <table class="summary-wrap">
            <tr>
                <td class="summary-left">
                    <div><strong>Counted</strong></div>
                </td>
                <td class="summary-right">
                    <table class="summary-table">
                        <tr>
                            <td class="label">SUBTOTAL</td>
                            <td class="currency">{{ $invoice->currency_code }}</td>
                            <td class="value">{{ number_format((float) $invoice->subtotal, 2, '.', ',') }}</td>
                        </tr>
                        <tr>
                            <td class="label">TAX</td>
                            <td class="currency">{{ $invoice->currency_code }}</td>
                            <td class="value">{{ number_format((float) $invoice->tax_amount, 2, '.', ',') }}</td>
                        </tr>
                        <tr>
                            <td class="label">TOTAL</td>
                            <td class="currency">{{ $invoice->currency_code }}</td>
                            <td class="value">{{ number_format((float) $invoice->total_amount, 2, '.', ',') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="row-title">Amount In Words</div>
        <div class="amount-words">{{ trim($invoice->notes ?: '-') }}</div>

        <table class="footer-table">
            <tr>
                <td style="width: 72%;">
                    <table class="account-box">
                        <tr>
                            <td style="width: 28%;">Account No.</td>
                            <td>: 156-00-2035532-9</td>
                        </tr>
                        <tr>
                            <td>Bank</td>
                            <td>: Bank Mandiri KCP Tambun City</td>
                        </tr>
                    </table>
                </td>
                <td class="regards" style="width: 28%;">
                    <div><strong>Best Regards</strong></div>
                    <div class="signature-space"></div>
                    <div><strong>PT. Denki Automa Indonesia</strong></div>
                </td>
            </tr>
        </table>

        <div class="thanks">THANK YOU FOR YOUR BUSINESS!</div>
    </div>
</body>
</html>
