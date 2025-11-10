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
        margin: 5mm auto;
        padding: 10mm;
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
    <script src="{{ asset('assets/js/qz-tray.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        const invoiceNode = document.querySelector('.invoice-container');

        document.addEventListener('DOMContentLoaded', () => {
            tryQzPrint()
                .catch(error => {
                    console.warn('QZ print failed, falling back to browser print.', error);
                    fallbackWindowPrint();
                });
        });

        async function tryQzPrint() {
            if (typeof qz === 'undefined' || !qz.websocket) {
                throw new Error('QZ Tray libraries not available');
            }

            await waitForQzConnection();

            let printer = resolveInvoicePrinter();
            if (!printer) {
                const availablePrinters = await qz.printers.find();
                if (!availablePrinters.length) {
                    throw new Error('No printers detected by QZ Tray');
                }

                printer = await promptForPrinterSelection(availablePrinters);
                if (!printer) {
                    throw new Error('Printer selection cancelled');
                }

                storeInvoicePrinter(printer);
            }

            // Convert invoice to image and print
            const invoiceImageBase64 = await convertInvoiceToImage();
            const imageConfig = qz.configs.create(printer, {
                orientation: 'portrait',
                margins: { top: 5, right: 5, bottom: 5, left: 5 },
                units: 'mm'
            });
            await qz.print(imageConfig, [{
                type: 'pixel',
                format: 'image',
                flavor: 'base64',
                data: invoiceImageBase64
            }]);
        }

        async function convertInvoiceToImage() {
            try {
                const canvas = await html2canvas(invoiceNode, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    logging: false,
                    imageTimeout: 0
                });

                const dataUrl = canvas.toDataURL('image/png', 0.95);
                const base64 = dataUrl.substring(dataUrl.indexOf(',') + 1);
                return base64;
            } catch (error) {
                console.error('html2canvas failed:', error);
                throw new Error('Failed to convert invoice to image');
            }
        }

        function fallbackWindowPrint() {
            window.print();
        }

        function resolveInvoicePrinter() {
            const preferredKeys = ['Invoice_Printer', 'A4_Printer', 'Default_Printer'];
            for (const key of preferredKeys) {
                const value = window.localStorage ? localStorage.getItem(key) : null;
                if (value) {
                    return value;
                }
            }

            return null;
        }

        function storeInvoicePrinter(printerName) {
            if (window.localStorage) {
                localStorage.setItem('Invoice_Printer', printerName);
                localStorage.setItem('A4_Printer', printerName);
            }
            if (window.sessionStorage) {
                sessionStorage.setItem('Invoice_Printer', printerName);
                sessionStorage.setItem('A4_Printer', printerName);
            }
        }

        function promptForPrinterSelection(printers) {
            return new Promise(resolve => {
                const overlay = document.createElement('div');
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100vw';
                overlay.style.height = '100vh';
                overlay.style.background = 'rgba(0,0,0,0.45)';
                overlay.style.zIndex = '9999';
                overlay.style.display = 'flex';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';

                const dialog = document.createElement('div');
                dialog.style.background = '#fff';
                dialog.style.padding = '24px';
                dialog.style.borderRadius = '8px';
                dialog.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                dialog.style.width = 'min(90vw, 420px)';
                dialog.style.fontFamily = 'Arial, sans-serif';

                const title = document.createElement('h2');
                title.textContent = 'Select A4 Printer';
                title.style.marginTop = '0';
                title.style.fontSize = '20px';
                title.style.marginBottom = '12px';

                const description = document.createElement('p');
                description.textContent = 'Choose the printer you want to use for invoices.';
                description.style.marginTop = '0';
                description.style.fontSize = '14px';
                description.style.marginBottom = '16px';

                const select = document.createElement('select');
                select.style.width = '100%';
                select.style.padding = '8px';
                select.style.fontSize = '14px';
                select.style.border = '1px solid #ccc';
                select.style.borderRadius = '4px';
                select.style.marginBottom = '20px';

                printers.forEach(name => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    select.appendChild(option);
                });

                const actions = document.createElement('div');
                actions.style.display = 'flex';
                actions.style.justifyContent = 'flex-end';
                actions.style.gap = '8px';

                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.textContent = 'Cancel';
                cancelBtn.style.padding = '8px 14px';
                cancelBtn.style.fontSize = '14px';
                cancelBtn.style.border = '1px solid #ccc';
                cancelBtn.style.borderRadius = '4px';
                cancelBtn.style.background = '#f1f1f1';
                cancelBtn.addEventListener('click', () => {
                    document.body.removeChild(overlay);
                    resolve(null);
                });

                const confirmBtn = document.createElement('button');
                confirmBtn.type = 'button';
                confirmBtn.textContent = 'Use Printer';
                confirmBtn.style.padding = '8px 14px';
                confirmBtn.style.fontSize = '14px';
                confirmBtn.style.border = 'none';
                confirmBtn.style.borderRadius = '4px';
                confirmBtn.style.background = '#2563eb';
                confirmBtn.style.color = '#fff';
                confirmBtn.style.cursor = 'pointer';
                confirmBtn.addEventListener('click', () => {
                    const value = select.value || null;
                    document.body.removeChild(overlay);
                    resolve(value);
                });

                actions.appendChild(cancelBtn);
                actions.appendChild(confirmBtn);

                dialog.appendChild(title);
                dialog.appendChild(description);
                dialog.appendChild(select);
                dialog.appendChild(actions);

                overlay.appendChild(dialog);
                document.body.appendChild(overlay);
            });
        }

        function waitForQzConnection(timeout = 7000) {
            if (qz.websocket.isActive()) {
                return Promise.resolve();
            }

            try {
                qz.websocket.connect();
            } catch (error) {
                console.debug('QZ connection attempt rejected immediately.', error);
            }

            return new Promise((resolve, reject) => {
                const start = Date.now();
                const timer = setInterval(() => {
                    if (qz.websocket.isActive()) {
                        clearInterval(timer);
                        resolve();
                    } else if (Date.now() - start > timeout) {
                        clearInterval(timer);
                        reject(new Error('Timed out waiting for QZ Tray connection'));
                    }
                }, 250);
            });
        }
        window.onafterprint = () => {
            // if (!qz?.websocket || !qz.websocket.isActive()) {
                // Delay closing to allow any async cleanup (e.g. QZ Tray) to finish.
                const closeTimeout = 500; // ms
                setTimeout(() => {
                    window.close();
                }, closeTimeout);
            // }
        };
    </script>
@endsection
