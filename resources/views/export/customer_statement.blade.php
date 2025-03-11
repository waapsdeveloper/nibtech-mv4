<!-- resources/views/invoices/invoice.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Invoice</title>

    <style>
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
                        <img src="{{ public_path('assets/img/brand').'/'.env('APP_LOGO') }}" alt="" height="50">
                    {{-- </div> --}}
                        {{-- <br> --}}
                        <br>
                        <h4><strong>{{ env('APP_NAME') }}</strong></h4>
                        <h5>Cromac Square,</h5>
                        <h5>Forsyth House,</h5>
                        <h5>Belfast, BT2 8LA</h5>
                        <h5>invoice@nibritaintech.com</h5>
                </td>
                <td width="150"></td>
                <td width="200" style="line-height:8px;">
                    <br>
                    <br>
                    <br>
                    <br>
                    <br>
                    <br>
                            <div style="line-height:10px;">
                                <h2><strong>Statement</strong></h2>
                                <h4>{{ $customer->company }}</h4>
                                {{-- <h5>{{ $customer->first_name." ".$customer->last_name }}</h5> --}}
                                <h5>{{ $customer->email }}</h5>
                                <h5>{{ $customer->street }} {{ $customer->street2 }}, {{ $customer->city }}</h5>
                                <h5>{{ $customer->postal_code }} {{ $customer->country_id->title ?? null }}</h5>
                                <h5>{{ $customer->vat }}</h5>
                                <!-- Add more customer details as needed -->
                            </div>
                </td>

            </tr>

            <tr style="border-top: 1px solid Black">
                <td width="300">
                    <table>
                    <tr>
                        <br>
                        <td colspan="2"><h3 style="line-height:10px; margin:0px; ">Bill To:</h3></td>
                    </tr>
                    <tr>
                        <td width="10"></td>
                        <td>
                        </td>
                    </tr>
                    </table>
                </td>
                <td width="60">

                </td>
            </tr>
        </table>

    </div>
</body>
</html>
