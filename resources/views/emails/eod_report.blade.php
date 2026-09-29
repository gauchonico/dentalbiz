@php
    $methodLabels = ['cash' => 'Cash', 'card' => 'Card', 'mobile_money' => 'Mobile Money', 'bank_transfer' => 'Bank Transfer', 'other' => 'Other'];
    $total = array_sum($byMethod);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>End of Day Payments Report</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:8px;">
        <tr>
            <td style="padding:24px 28px; border-bottom:1px solid #e5e7eb;">
                <div style="font-size:18px; font-weight:bold;">{{ config('app.name') }}</div>
                <div style="font-size:14px; color:#6b7280;">End of Day Payments — {{ $date->format('l, M j, Y') }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; font-size:14px; line-height:1.6;">
                <div style="font-weight:bold; margin-bottom:8px;">Payments by method</div>
                <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px; border-collapse:collapse;">
                    @foreach($byMethod as $method => $amount)
                        <tr><td style="color:#6b7280;">{{ $methodLabels[$method] ?? $method }}</td><td align="right">UGX {{ number_format((float) $amount) }}</td></tr>
                    @endforeach
                    <tr><td style="border-top:1px solid #e5e7eb;"><strong>Total received</strong></td><td align="right" style="border-top:1px solid #e5e7eb;"><strong>UGX {{ number_format((float) $total) }}</strong></td></tr>
                </table>

                <div style="font-weight:bold; margin:20px 0 8px;">Cash drawer movements</div>
                <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px; border-collapse:collapse;">
                    <tr><td style="color:#6b7280;">Inflow</td><td align="right">UGX {{ number_format((float) $inflow) }}</td></tr>
                    <tr><td style="color:#6b7280;">Outflow</td><td align="right">UGX {{ number_format((float) $outflow) }}</td></tr>
                    <tr><td style="border-top:1px solid #e5e7eb;"><strong>Net</strong></td><td align="right" style="border-top:1px solid #e5e7eb;"><strong>UGX {{ number_format((float) $inflow - (float) $outflow) }}</strong></td></tr>
                </table>

                <p style="margin:24px 0 0; color:#6b7280; font-size:12px;">The full breakdown is attached as a CSV file. Sessions auto-closed at day end still need their cash counted on the Cash Drawer page.</p>
            </td>
        </tr>
    </table>
</body>
</html>
