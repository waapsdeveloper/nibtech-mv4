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


@php
    $packingMode = $packingMode ?? false;
    $deliveryNoteUrl = $deliveryNoteUrl ?? null;
    $labelUrl = $labelUrl ?? null;
    $sessionA4Printer = $sessionA4Printer ?? null;
    $sessionLabelPrinter = $sessionLabelPrinter ?? null;
    $deliveryNoteProxyUrl = $deliveryNoteUrl ? url('order/proxy_server') . '?url=' . urlencode($deliveryNoteUrl) : null;
    $labelPrintViewUrl = $packingMode ? url('export_label') . '?ids=[' . $order->id . ']&packing=1' : null;
    $deliveryPrintViewUrl = ($packingMode && $deliveryNoteUrl) ? route('order.packing_delivery_print', ['id' => $order->id]) : null;
@endphp

@endsection

    @section('content')

    <div id="pdf-container" style="width: 100%;"></div>

    @section('scripts')
    @if ($packingMode)
        <script src="{{ asset('assets/js/qz-tray.js') }}"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    @endif
    @if ($deliveryNoteProxyUrl)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    @endif
    <script>
        const state = {
            packingMode: Boolean(@json($packingMode)),
            pdfUrl: @json($deliveryNoteProxyUrl),
            deliveryNoteRawUrl: @json($deliveryNoteUrl),
            labelRawUrl: @json($labelUrl),
            invoiceNode: document.querySelector('.invoice-container'),
            sessionA4Printer: @json($sessionA4Printer),
            sessionLabelPrinter: @json($sessionLabelPrinter),
            printerEndpoint: @json($packingMode ? route('order.store_printer_preferences') : null),
            labelPrintViewUrl: @json($labelPrintViewUrl),
            deliveryPrintViewUrl: @json($deliveryPrintViewUrl),
        };

    let renderedPdfCache = null;
    let auxiliaryWindowsOpened = false;

        document.addEventListener('DOMContentLoaded', () => {
            if (state.packingMode) {
                initPackingMode();
            } else {
                initStandardMode();
            }
        });

        async function initStandardMode() {
            if (state.pdfUrl) {
                try {
                    await renderPdfPages();
                } catch (error) {
                    console.warn('Unable to render delivery note PDF.', error);
                }
            }

            requestAnimationFrame(() => window.print());
        }

        async function initPackingMode() {
            try {
                if (state.pdfUrl) {
                    await renderPdfPages();
                }

                await ensureQzConnection();
                const printers = await resolvePrinters();
                await persistPrinters(printers);

                openAuxiliaryWindows();
                auxiliaryWindowsOpened = true;

                if (!printers.a4) {
                    throw new Error('No A4 printer available');
                }

                await printInvoiceViaQz(printers.a4);
                setTimeout(() => window.close(), 1200);
            } catch (error) {
                console.error('Packing mode auto-print failed:', error);
                if (!auxiliaryWindowsOpened) {
                    openAuxiliaryWindows();
                    auxiliaryWindowsOpened = true;
                }
                fallbackPackingBehaviour();
            }
        }

        function openAuxiliaryWindows() {
            if (state.labelPrintViewUrl) {
                window.open(state.labelPrintViewUrl, '_blank');
            }
            if (state.deliveryPrintViewUrl) {
                window.open(state.deliveryPrintViewUrl, '_blank');
            }
        }

        function fallbackPackingBehaviour() {
            if (state.deliveryNoteRawUrl) {
                window.open(state.deliveryNoteRawUrl, '_blank');
            }
            if (state.labelRawUrl) {
                window.open(state.labelRawUrl, '_blank');
            }
            requestAnimationFrame(() => window.print());
        }

        async function renderPdfPages() {
            if (!state.pdfUrl) {
                return;
            }
            if (renderedPdfCache) {
                return renderedPdfCache;
            }

            const container = document.getElementById('pdf-container');
            if (!container) {
                return null;
            }

            container.innerHTML = '';

            const loadingTask = pdfjsLib.getDocument(state.pdfUrl);
            const pdf = await loadingTask.promise;

            for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                const page = await pdf.getPage(pageNum);
                const viewport = page.getViewport({ scale: 1.4 });

                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.width = viewport.width;
                canvas.height = viewport.height;

                await page.render({ canvasContext: context, viewport }).promise;
                canvas.style.display = 'block';
                canvas.style.margin = '0 auto 12px';
                canvas.style.maxWidth = '100%';

                container.appendChild(canvas);
            }

            renderedPdfCache = true;
            return true;
        }

        window.onafterprint = () => {
            if (!state.packingMode) {
                setTimeout(() => {
                    window.close();
                }, 500);
            }
        };

        async function ensureQzConnection(timeout = 7000) {
            if (typeof qz === 'undefined') {
                throw new Error('QZ Tray library is not available');
            }

            if (qz.websocket.isActive()) {
                return;
            }

            try {
                qz.websocket.connect();
            } catch (error) {
                console.debug('Initial QZ connection attempt failed.', error);
            }

            const start = Date.now();
            while (Date.now() - start < timeout) {
                if (qz.websocket.isActive()) {
                    return;
                }
                await sleep(250);
            }

            try {
                window.location.assign('qz:launch');
            } catch (error) {
                console.debug('Unable to invoke QZ Tray launch protocol.', error);
            }

            const extendedStart = Date.now();
            while (Date.now() - extendedStart < timeout * 2) {
                if (qz.websocket.isActive()) {
                    return;
                }
                await sleep(250);
            }

            throw new Error('Timed out waiting for QZ Tray connection');
        }

        async function resolvePrinters() {
            const printers = {
                a4: resolveStoredPrinter('a4'),
                label: resolveStoredPrinter('label'),
            };

            if (printers.a4 && printers.label) {
                return printers;
            }

            const availablePrinters = await qz.printers.find();
            const defaultPrinter = await qz.printers.getDefault().catch(() => null);

            if (!printers.a4) {
                printers.a4 = await resolvePrinterChoice('a4', availablePrinters, defaultPrinter);
            }
            if (!printers.label) {
                printers.label = await resolvePrinterChoice('label', availablePrinters, defaultPrinter);
            }

            return printers;
        }

        function resolveStoredPrinter(type) {
            const serverValue = type === 'a4' ? state.sessionA4Printer : state.sessionLabelPrinter;
            if (serverValue) {
                return serverValue;
            }

            const preferenceKeys = type === 'a4'
                ? ['A4_Printer', 'Invoice_Printer', 'Default_Printer']
                : ['Label_Printer', 'Sticker_Printer'];

            const stores = [];
            try { stores.push(window.sessionStorage); } catch (error) { /* ignore */ }
            try { stores.push(window.localStorage); } catch (error) { /* ignore */ }

            for (const store of stores) {
                if (!store) {
                    continue;
                }
                for (const key of preferenceKeys) {
                    try {
                        const value = store.getItem(key);
                        if (value) {
                            return value;
                        }
                    } catch (error) {
                        console.debug('Unable to read printer preference', key, error);
                    }
                }
            }

            return null;
        }

        async function resolvePrinterChoice(type, printers, defaultPrinter) {
            if (type === 'a4' && defaultPrinter) {
                return defaultPrinter;
            }

            if (!Array.isArray(printers) || !printers.length) {
                throw new Error('No printers detected by QZ Tray');
            }

            const label = type === 'a4' ? 'Select A4 Printer' : 'Select Label Printer';
            const description = type === 'a4'
                ? 'Choose the printer to use for A4 documents (invoice, delivery note).'
                : 'Choose the printer to use for shipping labels.';

            const choice = await promptForPrinterSelection(printers, label, description);
            if (!choice) {
                throw new Error('Printer selection cancelled');
            }
            return choice;
        }

        async function persistPrinters(printers) {
            const payload = {};
            if (printers.a4) {
                payload.a4_printer = printers.a4;
                storePrinterLocally('a4', printers.a4);
                state.sessionA4Printer = printers.a4;
            }
            if (printers.label) {
                payload.label_printer = printers.label;
                storePrinterLocally('label', printers.label);
                state.sessionLabelPrinter = printers.label;
            }

            if (!state.printerEndpoint || Object.keys(payload).length === 0) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                await fetch(state.printerEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
            } catch (error) {
                console.debug('Unable to persist printer preferences on server.', error);
            }
        }

        function storePrinterLocally(type, printerName) {
            const keys = type === 'a4'
                ? ['A4_Printer', 'Invoice_Printer']
                : ['Label_Printer', 'Sticker_Printer'];

            const stores = [];
            try { stores.push(window.sessionStorage); } catch (error) { /* ignore */ }
            try { stores.push(window.localStorage); } catch (error) { /* ignore */ }

            stores.forEach(store => {
                if (!store) {
                    return;
                }
                keys.forEach(key => {
                    try {
                        store.setItem(key, printerName);
                    } catch (error) {
                        console.debug('Unable to save printer preference', key, error);
                    }
                });
            });
        }

        async function printInvoiceViaQz(printerName) {
            if (!state.invoiceNode) {
                throw new Error('Invoice container not found');
            }

            const canvas = await html2canvas(state.invoiceNode, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
                logging: false,
            });

            const dataUrl = canvas.toDataURL('image/png', 0.95);
            const base64 = dataUrl.substring(dataUrl.indexOf(',') + 1);

            const config = qz.configs.create(printerName, {
                orientation: 'portrait',
                units: 'mm',
                margins: { top: 5, right: 5, bottom: 5, left: 5 },
            });

            await qz.print(config, [{
                type: 'pixel',
                format: 'image',
                flavor: 'base64',
                data: base64,
            }]);
        }

        function promptForPrinterSelection(printers, titleText, descriptionText) {
            return new Promise(resolve => {
                const overlay = document.createElement('div');
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100vw';
                overlay.style.height = '100vh';
                overlay.style.background = 'rgba(15, 23, 42, 0.45)';
                overlay.style.display = 'flex';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';
                overlay.style.zIndex = '9999';

                const dialog = document.createElement('div');
                dialog.style.background = '#ffffff';
                dialog.style.padding = '24px';
                dialog.style.borderRadius = '12px';
                dialog.style.boxShadow = '0 16px 40px rgba(15,23,42,0.30)';
                dialog.style.width = 'min(480px, 92vw)';
                dialog.style.maxHeight = '80vh';
                dialog.style.overflow = 'auto';
                dialog.style.fontFamily = 'Arial, sans-serif';

                const title = document.createElement('h2');
                title.textContent = titleText;
                title.style.margin = '0 0 16px';
                title.style.fontSize = '20px';
                title.style.color = '#0f172a';

                const description = document.createElement('p');
                description.textContent = descriptionText;
                description.style.margin = '0 0 16px';
                description.style.fontSize = '14px';
                description.style.color = '#475569';

                const select = document.createElement('select');
                select.style.width = '100%';
                select.style.padding = '10px 12px';
                select.style.fontSize = '15px';
                select.style.border = '1px solid #cbd5f5';
                select.style.borderRadius = '8px';
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
                actions.style.gap = '12px';

                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.textContent = 'Cancel';
                cancelBtn.style.padding = '8px 14px';
                cancelBtn.style.fontSize = '14px';
                cancelBtn.style.border = 'none';
                cancelBtn.style.borderRadius = '4px';
                cancelBtn.style.background = '#e2e8f0';
                cancelBtn.style.color = '#0f172a';
                cancelBtn.style.cursor = 'pointer';
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

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
    </script>
@endsection
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
                const viewport = page.getViewport({ scale: 1.4 });

                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.width = viewport.width;
                canvas.height = viewport.height;

                await page.render({ canvasContext: context, viewport }).promise;
                canvas.style.display = 'block';
                canvas.style.margin = '0 auto 12px';
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
