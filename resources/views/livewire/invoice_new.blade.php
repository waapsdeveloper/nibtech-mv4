@extends('layouts.new')

@section('styles')

<style>
    body {
        font-family: 'Times New Roman', Times, serif, Arial, sans-serif;
        font-size: 12px;
        font-weight: 700 !important;
        margin: 0;
        padding: 0;
        color: #000;
        background-color: #fff !important;
    }
    p {
        font-size: 12px;
    }
    .invoice-container {
        width: 210mm;
        max-width: 210mm;
        margin: 10mm auto;
        padding: 0 0;
        background-color: #ffffff;
    }
    .invoice-headers {
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .invoice-headers img {
        max-height: 80px;
    }
    .invoice-headers .company-info {
        /* text-align: right; */
    }
    .invoice-details {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    .invoice-details .bill-to {
        width: 30%;
    }
    .invoice-details .invoice-info {
        /* width: 20%; */
        text-align: right;
        font-size: 14px;
    }
    .invoice-details h3, .invoice-details h4 {
        margin: 5px 0;
    }
    .invoice-details p {
        font-size: 14px;
    }
    .invoice-items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        font-size: 14px;
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
        border-top: 2px solid #000;
    }
    .invoice-items tfoot td {
        border: none;
        padding: 5px;
        text-align: right;
    }
    .store-policy {
        margin-top: 20px;
        border-top: 2px solid #000;
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

    <br>
    <br>
    <br>
    <br>
    <div class="invoice-container">

        <div class="invoice-headers">
            <div class="company-info">
                <img src="{{ asset('assets/img/brand').'/'.env('APP_LOGO') }}" alt="Company Logo" height="100">
                <br>
                <br>
                @if (env('APP_NAME') != null)
                    <h3><strong>{{ env('APP_NAME') }}</strong></h3>
                @endif
                {{-- <h3><strong>{{ env('APP_NAME') }}</strong></h3> --}}
                <p>Cromac Square, Forsyth House, Belfast, BT2 8LA</p>
            </div>
            <div class="invoice-logo">
                <!-- Empty space for alignment -->
            </div>
        </div>
<br>
        <div class="invoice-details">
            <div class="bill-to ">
                <h2><strong>Bill To:</strong></h2>
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
                <h1><strong>INVOICE</strong></h1>
                <table>
                    <tr>
                        <td class="px-2"><strong>Order ID:</strong></td>
                        <td>{{ $order->reference_id }}</td>
                    </tr>
                    @if ($order->admin)
                        <tr>
                            <td class="px-2"><strong>Sales Rep:</strong></td>
                            <td>{{ $order->admin->first_name }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="px-2"><strong>Order Date:</strong></td>
                        <td>{{ \Carbon\Carbon::parse($order->created_at)->format('d-m-Y') }}</td>
                    </tr>
                    <tr>
                        <td class="px-2"><strong>Invoice Date:</strong></td>
                        <td>{{ \Carbon\Carbon::parse($order->processed_at)->format('d-m-Y') }}</td>
                    </tr>
                </table>
            </div>
        </div>
        <br>
        <br>

        <!-- Order Items -->
        <h2><strong>Order Items</strong></h2>
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
<br>
<tfoot>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Sub Total:</strong></td>
                    <td align="right"><strong>{{ $order->currency_id->sign }}{{ number_format($totalAmount, 2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>VAT:</strong></td>
                    <td align="right"><strong>{{ $order->currency_id->sign }}0.00</strong></td>
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
        <br>

        <!-- Store Policy -->
        <div class="store-policy">
            <h3>Store Policy</h3>
            <hr>
            <p>Stock Sold on Marginal VAT Scheme. VAT Number: {{ env('APP_VAT')}}</p>
        </div>
    </div>
    @endsection

    @section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <script>
        const pdfUrl = @json($order->delivery_note_url ? url('order/proxy_server').'?url='.urlencode($order->delivery_note_url) : null);

        document.addEventListener('DOMContentLoaded', async () => {
            if (pdfUrl) {
                try {
                    await renderPdfPages();
                } catch (error) {
                    console.warn('Unable to render delivery note PDF.', error);
                }
            }

            requestAnimationFrame(() => window.print());
        });

        async function renderPdfPages() {
            const container = document.getElementById('pdf-container');
            if (!container) {
                return;
            }

            container.innerHTML = '';

            const loadingTask = pdfjsLib.getDocument(pdfUrl);
            const pdf = await loadingTask.promise;

            for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                const page = await pdf.getPage(pageNum);
                const viewport = page.getViewport({ scale: 1.5 });

                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.width = viewport.width;
                canvas.height = viewport.height;

                await page.render({ canvasContext: context, viewport }).promise;
                canvas.style.display = 'block';
                canvas.style.margin = '0 auto';
                canvas.style.maxWidth = '100%';

                container.appendChild(canvas);
            }
        }

        window.onafterprint = () => {
            setTimeout(() => {
                window.close();
            }, 200);
        };
    </script>
@endsection
