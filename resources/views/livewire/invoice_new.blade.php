@extends('layouts.new')

@section('styles')

<style>
    body {
        font-family: 'Times New Roman', Times, serif, Arial, sans-serif;
        font-size: 18px;
        font-weight: 700 !important;
        margin: 0;
        padding: 0;
        color: #000;
        background-color: #fff !important;
    }
    p {
        font-size: 18px;
    }
    .invoice-container {
        width: 100%;
        /* max-width: 1000px; */
        margin: 20px auto;
        padding: 20px;
        /* border: 1px solid #ddd;
        border-radius: 8px; */
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
        font-size: 18px;
    }
    .invoice-details h3, .invoice-details h4 {
        margin: 5px 0;
    }
    .invoice-details p {
        font-size: 18px;
    }
    .invoice-items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        font-size: 18px;
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

    {{-- <iframe src="{{ $order->delivery_note_url }}#toolbar=0&navpanes=0&scrollbar=0" width="900" height="1421"></iframe> --}}
    {{-- <div class="invoice-container">


        <table border="0">
            <tr style="text-align: right; padding:0; margin:0;">
                <td style="text-align: left; padding:0; margin:0; line-height:10px">

                        <br><br>
                        <img src="{{ public_path('assets/img/brand').'/'.env('APP_LOGO') }}" alt="" height="50">
                </td>
                <td width="150"></td>
                <td width="200" style="line-height:8px;">
                        <h4><strong>{{ env('APP_NAME') }}</strong></h4>
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
            <h4>Stock Sold on Marginal VAT Scheme. VAT Number: {{ env('APP_VAT') }}</h4>
        </div>
    </div> --}}

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
        const pdfUrl = "{{ url('order/proxy_server').'?url='.urlencode($order->delivery_note_url) }}";
        const invoiceNode = document.querySelector('.invoice-container');
        let pdfImageHtml = null;
        let pdfBase64Cache = null;

        document.addEventListener('DOMContentLoaded', () => {
            renderPdfPages()
                .then(html => {
                    pdfImageHtml = html;
                    return tryQzPrint();
                })
                .catch(error => {
                    console.warn('QZ print failed, falling back to browser print.', error);
                    fallbackWindowPrint();
                });
        });

        async function renderPdfPages() {
            if (pdfImageHtml !== null) {
                return pdfImageHtml;
            }

            const container = document.getElementById('pdf-container');
            container.innerHTML = '';

            const loadingTask = pdfjsLib.getDocument(pdfUrl);
            const pdf = await loadingTask.promise;

            let html = '';
            for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                const page = await pdf.getPage(pageNum);
                const viewport = page.getViewport({ scale: 1.69 });

                const canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                const context = canvas.getContext('2d');

                await page.render({ canvasContext: context, viewport }).promise;
                container.appendChild(canvas);

                const dataUrl = canvas.toDataURL('image/png');
                html += `<img src="${dataUrl}" style="width:100%;display:block;margin:0 auto;" />`;
            }

            pdfImageHtml = html;
            return html;
        }

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

            const config = qz.configs.create(printer, {
                orientation: 'portrait',
                scaleContent: true,
                rasterize: true,
                htmlImageTimeout: 60000
            });

            const pdfBase64 = await fetchPdfAsBase64();

            const invoiceHtmlDocument = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
                @page { size: A4 portrait; margin: 0; }
                body { margin: 0; padding: 24px; font-family: 'Times New Roman', Times, serif; background: #fff; color: #000; }
                h1, h2, h3, h4, h5 { margin: 0 0 6px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                .no-border td, .no-border th { border: none; }
            </style></head><body>${invoiceNode.outerHTML}</body></html>`;

            const printItems = [
                {
                    type: 'pdf',
                    format: 'base64',
                    data: pdfBase64
                },
                {
                    type: 'html',
                    format: 'plain',
                    data: invoiceHtmlDocument
                }
            ];

            await qz.print(config, printItems);
        }

        async function fetchPdfAsBase64() {
            if (pdfBase64Cache) {
                return pdfBase64Cache;
            }

            try {
                const response = await fetch(pdfUrl, { cache: 'no-store' });
                if (!response.ok) {
                    throw new Error(`Failed to fetch PDF: ${response.status}`);
                }

                const blob = await response.blob();
                const base64 = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => {
                        const result = typeof reader.result === 'string' ? reader.result : '';
                        const commaIndex = result.indexOf(',');
                        if (commaIndex === -1) {
                            reject(new Error('Unexpected PDF data URL format'));
                            return;
                        }
                        resolve(result.substring(commaIndex + 1));
                    };
                    reader.onerror = () => reject(reader.error || new Error('Failed to read PDF blob'));
                    reader.readAsDataURL(blob);
                });

                pdfBase64Cache = base64;
                return base64;
            } catch (error) {
                console.warn('Unable to convert PDF to base64 for QZ Tray.', error);
                throw error;
            }
        }

        function fallbackWindowPrint() {
            renderPdfPages()
                .then(() => {
                    window.print();
                })
                .catch(() => window.print());
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
