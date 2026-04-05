<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery Order {{ $documentNumber }}</title>
    <style>
        :root {
            --blue: #254a87;
            --border: #222;
            --muted: #f1f4f8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background: #fff;
        }

        .page {
            width: 100%;
            min-height: auto;
            margin: 0;
            background: #fff;
            border: 0;
            padding: 0;
        }

        .header {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-bottom: 2px solid #111;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }

        .company {
            display: table-cell;
            width: 56%;
            vertical-align: top;
            padding-left: 70px;
            position: relative;
            min-height: 66px;
        }

        .logo-box {
            width: 64px;
            height: 64px;
            border: 1px solid #aaa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: #335;
            position: absolute;
            left: 0;
            top: 0;
        }

        .company h2 {
            margin: 0;
            font-size: 16px;
            line-height: 1.1;
        }

        .company p {
            margin: 2px 0;
            font-size: 11px;
            line-height: 1.25;
        }

        .doc-title {
            display: table-cell;
            width: 44%;
            vertical-align: top;
            text-align: right;
        }

        .doc-title h1 {
            margin: 0;
            letter-spacing: 1px;
            font-size: 24px;
            line-height: 1.1;
            white-space: nowrap;
        }

        .doc-number {
            margin-top: 14px;
            font-size: 13px;
            font-weight: 700;
        }

        .meta {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 10px;
        }

        .to-box {
            display: table-cell;
            width: 50%;
            border: 1px solid var(--border);
            min-height: 116px;
            padding: 8px;
            vertical-align: top;
        }

        .to-box h3,
        .right-meta h3 {
            margin: 0 0 8px;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .to-box p {
            margin: 0;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-line;
        }

        .right-meta {
            display: table-cell;
            width: 50%;
            border: 1px solid var(--border);
            padding: 8px;
            vertical-align: top;
        }

        .meta-row {
            display: grid;
            grid-template-columns: 110px 10px 1fr;
            gap: 6px;
            font-size: 12px;
            margin-bottom: 6px;
            align-items: center;
        }

        .meta-value {
            border-bottom: 1px solid #222;
            min-height: 20px;
            display: flex;
            align-items: center;
            padding-left: 2px;
        }

        .check-row {
            margin-top: 8px;
            font-size: 13px;
            font-weight: 700;
        }

        .check-row span {
            display: inline-block;
            margin-right: 16px;
            margin-top: 8px;
        }

        .check-box {
            width: 16px;
            height: 16px;
            border: 1px solid #111;
            display: inline-block;
            margin-right: 6px;
            vertical-align: middle;
        }

        .lead-text {
            font-size: 14px;
            margin: 10px 0 8px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .items th {
            background: var(--blue);
            color: #fff;
            border: 1px solid #111;
            font-size: 11px;
            padding: 5px;
            text-align: center;
        }

        .items td {
            border: 1px solid #111;
            font-size: 11px;
            padding: 4px 5px;
            height: 24px;
        }

        .items tbody tr:nth-child(even) {
            background: var(--muted);
        }

        .items .col-no {
            width: 48px;
            text-align: center;
        }

        .items .col-desc {
            width: auto;
        }

        .items .col-qty {
            width: 80px;
            text-align: right;
        }

        .items .col-unit {
            width: 72px;
            text-align: center;
        }

        .items .col-remark {
            width: 140px;
        }

        .notes {
            border: 1px solid #111;
            border-top: 0;
            padding: 6px 8px;
            font-size: 12px;
            line-height: 1.45;
        }

        .notes h4 {
            margin: 0 0 4px;
            font-size: 13px;
        }

        .notes ul {
            margin: 0;
            padding-left: 16px;
        }

        .signature {
            margin-top: 10px;
            border: 1px solid #111;
        }

        .signature th {
            background: var(--blue);
            color: #fff;
            border-right: 1px solid #111;
            font-size: 13px;
            padding: 8px;
        }

        .signature th:last-child {
            border-right: 0;
        }

        .signature td {
            border-right: 1px solid #111;
            height: 90px;
        }

        .signature td:last-child {
            border-right: 0;
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                margin: 0;
                border: 0;
                width: 100%;
                min-height: auto;
                padding: 0;
            }

            @page {
                size: A4 portrait;
                margin: 7mm;
            }
        }

        @page {
            size: A4 portrait;
            margin: 7mm;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="company">
                <div class="logo-box">LOGO</div>
                <div>
                    <h2>PT.DENKI AUTOMA INDONESIA</h2>
                    <p>Office: Ruko GBM Blok A3 Jl. Buwek Raya, Tambun Selatan Bekasi 17510</p>
                    <p>Telp: 021-8953-6996</p>
                    <p>Email: info@denki-automa.co.id</p>
                </div>
            </div>
            <div class="doc-title">
                <h1>DELIVERY ORDER</h1>
                <div class="doc-number">NO : {{ $documentNumber }}</div>
            </div>
        </div>

        <div class="meta">
            <div class="to-box">
                <h3>DELIVERY TO :</h3>
                <p>{{ $order->customer?->name }}
{{ $order->shipping_address ?: ($order->customer?->shipping_address ?? '-') }}</p>
            </div>
            <div class="right-meta">
                <div class="meta-row">
                    <div>DATE</div>
                    <div>:</div>
                    <div class="meta-value">{{ $order->delivery_date?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
                </div>
                <div class="meta-row">
                    <div>PO NO</div>
                    <div>:</div>
                    <div class="meta-value">{{ $order->po_number ?: $order->co_number }}</div>
                </div>
                <div class="meta-row">
                    <div>PROJECT CODE</div>
                    <div>:</div>
                    <div class="meta-value">{{ $order->project_code ?: '-' }}</div>
                </div>
                <div class="check-row">
                    <span><span class="check-box">{{ $order->delivery_type === 'equipment' ? 'X' : '' }}</span>EQUIPMENT</span>
                    <span><span class="check-box">{{ $order->delivery_type === 'material' ? 'X' : '' }}</span>MATERIAL</span>
                </div>
            </div>
        </div>

        <div class="lead-text">
            To the person in charge, we will send the goods according to the order you requested.
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th class="col-no">NO</th>
                    <th class="col-desc">DESCRIPTION</th>
                    <th class="col-qty">QTY</th>
                    <th class="col-unit">UNIT</th>
                    <th class="col-remark">REMARKS</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $rows = $order->items->values();
                    $minimumRows = 10;
                    $displayRows = max($minimumRows, $rows->count());
                @endphp

                @for ($i = 0; $i < $displayRows; $i++)
                    @php $item = $rows->get($i); @endphp
                    <tr>
                        <td class="col-no">{{ $item ? $i + 1 : '' }}</td>
                        <td class="col-desc">
                            @if ($item)
                                {{ trim(($item->part?->part_number ?? '-') . ' - ' . ($item->part?->name ?? '-')) }}
                            @endif
                        </td>
                        <td class="col-qty">{{ $item ? rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') : '' }}</td>
                        <td class="col-unit">{{ $item ? strtoupper((string) ($item->unit ?? 'PCS')) : '' }}</td>
                        <td class="col-remark">{{ $item?->remarks }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="notes">
            <h4>NOTE :</h4>
            <ul>
                <li>Items that have been shipped more than 1 week cannot be returned.</li>
                <li>Should you have any enquiries concerning this delivery note please contact 021 8953 6996.</li>
            </ul>
        </div>

        <table class="signature">
            <thead>
                <tr>
                    <th>SHIPPER</th>
                    <th>DRIVER</th>
                    <th>RECEIVER</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
