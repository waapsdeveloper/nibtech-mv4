@extends('layouts.new')

@section('styles')

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        color: #333;
        background-color: #fff !important;
    }
    .invoice-container {
        width: 100%;
        max-width: 900px;
        margin: 20px auto;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
    }
    .invoice-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #333;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .invoice-header img {
        max-height: 50px;
    }
    .invoice-header .company-info {
        text-align: right;
    }
    .invoice-details {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    .invoice-details .bill-to {
        width: 50%;
    }
    .invoice-details .invoice-info {
        width: 20%;
        text-align: right;
    }
    .invoice-details h3, .invoice-details h4 {
        margin: 5px 0;
    }
    .invoice-items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .invoice-items th, .invoice-items td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    .invoice-items th {
        background-color: #f4f4f4;
    }
    .invoice-items tfoot {
        border-top: 2px solid #333;
    }
    .invoice-items tfoot td {
        border: none;
        padding: 5px;
        text-align: right;
    }
    .store-policy {
        margin-top: 20px;
        border-top: 2px solid #333;
        padding-top: 10px;
    }
    .store-policy h3, .store-policy h4 {
        margin: 5px 0;
    }
</style>
<style type="text/css" media="print">
    body{
        background-color: #fff !important;
    }
    @page { size: landscape; }
    /* #pdf-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
}

canvas {
    margin-bottom: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
} */

  </style>


@endsection

    @section('content')

    <div id="pdf-container" style="width: 100%;"></div>

    {{-- <iframe src="{{ $order->delivery_note_url }}#toolbar=0&navpanes=0&scrollbar=0" width="900" height="1421"></iframe> --}}
    {{-- <div class="invoice-container">


        <table border="0">
            <tr style="text-align: right; padding:0; margin:0;">
                <td style="text-align: left; padding:0; margin:0; line-height:10px">

                        <br><br>
                        <img src="{{ public_path('assets/img/brand/logo1.png') }}" alt="" height="50">
                </td>
                <td width="150"></td>
                <td width="200" style="line-height:8px;">
                        <h4><strong>(NI) Britain Tech Ltd</strong></h4>
                        <h4>Cromac Square,</h4>
                        <h4>Forsyth House,</h4>
                        <h4>Belfast, BT2 8LA</h4>

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
                        <td width="">
                            <div style="line-height:10px; margin:0; padding:0;">
                                <h5>{{ $customer->company }}</h5>
                                <h5>{{ $customer->first_name." ".$customer->last_name }}</h5>
                                <h5>{{ $customer->phone }}</h5>
                                <h5>{{ $customer->street }} {{ $customer->street2 }}, {{ $customer->city }}</h5>
                                <h5>{{ $customer->postal_code }} {{ $customer->country_id->title ?? null }}</h5>
                                <h5>{{ $customer->vat }}</h5>
                                <!-- Add more customer details as needed -->
                            </div>
                        </td>
                    </tr>
                    </table>
                </td>
                <td width="60">

                </td>
                <td style="text-align: right; padding:0; margin:0; line-height:10px" width="170">
                    <br><br>
                    <h1 style="font-size: 26px; text-align:right;">INVOICE</h1>
                    <table cellspacing="4">

                    <br><br><br><br>
                        <tr>
                            <td style="text-align: left; margin-top:5px;" width="80"><h4><strong>Order ID:</strong></h4></td>
                            <td colspan="2" width="80"><h4 style="font-weight: 400">{{ $order->reference_id }}</h4></td>
                        </tr>
                        @if ($order->admin)

                        <tr>
                            <td style="text-align: left; margin-top:5px;"><h4><strong>Sales Rep:</strong></h4></td>
                            <td colspan="2"><h4 style="font-weight: 400">{{ $order->admin->first_name }}</h4></td>
                        </tr>
                        @endif
                        <tr>
                            <td style="text-align: left; margin-top:5px;"><h4><strong>Order Date:</strong></h4></td>
                            <td colspan="2"><h4 style="font-weight: 400">{{ \Carbon\Carbon::parse($order->created_at)->format('d-m-Y') }}</h4></td>
                        </tr>
                        <tr>
                            <td style="text-align: left; margin-top:5px;"><h4><strong>Invoice Date:</strong></h4></td>
                            <td colspan="2"><h4 style="font-weight: 400">{{ \Carbon\Carbon::parse($order->processed_at)->format('d-m-Y') }}</h4></td>
                        </tr>
                    </table>
                    {{-- <h3><strong>Order ID:</strong> {{ $order->reference_id }}</h3>
                    <h3><strong>Order Date:</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y') }}</h4><h4> {{ \Carbon\Carbon::parse($order->created_at)->format('H:m:s') }}&nbsp;</h3>
                </td>
            </tr>
        </table>

        <!-- Order Items -->
        <div class="order-items">
            <h3>Order Items</h3>
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
                            if($item->stock_id == null){
                                continue;
                            }
                            $itemTotal = $item->price;
                            $totalAmount += $itemTotal;
                            $totalQty += 1;

                            if($item->variation->storage_id){
                                $storage = $item->variation->storage_id->name . " - " ;
                            }else {
                                $storage = null;
                            }
                            if($item->variation->color_id){
                                $color = $item->variation->color_id->name . " - " ;
                            }else {
                                $color = null;
                            }
                            if ($order->exchange_items->count() > 0){
                                $item = $order->exchange_items[0];
                            }
                            if($item->replacement){
                                $replacement = $item->replacement;
                                while ($replacement != null){
                                    $item = $replacement;
                                    $replacement = $replacement->replacement;
                                }
                            }
                        @endphp
                        <tr>
                            <td width="320">{{ $item->variation->product->model . " - " . $storage . $color }} <br> {{  $item->stock->imei . $item->stock->serial_number . " - " . $item->stock->tester }}</td>
                            <td width="80" align="right">{{ $order->currency_id->sign }}{{ number_format($item->price,2) }}</td>
                            <td width="40"> 1 </td>
                            <td width="90" align="right">{{ $order->currency_id->sign }}{{ number_format($item->price,2) }}</td>
                        </tr>
                    @endforeach
                        <tr>
                            <td width="320">Accessories</td>
                            <td width="80" align="right">{{ $order->currency_id->sign }}0.00</td>
                            <td width="40">{{ $totalQty }}</td>
                            <td width="90" align="right">{{ $order->currency_id->sign }}0.00</td>
                        </tr>
                    <hr>
                </tbody>
                <tfoot>
                    <tr style="border-top: 1px solid Black" >
                        <td></td>
                        <td colspan="3">
                            <table cellpadding="5">
                                    <tr>
                                        <td>Sub Total:</td>
                                        <td align="right"> <strong>{{ $order->currency_id->sign }}{{number_format( $totalAmount,2) }}</strong></td>
                                    </tr>
                                    <br>
                                    <br>
                                    <hr>
                                    <tr>
                                        <td>Amount Due:</td>
                                        <td align="right"> <strong>{{ $order->currency_id->sign }}{{number_format( $totalAmount,2) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Back Market:</td>
                                        <td align="right"> <strong>{{ $order->currency_id->sign }}{{number_format( $totalAmount,2) }}</strong></td>
                                    </tr>
                                    <hr>
                                    <tr>
                                        <td>Change:</td>
                                        <td align="right"> <strong>{{ $order->currency_id->sign }}0.00</strong></td>
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
            <h4>Stock Sold on Marginal VAT Scheme. VAT Number: GB972500428</h4>
        </div>
    </div> --}}

    <br>
    <br>
    <br>
    <br>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <img src="{{ public_path('assets/img/brand/logo1.png') }}" alt="Company Logo">
                <h4><strong>(NI) Britain Tech Ltd</strong></h4>
                <p>Cromac Square, Forsyth House, Belfast, BT2 8LA</p>
            </div>
            <div class="invoice-logo">
                <!-- Empty space for alignment -->
            </div>
        </div>

        <div class="invoice-details">
            <div class="bill-to">
                <h3>Bill To:</h3>
                @if($customer->company != null)
                    <p class="mb-0">{{ $customer->company }}</p>
                @endif
                @if($customer->first_name != null)
                    <p class="mb-0">{{ $customer->first_name }} {{ $customer->last_name }}</p>
                @endif
                @if($customer->phone != null)
                    <p class="mb-0">{{ $customer->phone }}</p>
                @endif
                @if($customer->street != null)
                    <p class="mb-0">{{ $customer->street }} {{ $customer->street2 }}, {{ $customer->city }}</p>
                @endif
                @if($customer->postal_code != null)
                    <p class="mb-0">{{ $customer->postal_code }} {{ $customer->country_id->title ?? null }}</p>
                @endif
                @if($customer->vat != null)
                    <p class="mb-0">{{ $customer->vat }}</p>
                @endif
            </div>
            <div class="invoice-info">
                <h1>INVOICE</h1>
                <table>
                    <tr>
                        <td><strong>Order ID:</strong></td>
                        <td>{{ $order->reference_id }}</td>
                    </tr>
                    @if ($order->admin)
                        <tr>
                            <td><strong>Sales Rep:</strong></td>
                            <td>{{ $order->admin->first_name }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td><strong>Order Date:</strong></td>
                        <td>{{ \Carbon\Carbon::parse($order->created_at)->format('d-m-Y') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Invoice Date:</strong></td>
                        <td>{{ \Carbon\Carbon::parse($order->processed_at)->format('d-m-Y') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Order Items -->
        <h3>Order Items</h3>
        <table class="invoice-items">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalAmount = 0;
                    $totalQty = 0;
                @endphp
                @foreach ($orderItems as $item)
                    @php
                        if ($item->stock_id == null) {
                            continue;
                        }
                        $itemTotal = $item->price;
                        $totalAmount += $itemTotal;
                        $totalQty += 1;

                        $storage = $item->variation->storage_id ? $item->variation->storage_id->name . " - " : '';
                        $color = $item->variation->color_id ? $item->variation->color_id->name . " - " : '';

                        if ($order->exchange_items->count() > 0) {
                            $item = $order->exchange_items[0];
                        }
                        if ($item->replacement) {
                            $replacement = $item->replacement;
                            while ($replacement != null) {
                                $item = $replacement;
                                $replacement = $replacement->replacement;
                            }
                        }
                    @endphp
                    <tr>
                        <td>{{ $item->variation->product->model . " - " . $storage . $color }} <br> {{ $item->stock->imei . $item->stock->serial_number . " - " . $item->stock->tester }}</td>
                        <td align="right">{{ $order->currency_id->sign }}{{ number_format($item->price, 2) }}</td>
                        <td align="right">1</td>
                        <td align="right">{{ $order->currency_id->sign }}{{ number_format($item->price, 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td>Accessories</td>
                    <td align="right">{{ $order->currency_id->sign }}0.00</td>
                    <td align="right">{{ $totalQty }}</td>
                    <td align="right">{{ $order->currency_id->sign }}0.00</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Sub Total:</strong></td>
                    <td align="right"><strong>{{ $order->currency_id->sign }}{{ number_format($totalAmount, 2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Amount Due:</strong></td>
                    <td align="right"><strong>{{ $order->currency_id->sign }}{{ number_format($totalAmount, 2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Back Market:</strong></td>
                    <td align="right"><strong>{{ $order->currency_id->sign }}{{ number_format($totalAmount, 2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Change:</strong></td>
                    <td align="right"><strong>{{ $order->currency_id->sign }}0.00</strong></td>
                </tr>
            </tfoot>
        </table>

        <!-- Store Policy -->
        <div class="store-policy">
            <h3>Store Policy</h3>
            <hr>
            <p>Stock Sold on Marginal VAT Scheme. VAT Number: GB972500428</p>
        </div>
    </div>
    @endsection

    @section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const url = "{{ url('order/proxy_server').'?url='.urlencode($order->delivery_note_url) }}";
            // const url = '{{ $order->delivery_note_url }}';
            const container = document.getElementById('pdf-container');

            const loadingTask = pdfjsLib.getDocument(url);
            loadingTask.promise.then(function(pdf) {
                const totalPages = pdf.numPages;

                for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
                    pdf.getPage(pageNum).then(function(page) {
                        const viewport = page.getViewport({ scale: 1.69 });

                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        container.appendChild(canvas);

                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };

                        page.render(renderContext).promise.then(function () {
                            console.log('Page rendered');
                        });
                    });
                }
            }, function (reason) {
                console.error(reason);
            });
        });

    </script>

    @endsection
