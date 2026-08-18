<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family: Arial, Helvetica, sans-serif; color:#111;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:6px; overflow:hidden;">
                    <tr>
                        <td style="background:#6d2ea0; color:#fff; padding:16px 24px; font-size:18px; font-weight:bold;">
                            PT. Denki Automa Indonesia
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 16px;">Yth. {{ $invoice->customer?->name ?? 'Bapak/Ibu' }},</p>

                            <p style="margin:0 0 16px;">
                                Bersama email ini kami lampirkan invoice untuk pesanan Anda. Mohon periksa detail berikut:
                            </p>

                            <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin-bottom:16px;">
                                <tr>
                                    <td style="border:1px solid #e5e5e5; font-weight:bold; width:45%;">Invoice Number</td>
                                    <td style="border:1px solid #e5e5e5;">{{ $invoice->invoice_number }}</td>
                                </tr>
                                <tr>
                                    <td style="border:1px solid #e5e5e5; font-weight:bold;">Invoice Date</td>
                                    <td style="border:1px solid #e5e5e5;">{{ $invoice->invoice_date?->format('d/m/Y') ?? '-' }}</td>
                                </tr>
                                @if ($invoice->due_date)
                                <tr>
                                    <td style="border:1px solid #e5e5e5; font-weight:bold;">Due Date</td>
                                    <td style="border:1px solid #e5e5e5;">{{ $invoice->due_date?->format('d/m/Y') }}</td>
                                </tr>
                                @endif
                                @if ($invoice->customerOrder?->co_number)
                                <tr>
                                    <td style="border:1px solid #e5e5e5; font-weight:bold;">Order No.</td>
                                    <td style="border:1px solid #e5e5e5;">{{ $invoice->customerOrder->co_number }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="border:1px solid #e5e5e5; font-weight:bold;">Total Amount</td>
                                    <td style="border:1px solid #e5e5e5; font-weight:bold;">{{ $invoice->currency_code }} {{ number_format((float) $invoice->total_amount, 2, '.', ',') }}</td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px;">
                                File PDF invoice terlampir pada email ini. Mohon lakukan pembayaran sesuai dengan terms yang berlaku.
                            </p>

                            <p style="margin:0;">Terima kasih atas kepercayaan Anda.</p>
                            <p style="margin:16px 0 0;">Hormat kami,<br><strong>PT. Denki Automa Indonesia</strong></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
