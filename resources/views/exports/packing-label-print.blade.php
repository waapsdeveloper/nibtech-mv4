<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Printing Label</title>
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
        <p id="status">Preparing shipping label...</p>
    </div>

    <script src="{{ asset('assets/js/qz-tray.js') }}"></script>
    <script src="{{ asset('assets/js/functions.js') }}"></script>
    <script>
        (function () {
            const statusNode = document.getElementById('status');
            const pdfBase64 = @json($pdfBase64);
            const autoCloseDelay = 1200;

            function updateStatus(text) {
                if (statusNode) {
                    statusNode.textContent = text;
                }
            }

            function sleep(ms) {
                return new Promise(resolve => setTimeout(resolve, ms));
            }

            function base64ToBlob(base64, mimeType) {
                const byteCharacters = atob(base64);
                const byteArrays = [];
                const sliceSize = 1024;

                for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
                    const slice = byteCharacters.slice(offset, offset + sliceSize);
                    const byteNumbers = new Array(slice.length);
                    for (let i = 0; i < slice.length; i++) {
                        byteNumbers[i] = slice.charCodeAt(i);
                    }
                    byteArrays.push(new Uint8Array(byteNumbers));
                }

                return new Blob(byteArrays, { type: mimeType });
            }

            function fallbackToPdf() {
                updateStatus('Opening label for manual printing...');
                try {
                    const blob = base64ToBlob(pdfBase64, 'application/pdf');
                    const url = URL.createObjectURL(blob);
                    window.location.replace(url);
                    setTimeout(() => URL.revokeObjectURL(url), 12000);
                } catch (error) {
                    console.error('Failed to open fallback PDF:', error);
                    document.body.innerHTML = '<p style="padding:16px;text-align:center;">Unable to auto print. Please download the label manually.</p>';
                }
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

                throw new Error('Timed out waiting for QZ Tray connection');
            }

            function resolveStoredPrinter(keys) {
                const stores = [];
                try { stores.push(window.localStorage); } catch (error) { /* ignore */ }
                try { stores.push(window.sessionStorage); } catch (error) { /* ignore */ }

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
                            console.debug('Storage access failed for key', key, error);
                        }
                    }
                }

                return null;
            }

            function storeLabelPrinter(printerName) {
                const normalized = (function(name) {
                    if (name && typeof name === 'object') {
                        if (name.name) {
                            return name.name;
                        }
                        if (name.file) {
                            return name.file;
                        }
                        if (name.host && name.port) {
                            return name.host + ':' + name.port;
                        }
                        try {
                            return JSON.stringify(name);
                        } catch (error) {
                            return String(name);
                        }
                    }
                    return String(name);
                })(printerName);

                const keys = ['Label_Printer', 'Sticker_Printer'];
                const storages = [];
                try { storages.push(window.localStorage); } catch (error) { /* ignore */ }
                try { storages.push(window.sessionStorage); } catch (error) { /* ignore */ }

                storages.forEach(storage => {
                    if (!storage) {
                        return;
                    }
                    keys.forEach(key => {
                        try {
                            storage.setItem(key, normalized);
                        } catch (error) {
                            console.debug('Unable to persist printer preference', key, error);
                        }
                    });
                });
            }

            function promptForPrinterSelection(printers) {
                return new Promise(resolve => {
                    const overlay = document.createElement('div');
                    overlay.style.position = 'fixed';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.width = '100%';
                    overlay.style.height = '100%';
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
                    title.textContent = 'Select Label Printer';
                    title.style.margin = '0 0 16px';
                    title.style.fontSize = '20px';
                    title.style.color = '#0f172a';

                    const description = document.createElement('p');
                    description.textContent = 'Choose the printer to use for shipping labels. The selection will be remembered for future packing sessions.';
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

                    printers.forEach(printer => {
                        const option = document.createElement('option');
                        option.value = printer;
                        option.textContent = printer;
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

            async function resolvePrinter() {
                const preferredKeys = [
                    'Sticker_Printer',
                    'Label_Printer',
                    'DHL_Printer',
                    'Shipping_Printer',
                    'Default_Printer'
                ];

                const stored = resolveStoredPrinter(preferredKeys);
                if (stored) {
                    return stored;
                }

                try {
                    const defaultPrinter = await qz.printers.getDefault();
                    if (defaultPrinter) {
                        storeLabelPrinter(defaultPrinter);
                        return defaultPrinter;
                    }
                } catch (error) {
                    console.debug('No default printer detected via QZ Tray.', error);
                }

                try {
                    const printers = await qz.printers.find();
                    if (printers && printers.length) {
                        updateStatus('Waiting for printer selection...');
                        const choice = await promptForPrinterSelection(printers);
                        if (choice) {
                            storeLabelPrinter(choice);
                            return choice;
                        }
                        throw new Error('Printer selection cancelled');
                    }
                } catch (error) {
                    console.debug('Printer enumeration failed.', error);
                }

                return null;
            }

            async function sendToPrinter() {
                if (typeof qz === 'undefined') {
                    throw new Error('QZ Tray library is not loaded');
                }

                updateStatus('Connecting to QZ Tray...');
                try {
                    await waitForQzConnection();
                } catch (error) {
                    console.debug('Attempting to launch QZ Tray client.');
                    try {
                        window.location.assign('qz:launch');
                    } catch (launchError) {
                        console.debug('Unable to invoke qz:launch protocol.', launchError);
                    }
                    await waitForQzConnection(12000);
                }

                const printer = await resolvePrinter();
                if (!printer) {
                    throw new Error('No printer configured for labels');
                }

                updateStatus('Sending label to printer...');
                const config = qz.configs.create(printer, {
                    size: { width: 102, height: 210 },
                    units: 'mm',
                    copies: 1,
                    margins: { top: 0, right: 0, bottom: 0, left: 0 }
                });

                await qz.print(config, [{
                    type: 'pdf',
                    format: 'base64',
                    data: pdfBase64
                }]);
            }

            document.addEventListener('DOMContentLoaded', async () => {
                try {
                    await sendToPrinter();
                    updateStatus('Label sent to printer.');

                                storeLabelPrinter(printer);
                    setTimeout(() => window.close(), autoCloseDelay);
                } catch (error) {
                    console.error('Automatic label printing failed:', error);
                    fallbackToPdf();
                }
            });
        })();
    </script>
</body>
</html>
