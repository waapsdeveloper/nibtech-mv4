<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Partial Refund Invoice</title>
</head>
<body>
    <p>Dear Customer,</p>

    <p>Please find the attached partial refund invoice for your recent return or adjustment. This refund covers the selected items from your order.</p>

    @if (!empty($partialRefundAmount))
        <p><strong>Refund Amount: {{ $partialRefundAmount }} {{ $order->currency_id->code ?? 'GBP' }}</strong></p>
    @endif

    <p>Let us know if you need any additional information.</p>

    <p>Best regards,<br>{{ env('APP_NAME') }}</p>

    <p><strong>Note: This is an automated message, please do not reply to this email.</strong></p>
</body>
</html>
