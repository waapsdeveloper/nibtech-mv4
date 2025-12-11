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
        #printer-info-panel {
            position: fixed;
            top: 10px;
            right: 10px;
            background: white;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: Arial, sans-serif;
            font-size: 13px;
            z-index: 10000;
            min-width: 280px;
        }
        .info-row {
            margin-bottom: 6px;
        }
        .btn-row {
            margin-top: 10px;
            display: flex;
            gap: 8px;
        }
        .btn {
            flex: 1;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-success {
            background: #059669;
            color: white;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="status-wrapper">
        <div class="spinner" aria-hidden="true"></div>
        <p id="status">Preparing delivery note...</p>
    </div>

    <div id="printer-info-panel">
        <div style="margin-bottom: 8px; font-weight: bold; color: #0f172a; font-size: 14px;">
            📄 Delivery Note Printer Status
        </div>
        <div class="info-row">
            <strong>QZ Tray:</strong> <span id="qz-status" style="color: #94a3b8;">Connecting...</span>
        </div>
        <div class="info-row">
            <strong>Printer:</strong> <span id="printer-name" style="color: #94a3b8;">Detecting...</span>
        </div>
        <div class="info-row">
            <strong>Status:</strong> <span id="print-status" style="color: #94a3b8;">Initializing...</span>
        </div>
        <div class="btn-row">
            <button id="change-printer-btn" class="btn btn-primary" disabled>Change Printer</button>
            <button id="retry-print-btn" class="btn btn-success" disabled>Retry Print</button>
        </div>
    </div>

    <script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/qz-tray.js') }}"></script>
    <script src="{{ asset('assets/js/functions.js') }}"></script>
    <script>
        (function () {
            sessionStorage.setItem('packing_delivery_window_opened', Date.now().toString());

            const statusNode = document.getElementById('status');
            const spinnerEl = document.querySelector('.spinner');
            const pdfUrl = @json($pdfProxyUrl);
            let serverA4Printer = @json($sessionA4Printer);
            const printerEndpoint = @json(route('order.store_printer_preferences'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const autoCloseDelay = 1200;
            let currentPrinterName = null;
            let isQzConnected = false;

            function updateQzStatus(connected) {
                isQzConnected = connected;
                const statusEl = document.getElementById('qz-status');
                if (statusEl) {
                    statusEl.textContent = connected ? 'Connected ✓' : 'Disconnected ✗';
                    statusEl.style.color = connected ? '#059669' : '#dc2626';
                }

                const changePrinterBtn = document.getElementById('change-printer-btn');
                const retryBtn = document.getElementById('retry-print-btn');
                if (changePrinterBtn) {
                    changePrinterBtn.disabled = !connected;
                }
                if (retryBtn) {
                    retryBtn.disabled = !connected;
                }
            }

            function updatePrinterName(name) {
                currentPrinterName = name;
                const nameEl = document.getElementById('printer-name');
                if (nameEl) {
                    nameEl.textContent = name || 'None selected';
                    nameEl.style.color = name ? '#0f172a' : '#94a3b8';
                }
            }

            function updatePrinterStatus(status, type = 'info') {
                const statusEl = document.getElementById('print-status');
                if (statusEl) {
                    statusEl.textContent = status;
                    const colors = {
                        'info': '#0ea5e9',
                        'success': '#059669',
                        'error': '#dc2626',
                        'warning': '#f59e0b'
                    };
                    statusEl.style.color = colors[type] || colors['info'];
                }
            }

            document.getElementById('change-printer-btn').addEventListener('click', async () => {
                try {
                    updateStatus('Loading printers...', 'info');
                    await changePrinterManually();
                } catch (error) {
                    console.error('Error changing printer:', error);
                }
            });

            document.getElementById('retry-print-btn').addEventListener('click', async () => {
                updateStatus('Retrying...', 'info');
                updatePrinterStatus('Retrying...', 'info');
                try {
                    await sendToPrinter();
                } catch (error) {
                    console.error('Retry failed:', error);
                    updatePrinterStatus('Retry failed', 'error');
                }
            });

            async function changePrinterManually() {
                if (!isQzConnected) {
                    alert('QZ Tray is not connected. Please ensure QZ Tray is running.');
                    return;
                }

                updatePrinterStatus('Loading printers...', 'info');
                try {
                    const availablePrinters = await qz.printers.find();
                    if (!availablePrinters.length) {
                        alert('No printers detected by QZ Tray');
                        updatePrinterStatus('No printers found', 'error');
                        return;
                    }

                    const newPrinter = await promptForPrinterSelection(availablePrinters);
                    if (newPrinter) {
                        storeA4Printer(newPrinter);
                        updatePrinterName(newPrinter);
                        updatePrinterStatus('Printer updated', 'success');
                    }
                } catch (error) {
                    console.error('Failed to change printer:', error);
                    updatePrinterStatus('Failed to load printers', 'error');
                }
            }

            function setSpinnerVisible(visible) {
                if (!spinnerEl) {
                    return;
                }
                spinnerEl.style.display = visible ? 'block' : 'none';
            }

            function updateStatus(text, type = 'info') {
                const colors = {
                    'info': '#0ea5e9',
                    'success': '#059669',
                    'error': '#dc2626',
                    'warning': '#f59e0b'
                };

                if (statusNode) {
                    statusNode.textContent = text;
                    statusNode.style.color = colors[type] || colors['info'];
                }

                updatePrinterStatus(text, type);

                if (type === 'success' || type === 'error' || type === 'warning') {
                    setSpinnerVisible(false);
                } else if (type === 'info') {
                    setSpinnerVisible(true);
                }
            }

            function sleep(ms) {
                return new Promise(resolve => setTimeout(resolve, ms));
            }

            function fallbackToPdf() {
                updateStatus('Opening delivery note for manual printing...', 'warning');
                window.location.replace(pdfUrl);
            }

            async function waitForQzConnection(timeout = 7000) {
                // Use global connection manager if available (from main layout)
                if (typeof window.ensureQzConnection === 'function') {
                    console.log('Using global QZ Tray connection manager');
                    try {
                        await window.ensureQzConnection(timeout);
                        updateQzStatus(true);
                        console.log('Connected via global manager');
                        return;
                    } catch (error) {
                        console.log('Global manager failed, falling back to local connection');
                    }
                }

                // Fallback: Local connection logic (for standalone pages)
                // Check if already connected - don't reconnect
                if (qz.websocket.isActive()) {
                    updateQzStatus(true);
                    console.log('QZ Tray already connected - reusing existing connection');
                    return;
                }

                // Only attempt connection if not already active
                const isConnecting = qz.websocket.isConnecting && qz.websocket.isConnecting();
                if (isConnecting) {
                    console.log('QZ Tray connection already in progress - waiting...');
                } else {
                    try {
                        qz.websocket.connect();
                    } catch (error) {
                        console.debug('Initial QZ connection attempt failed.', error);
                    }
                }

                const start = Date.now();
                while (Date.now() - start < timeout) {
                    if (qz.websocket.isActive()) {
                        updateQzStatus(true);
                        console.log('QZ Tray connection established');
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
                        updateQzStatus(true);
                        console.log('QZ Tray connection established after launch');
                        return;
                    }
                    await sleep(250);
                }

                updateQzStatus(false);
                throw new Error('Timed out waiting for QZ Tray connection');
            }

            function resolveStoredPrinter() {
                if (serverA4Printer) {
                    updatePrinterName(serverA4Printer);
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
                                updatePrinterName(value);
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
                updatePrinterStatus('Connecting...', 'info');
                await waitForQzConnection();

                let printer = resolveStoredPrinter();
                if (!printer) {
                    updatePrinterStatus('No printer configured', 'warning');
                    const availablePrinters = await qz.printers.find();
                    if (!availablePrinters.length) {
                        throw new Error('No printers detected by QZ Tray');
                    }

                    printer = await promptForPrinterSelection(availablePrinters);
                    if (!printer) {
                        throw new Error('Printer selection cancelled');
                    }
                    updatePrinterName(printer);
                }

                updateStatus('Downloading delivery note...');
                updatePrinterStatus('Downloading PDF...', 'info');
                const pdfBase64 = await fetchPdfAsBase64();

                updateStatus('Sending delivery note to printer...');
                updatePrinterStatus('Sending to printer...', 'info');

                // Ensure QZ Tray API version is available before printing (avoids undefined version errors)
                async function ensureQzVersion() {
                    try {
                        const v = await qz.api.getVersion();
                        if (v) {
                            qz.api.setVersion(v);
                            return v;
                        }
                    } catch (e) {
                        console.debug('Unable to read QZ version, falling back:', e);
                    }
                    // Fallback to a safe default; prevents versionCompare from reading undefined
                    const fallback = '2.1.0';
                    if (qz.api && typeof qz.api.setVersion === 'function') {
                        qz.api.setVersion(fallback);
                    }
                    return fallback;
                }

                await ensureQzVersion();

                if (!pdfBase64 || pdfBase64.length < 10) {
                    throw new Error('Delivery note PDF is empty or failed to load');
                }

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
                updateStatus('Delivery note sent to printer.', 'success');
                updatePrinterStatus('Printed successfully ✓', 'success');
                if (window.sessionStorage) {
                    try {
                        sessionStorage.setItem('packing_delivery_print_status', 'success');
                    } catch (error) {
                        console.debug('Unable to persist delivery note print status in sessionStorage.', error);
                    }
                }
            }

            document.addEventListener('DOMContentLoaded', async () => {
                try {
                    await sendToPrinter();
                    updateStatus('Delivery note sent to printer.', 'success');
                    updatePrinterStatus('Print completed - closing in 1s', 'success');
                    setTimeout(() => {
                        window.close();
                    }, 1000);
                } catch (error) {
                    console.error('Automatic delivery note printing failed:', error);
                    updateStatus('Printing failed - use buttons to retry or change printer', 'error');
                    updatePrinterStatus('Print failed: ' + error.message, 'error');
                    // Don't auto-close or redirect on error - let user interact with the panel
                }
            });

            // Prevent QZ Tray disconnection when window closes - keep connection alive for other tabs
            window.addEventListener('beforeunload', (event) => {
                // Do NOT disconnect QZ Tray - other windows may be using it
                console.log('Window closing - preserving QZ Tray connection for other tabs');
            });
        })();
    </script>
</body>
</html>
