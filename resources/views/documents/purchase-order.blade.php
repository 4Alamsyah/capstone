<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Order {{ $purchaseOrder->po_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 11px;
            line-height: 1.35;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .page {
            width: 100%;
        }

        .box {
            border: 1px solid #111;
            padding: 6px 8px;
        }

        .top-row {
            width: 100%;
            table-layout: fixed;
            margin-bottom: 6px;
        }

        .top-row > tbody > tr > td {
            width: 50%;
            vertical-align: top;
        }

        .top-row > tbody > tr > td:first-child {
            padding-right: 4px;
        }

        .top-row > tbody > tr > td:last-child {
            padding-left: 4px;
        }

        .box-title {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .mid-row {
            width: 100%;
            table-layout: fixed;
            margin-bottom: 6px;
        }

        .mid-row > tbody > tr > td {
            vertical-align: top;
        }

        .seller-col {
            width: 40%;
            padding-right: 4px;
        }

        .brand-col {
            width: 30%;
            padding: 0 4px;
            text-align: center;
        }

        .brand-logo {
            display: block;
            width: 170px;
            height: auto;
            margin: 0 auto;
        }

        .brand-name {
            font-weight: 700;
            font-size: 13px;
            color: #1a5e2a;
            margin-top: 2px;
        }

        .brand-tagline {
            font-size: 8px;
            color: #1a5e2a;
            margin-bottom: 6px;
        }

        .doc-title {
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        .doc-number {
            font-size: 13px;
            margin-top: 2px;
        }

        .meta-col {
            width: 30%;
            padding-left: 4px;
        }

        .meta-table {
            width: 100%;
            table-layout: fixed;
        }

        .meta-table td {
            border: 1px solid #111;
            padding: 3px 5px;
            font-size: 10px;
        }

        .meta-table .meta-label {
            width: 52%;
            font-weight: 700;
        }

        .meta-table .meta-colon {
            width: 6%;
            text-align: center;
        }

        .notice {
            border: 1px solid #111;
            border-bottom: 0;
            padding: 4px 6px;
            font-weight: 700;
            font-size: 10px;
        }

        .items {
            width: 100%;
            table-layout: fixed;
        }

        .items th,
        .items td {
            border: 1px solid #111;
            padding: 4px 5px;
            font-size: 10px;
        }

        .items th {
            text-align: center;
            font-weight: 700;
        }

        .col-qty { width: 8%; text-align: center; }
        .col-uom { width: 8%; text-align: center; }
        .col-item-number { width: 16%; }
        .col-desc { width: 34%; }
        .col-unit-price { width: 17%; text-align: right; }
        .col-total { width: 17%; text-align: right; }

        .items tbody td {
            height: 20px;
        }

        .bottom-row {
            width: 100%;
            table-layout: fixed;
        }

        .bottom-row > tbody > tr > td {
            vertical-align: top;
        }

        .notice-col {
            width: 62%;
            padding-right: 6px;
        }

        .notice-box {
            border: 1px solid #111;
            border-top: 0;
            padding: 6px;
            font-size: 9px;
            line-height: 1.4;
        }

        .totals-col {
            width: 38%;
        }

        .totals-table {
            width: 100%;
            table-layout: fixed;
        }

        .totals-table td {
            border: 1px solid #111;
            padding: 3px 6px;
            font-size: 10px;
        }

        .totals-table .totals-label {
            width: 46%;
            font-weight: 700;
        }

        .totals-table .totals-currency {
            width: 14%;
            text-align: center;
        }

        .totals-table .totals-value {
            width: 40%;
            text-align: right;
        }

        .totals-table .grand-total td {
            font-weight: 700;
        }

        .sign-table {
            width: 100%;
            table-layout: fixed;
            margin-top: 6px;
        }

        .sign-table td {
            border: 1px solid #111;
            padding: 4px 6px;
            vertical-align: top;
        }

        .sign-header {
            font-weight: 700;
            text-align: center;
        }

        .sign-space {
            height: 46px;
        }

        .sign-name {
            text-align: center;
            font-weight: 700;
        }

        .foot-table {
            width: 100%;
            table-layout: fixed;
            margin-top: 6px;
        }

        .foot-table > tbody > tr > td {
            border: 1px solid #111;
            padding: 6px;
            vertical-align: top;
        }

        .foot-left {
            width: 50%;
        }

        .foot-right {
            width: 50%;
        }

        .foot-signature-space {
            height: 56px;
        }

        .foot-signature-label {
            text-align: right;
            font-weight: 700;
        }

        @page {
            size: A4 portrait;
            margin: 8mm;
        }
    </style>
</head>
<body>
    <div class="page">
        <table class="top-row">
            <tr>
                <td>
                    <div class="box">
                        <div class="box-title">BUYER</div>
                        <div>PT. Denki Automa Indonesia</div>
                        <div>Jl. Hidayah M126/No. 5</div>
                        <div>Wanajaya, Cibitung, Bekasi</div>
                        <div>Jawa Barat 17520</div>
                    </div>
                </td>
                <td>
                    <div class="box">
                        <div class="box-title">SHIP TO</div>
                        <div>PT. Denki Automa Indonesia</div>
                        <div>Ruko BGM</div>
                        <div>Blok A3 Jl. Kp Buwek</div>
                        <div>Sumber jaya, tambun, Bekasi</div>
                        <div>Phone : 021-8953-6996</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="mid-row">
            <tr>
                <td class="seller-col">
                    <div class="box" style="min-height: 96px;">
                        <div class="box-title">SELLER</div>
                        <div>{{ $purchaseOrder->supplier?->name ?? '-' }}</div>
                        <div>{{ $purchaseOrder->supplier?->address ?? '-' }}</div>
                        @if ($purchaseOrder->supplier?->phone)
                            <div>Phone : {{ $purchaseOrder->supplier->phone }}</div>
                        @endif
                    </div>
                </td>
                <td class="brand-col">
                    @if ($logoDataUri)
                        <img src="{{ $logoDataUri }}" alt="PT Denki Automa Indonesia" class="brand-logo">
                    @else
                        <div class="brand-name">PT DENKI AUTOMA INDONESIA</div>
                        <div class="brand-tagline">GOOD &amp; SMART YOUR SOLUTIONS</div>
                    @endif
                    <div class="doc-title">PURCHASE ORDER</div>
                    <div class="doc-number">{{ $purchaseOrder->po_number }}</div>
                </td>
                <td class="meta-col">
                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">Order No</td>
                            <td class="meta-colon">:</td>
                            <td>{{ $purchaseOrder->po_number }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Quo No</td>
                            <td class="meta-colon">:</td>
                            <td>{{ $purchaseOrder->quo_no ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Project Code</td>
                            <td class="meta-colon">:</td>
                            <td>{{ $purchaseOrder->project_code ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Date</td>
                            <td class="meta-colon">:</td>
                            <td>{{ $purchaseOrder->order_date?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Term Payment</td>
                            <td class="meta-colon">:</td>
                            <td>{{ $purchaseOrder->term_payment ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Delivery Date</td>
                            <td class="meta-colon">:</td>
                            <td>{{ $purchaseOrder->expected_date?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="notice">THE ABOVE PURCHASE ORDER NUMBER MUST BE SHOWN ON BILLS OF LADING, PACKING LISTS AND INVOICES</div>

        <table class="items">
            <thead>
                <tr>
                    <th class="col-qty">QTY</th>
                    <th class="col-uom">UOM</th>
                    <th class="col-item-number">ITEM NUMBER</th>
                    <th class="col-desc">DESCRIPTION</th>
                    <th class="col-unit-price">UNIT PRICE<br>{{ $purchaseOrder->currency_code }}</th>
                    <th class="col-total">TOTAL<br>{{ $purchaseOrder->currency_code }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $rows = $purchaseOrder->items->values();
                    $minimumRows = 6;
                    $displayRows = max($minimumRows, $rows->count());
                @endphp

                @for ($i = 0; $i < $displayRows; $i++)
                    @php $item = $rows->get($i); @endphp
                    <tr>
                        <td class="col-qty">{{ $item ? rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') : '' }}</td>
                        <td class="col-uom">{{ $item ? strtoupper((string) ($item->unit ?? 'PCS')) : '' }}</td>
                        <td class="col-item-number">{{ $item?->part?->part_number }}</td>
                        <td class="col-desc">{{ $item?->part?->name }}</td>
                        <td class="col-unit-price">{{ $item ? number_format((float) $item->unit_price, 2, '.', ',') : '' }}</td>
                        <td class="col-total">{{ $item ? number_format((float) $item->line_total, 2, '.', ',') : '' }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <table class="bottom-row">
            <tr>
                <td class="notice-col">
                    <div class="notice-box">
                        <strong>ACCEPTANCE MUST BE SIGNED AND RETURNED IMMEDIATELY</strong>
                        <p style="margin: 4px 0 0;">
                            Acceptance in any other manner of this order is expressly limited to the terms and conditions
                            on the face and reverse side hereof and any different or additional terms provided by setter
                            are hereby objected to and shall not be binding on buyer notwithstanding the delivery of
                            goods to buyer, of the retention of or payment for such goods.
                        </p>
                        <p style="margin: 4px 0 0;">This order is recoverable at any time prior to acceptance.</p>
                    </div>
                </td>
                <td class="totals-col">
                    <table class="totals-table">
                        <tr>
                            <td class="totals-label">Total</td>
                            <td class="totals-currency">{{ $purchaseOrder->currency_code }}</td>
                            <td class="totals-value">{{ number_format((float) $purchaseOrder->subtotal, 2, '.', ',') }}</td>
                        </tr>
                        <tr>
                            <td class="totals-label">Discount</td>
                            <td class="totals-currency">{{ $purchaseOrder->currency_code }}</td>
                            <td class="totals-value">{{ number_format((float) $purchaseOrder->discount, 2, '.', ',') }}</td>
                        </tr>
                        <tr>
                            <td class="totals-label">Total After Discount</td>
                            <td class="totals-currency">{{ $purchaseOrder->currency_code }}</td>
                            <td class="totals-value">{{ number_format((float) $purchaseOrder->subtotal - (float) $purchaseOrder->discount, 2, '.', ',') }}</td>
                        </tr>
                        <tr>
                            <td class="totals-label">Tax</td>
                            <td class="totals-currency">{{ $purchaseOrder->currency_code }}</td>
                            <td class="totals-value">{{ number_format((float) $purchaseOrder->tax_amount, 2, '.', ',') }}</td>
                        </tr>
                        <tr class="grand-total">
                            <td class="totals-label">Grand Total</td>
                            <td class="totals-currency">{{ $purchaseOrder->currency_code }}</td>
                            <td class="totals-value">{{ number_format($purchaseOrder->grand_total, 2, '.', ',') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="sign-table">
            <tr>
                <td style="width: 50%;">
                    <div class="sign-header">Prepare By</div>
                </td>
                <td style="width: 50%;">
                    <div class="sign-header">Approved By</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="sign-space"></div>
                    <div class="sign-name">Efna N</div>
                </td>
                <td>
                    <div class="sign-space"></div>
                    <div class="sign-name">Murthado</div>
                </td>
            </tr>
        </table>

        <table class="foot-table">
            <tr>
                <td class="foot-left">
                    <div><strong>Departement</strong> &nbsp; {{ $purchaseOrder->department ?: '-' }}</div>
                    <div style="margin-top: 16px;"><strong>Comment</strong></div>
                    <div>{{ $purchaseOrder->notes ?: '-' }}</div>
                </td>
                <td class="foot-right">
                    <div><strong>Accepted</strong></div>
                    <div class="foot-signature-space"></div>
                    <div class="foot-signature-label">Seller Authorized Signature</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
