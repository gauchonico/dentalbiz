@php
    $methodLabels = ['cash' => 'Cash', 'card' => 'Card', 'mobile_money' => 'Mobile Money', 'bank_transfer' => 'Bank Transfer'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:8px;">
        <tr>
            <td style="padding:24px 28px; border-bottom:1px solid #e5e7eb;">
                <div style="font-size:18px; font-weight:bold;">{{ config('app.name') }}</div>
                <div style="font-size:14px; color:#6b7280;">Payment Receipt</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; font-size:14px; line-height:1.6;">
                <p style="margin:0 0 16px;">Dear {{ $invoice->patient->name ?? 'Patient' }},</p>
                <p style="margin:0 0 16px;">Thank you. We have received your payment for invoice #{{ $invoice->id }}.</p>

                <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px; border-collapse:collapse;">
                    <tr><td style="color:#6b7280;">Amount paid</td><td align="right"><strong>UGX {{ number_format((float) $payment->amount) }}</strong></td></tr>
                    <tr><td style="color:#6b7280;">Method</td><td align="right">{{ $methodLabels[$payment->method] ?? ($payment->method ?: '—') }}</td></tr>
                    <tr><td style="color:#6b7280;">Date</td><td align="right">{{ optional($payment->received_at)->timezone(config('app.timezone'))->format('M j, Y H:i') }}</td></tr>
                    @if($payment->receipt_number)
                        <tr><td style="color:#6b7280;">Receipt no.</td><td align="right">{{ $payment->receipt_number }}</td></tr>
                    @endif
                    @if($payment->reference)
                        <tr><td style="color:#6b7280;">Reference</td><td align="right">{{ $payment->reference }}</td></tr>
                    @endif
                    <tr><td colspan="2" style="border-top:1px solid #e5e7eb; padding:0;"></td></tr>
                    <tr><td style="color:#6b7280;">Invoice total</td><td align="right">UGX {{ number_format((float) $invoice->amount) }}</td></tr>
                    <tr><td style="color:#6b7280;">Balance remaining</td><td align="right"><strong>UGX {{ number_format((float) $invoice->balance) }}</strong></td></tr>
                </table>

                <p style="margin:24px 0 0; color:#6b7280; font-size:12px;">Please keep this email for your records.</p>
            </td>
        </tr>
    </table>
</body>
</html>
