<!-- resources/views/invoices/invoice.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Invoice</title>

    <style>

        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            font-size: 14px;
            /* line-height: 10px; */
        }

        .invoice-container {
            max-width: 800px;
            /* margin: 20px auto; */
            /* padding: 20px; */
            /* box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); */
        }
        .pull-right {
            float: right;
        }
        .pull-left {
            float: left;
        }
        .clearfix {
            clear: both;
        }
        .wd-30 {
            width: 30px;
        }
        .wd-120 {
            width: 120px;
        }
        .company-info, .invoice-header, .customer-details, .order-details, .order-items, .total-amount {
            /* margin-bottom: 20px; */
        }

        .invoice-header h2, .customer-details h3, .order-details h3, .total-amount h3 {
            /* border-bottom: 2px solid #333; */
            /* padding-bottom: 5px;
            margin-bottom: 10px; */
        }

        .order-items table {
            /* width: 100%;
            border-collapse: collapse;
            margin-top: 20px; */
        }


        .total-amount {
            /* text-align: right; */
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
    {{-- <iframe src="{{ $order->delivery_note_url }}"></iframe> --}}

    <div class="invoice-container">


        <table border="0">
            <tr style="text-align: right; padding:0; margin:0;">
                <td style="text-align: left; padding:0; margin:0; line-height:10px">

                    {{-- <div class="company-info"> --}}
                        <br><br>
                        <br><br>
                        <img src="{{ public_path('assets/img/brand').'/'.env('APP_LOGO') }}" alt="" height="40">
                    {{-- </div> --}}
                        {{-- <br> --}}
                        <br>
                        <h4><strong>{{ env('APP_NAME') }}</strong></h4>
                        <h5>Cromac Square,</h5>
                        <h5>Forsyth House,</h5>
                        <h5>Belfast, BT2 8LA</h5>
                        <h5>invoice@nibritaintech.com</h5>
                </td>
                <td width="210"></td>
                <td width="140" style="text-align: right; padding:0; margin:0; line-height:10px">
                    <br>
                    <br>
                    <br>
                    <br>
                    <br>
                    <h1 style="line-height:14px; margin:0px; text-align:right;">Statement</h1>

                    <h4 style="line-height:11px; margin: 0; padding: 0;">{{ $customer->company }}</h4>
                    <h5 style="margin: 0; padding: 0;">{{ $customer->email }}</h5>
                    <h5 style="margin: 0; padding: 0;">{{ $customer->street }} {{ $customer->street2 }}, {{ $customer->city }}</h5>
                    <h5 style="margin: 0; padding: 0;">{{ $customer->postal_code }} {{ $customer->country_id->title ?? null }}</h5>
                    <h5 style="margin: 0; padding: 0;">{{ $customer->vat }}</h5>

                </td>

            </tr>
            <br>
        </table>
            <br>
            <br>

        <table border="0">
            <hr style="border-top: 0px solid Black">
            <tr style="line-height: 24px;">
                <td colspan="4" align="center">
                    <i style="">All Invoices
                    @if (request('start_date') != null)
                        from {{ $start_date }}
                    @endif
                    @if (request('end_date') != null)
                        to {{ $end_date }}
                    @endif
                    </i>
                </td>
            </tr>
            <tr style="line-height: 20px;">
                <th style="border-bottom: 1px solid #09F; border-top: 1px solid #09F;" width="80">Date</th>
                <th style="border-bottom: 1px solid #09F; border-top: 1px solid #09F;" width="277">Details</th>
                <th style="border-bottom: 1px solid #09F; border-top: 1px solid #09F;" width="80" align="right">Amount</th>
                <th style="border-bottom: 1px solid #09F; border-top: 1px solid #09F;" width="100" align="right">Balance</th>
            </tr>
            @if ($balance_bf > 0)

            <tr>
                <td style="border-bottom: 1px solid #ccc;"></td>
                <td style="border-bottom: 1px solid #ccc;">Previous</td>
                <td style="border-bottom: 1px solid #ccc;" align="right">€{{ amount_formatter($balance_bf,2) }}</td>
                <td style="border-bottom: 1px solid #ccc;" align="right">€{{ amount_formatter($balance_bf,2) }}</td>

            </tr>
            @endif
            @php
                $total = $balance_bf;
            @endphp
            @foreach ($transactions as $transaction)
                @php
                    if($transaction->transaction_type_id == 2){
                        $amount = $transaction->amount * -1;
                    }else{
                        $amount = $transaction->amount;
                    }
                    $total += $amount;
                @endphp
                <tr style="line-height: 18px;">
                    <td style="border-bottom: 1px solid #ccc;">{{ date('d-m-Y', strtotime($transaction->date)) }}</td>
                    <td style="border-bottom: 1px solid #ccc;">{{ $transaction->description }}</td>
                    <td style="border-bottom: 1px solid #ccc;" align="right">{{ $transaction->currency_id->sign.amount_formatter($amount,2) }}</td>
                    <td style="border-bottom: 1px solid #ccc;" align="right">{{ $transaction->currency_id->sign.amount_formatter($total,2) }}</td>
                </tr>
            @endforeach

        </table>
        <br>
        <br>
    </div>
</body>
</html>
