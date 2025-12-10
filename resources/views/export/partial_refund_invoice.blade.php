<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Partial Refund Invoice</title>

    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            font-size: 14px;
        }

        .invoice-container {
            max-width: 800px;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .invoice-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <table border="0">
            <tr style="text-align: center;">
                <td style="text-align: center; padding:0; margin:0; line-height:10px">
                    <img src="{{ public_path('assets/img/brand').'/'.env('APP_LOGO') }}" alt="" height="60">
                    <h2><strong>{{ env('APP_NAME') }}</strong></h2>
                    <h4>Cromac Square, Forsyth House</h4>
                    <h4>Belfast, BT2 8LA</h4>
                </td>
                <td width="150"></td>
                <td style="text-align: center; padding:0; margin:0; line-height:10px" width="225">
                    <h1 style="font-size: 32px; color: #ff9800;">PARTIAL REFUND</h1>
                    <table cellspacing="4">
                        <tr>
                            <td style="text-align: left; margin-top:5px;"><h4><strong>Order ID:</strong></h4></td>
                            <td colspan="2"><h4 style="font-weight: 400">{{ $order->reference_id }}</h4></td>
                        </tr>
                        @if ($order->admin)
                        <tr>
                            <td style="text-align: left; margin-top:5px;"><h4><strong>Sales Rep:</strong></h4></td>
                            <td colspan="2"><h4 style="font-weight: 400">{{ $order->admin->first_name }}</h4></td>
                        </tr>
                        @endif
                        <tr>
                            <td style="text-align: left; margin-top:5px;"><h4><strong>Refund Date:</strong></h4></td>
                            <td colspan="2"><h4 style="font-weight: 400">{{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}</h4></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Customer Details -->
        <table border="0" style="margin-top: 20px;">
            <tr>
                <td style="vertical-align: top;">
                    <h3><strong>Customer Information</strong></h3>
                    <p>
                        <strong>Name:</strong> {{ $customer->first_name }} {{ $customer->last_name }}<br>
                        <strong>Email:</strong> {{ $customer->email }}<br>
                        @if($customer->phone_number)
                        <strong>Phone:</strong> {{ $customer->phone_number }}<br>
                        @endif
                    </p>
                </td>
            </tr>
        </table>

        <!-- Refunded Items -->
        <div class="order-items">
            <h3>Refunded Items</h3>
            <table cellpadding="5">
                <thead border="1">
                    <tr border="1">
                        <th width="320" border="0.1">Product Name</th>
                        <th width="80" border="0.1">Price</th>
                        <th width="40" border="0.1">Qty</th>
                        <th width="90" border="0.1">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalAmount = 0;
                        $totalQty = 0;
                    @endphp
                    @foreach ($orderItems as $item)
                        @php
                            $itemTotal = $item->selling_price ?? $item->price ?? 0;
                            $totalAmount += $itemTotal;
                            $totalQty += $item->quantity ?? 1;

                            $storage = $item->variation->storage_id ? $item->variation->storage_id->name . " - " : '';
                            $color = $item->variation->color_id ? $item->variation->color_id->name . " - " : '';
                            $productName = $item->variation->product->model ?? $item->variation->product->name ?? 'Product';
                        @endphp
                        <tr>
                            <td width="320">{{ $productName . " - " . $storage . $color }}</td>
                            <td width="80" align="right">-{{ $order->currency_id->sign ?? '£' }}{{ number_format($itemTotal, 2) }}</td>
                            <td width="40">{{ $item->quantity ?? 1 }}</td>
                            <td width="90" align="right">-{{ $order->currency_id->sign ?? '£' }}{{ number_format($itemTotal, 2) }}</td>
                        </tr>
                    @endforeach
                    <hr>
                </tbody>
                <tfoot>
                    <tr style="border-top: 1px solid Black">
                        <td></td>
                        <td colspan="3">
                            <table cellpadding="5">
                                <tr>
                                    <td>Sub Total:</td>
                                    <td align="right"><strong>-{{ $order->currency_id->sign ?? '£' }}{{ number_format($totalAmount, 2) }}</strong></td>
                                </tr>
                                @if (!empty($partialRefundAmount) && $partialRefundAmount != $totalAmount)
                                    <tr>
                                        <td>Adjusted Refund Amount:</td>
                                        <td align="right"><strong>-{{ $order->currency_id->sign ?? '£' }}{{ number_format($partialRefundAmount, 2) }}</strong></td>
                                    </tr>
                                @endif
                                <hr>
                                <tr>
                                    <td><strong>Amount to be Refunded:</strong></td>
                                    <td align="right"><strong>-{{ $order->currency_id->sign ?? '£' }}{{ number_format(!empty($partialRefundAmount) ? $partialRefundAmount : $totalAmount, 2) }}</strong></td>
                                </tr>
                                @php
                                    $marketplaceLabel = optional($order->marketplace)->name;
                                    if (!$marketplaceLabel) {
                                        $marketplaceLabel = ((int) ($order->marketplace_id ?? 0) === 4) ? 'Refurbed' : 'Back Market';
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $marketplaceLabel }}:</td>
                                    <td align="right"><strong>-{{ $order->currency_id->sign ?? '£' }}{{ number_format(!empty($partialRefundAmount) ? $partialRefundAmount : $totalAmount, 2) }}</strong></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Total Amount -->
        <div class="total-amount" style="padding:0; margin:0; line-height:6px">
            <h3>Store Policy</h3>
            <hr>
            <h4>Stock Sold on Marginal VAT Scheme. VAT Number: {{ env('APP_VAT') }}</h4>
            <h4 style="color: #ff9800;">This is a PARTIAL REFUND invoice for selected items only.</h4>
        </div>
    </div>
</body>
</html>
