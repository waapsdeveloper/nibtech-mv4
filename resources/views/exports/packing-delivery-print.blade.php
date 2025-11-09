<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Printing Delivery Note</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f8fafc;
            color: #0f172a;
            font-family: Arial, sans-serif;
        }
        .status-wrapper {
            text-align: center;
        }
        .spinner {
            width: 36px;
            height: 36px;
            margin: 0 auto 12px;
            border: 4px solid #cbd5f5;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        #status {
            font-size: 16px;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="status-wrapper">
        <div class="spinner" aria-hidden="true"></div>
        <p id="status">Preparing delivery note...</p>
    </div>

    <script src="{{ asset('assets/js/qz-tray.js') }}"></script>
    <script>
        (function () {
            sessionStorage.setItem('packing_delivery_window_opened', Date.now().toString());

            const statusNode = document.getElementById('status');
            const pdfUrl = @json($pdfProxyUrl);
            let serverA4Printer = @json($sessionA4Printer);
            const printerEndpoint = @json(route('order.store_printer_preferences'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const autoCloseDelay = 1200;

            function updateStatus(text) {
                if (statusNode) {
                    statusNode.textContent = text;
                }
            }

            function sleep(ms) {
                return new Promise(resolve => setTimeout(resolve, ms));
            }

            function fallbackToPdf() {
                updateStatus('Opening delivery note for manual printing...');
                window.location.replace(pdfUrl);
            }

            async function waitForQzConnection(timeout = 7000) {
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
                    console.debug('Unable to invoke qz:launch protocol.', error);
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

            function resolveStoredPrinter() {
                if (serverA4Printer) {
                    return serverA4Printer;
                }

                const keys = ['A4_Printer', 'Invoice_Printer', 'Default_Printer'];
                const stores = [];
                try { stores.push(window.sessionStorage); } catch (error) { /* ignore */ }
                try { stores.push(window.localStorage); } catch (error) { /* ignore */ }

                for (const store of stores) {
                    if (!store) {
                        continue;
                    }
                    for (const key of keys) {
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

            function promptForPrinterSelection(printers) {
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
                    title.textContent = 'Select A4 Printer';
                    title.style.margin = '0 0 16px';
                    title.style.fontSize = '20px';
                    title.style.color = '#0f172a';

                    const description = document.createElement('p');
                    description.textContent = 'Choose the printer to use for delivery notes.';
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

            function storeA4Printer(printerName) {
                if (!printerName) {
                    return;
                }

                serverA4Printer = printerName;

                const keys = ['A4_Printer', 'Invoice_Printer'];
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

                if (!printerEndpoint) {
                    return;
                }

                fetch(printerEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ a4_printer: printerName })
                }).catch(error => {
                    console.debug('Unable to persist A4 printer preference on server.', error);
                });
            }

            async function fetchPdfAsBase64() {
                const response = await fetch(pdfUrl, { cache: 'no-store' });
                if (!response.ok) {
                    throw new Error(`Failed to fetch PDF (${response.status})`);
                }

                const blob = await response.blob();
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => {
                        const result = typeof reader.result === 'string' ? reader.result : '';
                        const commaIndex = result.indexOf(',');
                        resolve(commaIndex === -1 ? result : result.substring(commaIndex + 1));
                    };
                    reader.onerror = () => reject(reader.error || new Error('Failed to convert PDF to base64'));
                    reader.readAsDataURL(blob);
                });
            }

            async function sendToPrinter() {
                if (typeof qz === 'undefined') {
                    throw new Error('QZ Tray library is not loaded');
                }

                if (!pdfUrl) {
                    throw new Error('Delivery note URL not provided');
                }

                updateStatus('Connecting to QZ Tray...');
                await waitForQzConnection();

                let printer = resolveStoredPrinter();
                if (!printer) {
                    const availablePrinters = await qz.printers.find();
                    if (!availablePrinters.length) {
                        throw new Error('No printers detected by QZ Tray');
                    }

                    printer = await promptForPrinterSelection(availablePrinters);
                    if (!printer) {
                        throw new Error('Printer selection cancelled');
                    }
                }

                const pdfBase64 = await fetchPdfAsBase64();

                updateStatus('Sending delivery note to printer...');
                const config = qz.configs.create(printer, {
                    orientation: 'portrait',
                    margins: { top: 5, right: 5, bottom: 5, left: 5 },
                    units: 'mm'
                });

                await qz.print(config, [{
                    type: 'pdf',
                    format: 'base64',
                    data: pdfBase64
                }]);

                storeA4Printer(printer);
            }

            document.addEventListener('DOMContentLoaded', async () => {
                try {
                    await sendToPrinter();
                    updateStatus('Delivery note sent to printer.');
                    setTimeout(() => window.close(), autoCloseDelay);
                } catch (error) {
                    console.error('Automatic delivery note printing failed:', error);
                    fallbackToPdf();
                }
            });
        })();
    </script>
</body>
</html>
