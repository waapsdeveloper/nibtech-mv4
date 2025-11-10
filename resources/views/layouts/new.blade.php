<!DOCTYPE html>
<html lang="en">
	<head>

		<meta charset="UTF-8">
		<meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="Description" content="Nowa – Laravel Bootstrap 5 Admin & Dashboard Template">
		<meta name="Author" content="Spruko Technologies Private Limited">
        <meta name="csrf-token" content="{{ csrf_token() }}">

		{{-- <meta name="Keywords" content="admin dashboard, admin dashboard laravel, admin panel template, blade template, blade template laravel, bootstrap template, dashboard laravel, laravel admin, laravel admin dashboard, laravel admin panel, laravel admin template, laravel bootstrap admin template, laravel bootstrap template, laravel template"/> --}}

		<!-- Title -->
        @php
            $session_url = session('url');
        @endphp
		<title> {{ session('page_title') ?? null }} - {{ env('APP_NAME') }} </title>

		<!-- JQUERY JS -->
		<script src="{{asset('assets/plugins/jquery/jquery.min.js')}}"></script>
        @include('layouts.components.styles')
        <style>

            :root {
                --primary-bg-color: #{{ env('PRIMARY_BG_COLOR', '052468') }};
                --primary-color: #{{ env('PRIMARY_COLOR', '052468') }};
                --primary-bg-hover: #{{ env('PRIMARY_BG_HOVER', '143272') }};
            }
            .form-floating>.form-control,
            .form-floating>.form-control-plaintext {
            padding: 0rem 0.75rem;
            }

            .form-floating>.form-control,
            .form-floating>.form-control-plaintext,
            .form-floating>.form-select {
            height: calc(2.5rem + 2px);
            line-height: 1;
            }

            .form-floating>label {
            padding: 0.5rem 0.75rem;
            }
        </style>
        @livewireStyles
	</head>

	<body class="ltr main-body app sidebar-mini">

		<!-- Loader -->
		<div id="global-loader">
			<img src="{{asset('assets/img/loader.svg')}}" class="loader-img" alt="Loader">
		</div>
		<!-- /Loader -->

		<!-- Page -->
		<div class="page">

                    @yield('content')


		</div>
		<!-- End Page -->

        @include('layouts.components.scripts')

        @livewireScripts

        {{-- Global QZ Tray Connection Manager --}}
        <script src="{{ asset('assets/js/qz-tray.js') }}"></script>
        <script>
            /**
             * Global QZ Tray Connection Manager
             * Establishes and maintains a single WebSocket connection across all pages
             * Prevents multiple redundant connections and improves performance
             */
            (function() {
                // Global flag to track connection state
                window.qzGlobalConnectionEstablished = false;
                window.qzGlobalConnectionInProgress = false;

                /**
                 * Configure QZ Tray security certificates
                 */
                function setupQzCertificates() {
                    if (typeof qz === 'undefined') {
                        console.warn('QZ Tray library not loaded');
                        return Promise.reject('QZ Tray not available');
                    }

                    return Promise.all([
                        fetch("{{ asset('qz-certs/certificate.crt') }}").then(r => r.text()),
                        fetch("{{ asset('private-key.pem') }}").then(r => r.text())
                    ]).then(function(results) {
                        var certificate = results[0];
                        var privateKey = results[1];

                        qz.security.setCertificatePromise(function(resolve) {
                            resolve(certificate);
                        });

                        qz.security.setSignatureAlgorithm("SHA512");
                        qz.security.setSignaturePromise(function(toSign) {
                            return function(resolve, reject) {
                                try {
                                    var pk = KEYUTIL.getKey(privateKey);
                                    var sig = new KJUR.crypto.Signature({"alg": "SHA512withRSA"});
                                    sig.init(pk);
                                    sig.updateString(toSign);
                                    var hex = sig.sign();
                                    resolve(stob64(hextorstr(hex)));
                                } catch (err) {
                                    console.error("Signature error:", err);
                                    reject(err);
                                }
                            };
                        });

                        console.log('✓ QZ Tray certificates configured');
                        return true;
                    }).catch(function(err) {
                        console.error('Failed to load QZ Tray certificates:', err);
                        throw err;
                    });
                }

                /**
                 * Initialize QZ Tray connection once per session
                 * This will be called automatically when the page loads
                 */
                function initializeGlobalQzConnection() {
                    // Skip if QZ Tray library not loaded
                    if (typeof qz === 'undefined' || !qz.websocket) {
                        console.log('QZ Tray library not loaded - skipping global connection');
                        return;
                    }

                    // Skip if already connected
                    if (qz.websocket.isActive()) {
                        console.log('✓ QZ Tray already connected globally');
                        window.qzGlobalConnectionEstablished = true;
                        return;
                    }

                    // Skip if connection is in progress
                    if (window.qzGlobalConnectionInProgress) {
                        console.log('QZ Tray connection already in progress...');
                        return;
                    }

                    window.qzGlobalConnectionInProgress = true;
                    console.log('Initializing global QZ Tray connection...');

                    // Configure certificates first, then connect
                    setupQzCertificates()
                        .then(function() {
                            return qz.websocket.connect();
                        })
                        .then(function() {
                            window.qzGlobalConnectionEstablished = true;
                            window.qzGlobalConnectionInProgress = false;
                            console.log('✓ Global QZ Tray connection established successfully');
                        })
                        .catch(function(err) {
                            window.qzGlobalConnectionInProgress = false;
                            console.log('⚠ Global QZ Tray connection failed (will retry when needed):', err.message || err);
                        });
                }

                /**
                 * Ensure connection is active
                 * Child pages can call this to verify connection before printing
                 */
                window.ensureQzConnection = function(timeout = 5000) {
                    return new Promise(function(resolve, reject) {
                        // Already connected
                        if (typeof qz !== 'undefined' && qz.websocket && qz.websocket.isActive()) {
                            console.log('✓ Using existing global QZ Tray connection');
                            resolve();
                            return;
                        }

                        // Try to connect
                        console.log('Ensuring QZ Tray connection...');
                        if (typeof qz === 'undefined' || !qz.websocket) {
                            reject(new Error('QZ Tray library not available'));
                            return;
                        }

                        // Check if connection is in progress
                        const isConnecting = qz.websocket.isConnecting && qz.websocket.isConnecting();
                        if (!isConnecting && !qz.websocket.isActive()) {
                            try {
                                setupQzCertificates()
                                    .then(() => qz.websocket.connect())
                                    .catch(err => console.debug('Connection attempt failed:', err));
                            } catch (error) {
                                console.debug('Connection attempt failed:', error);
                            }
                        }

                        // Wait for connection
                        const startTime = Date.now();
                        const checkInterval = setInterval(function() {
                            if (qz.websocket.isActive()) {
                                clearInterval(checkInterval);
                                console.log('✓ QZ Tray connection ready');
                                resolve();
                            } else if (Date.now() - startTime > timeout) {
                                clearInterval(checkInterval);
                                reject(new Error('QZ Tray connection timeout'));
                            }
                        }, 200);
                    });
                };

                /**
                 * Get connection status
                 */
                window.isQzConnected = function() {
                    return typeof qz !== 'undefined' &&
                           qz.websocket &&
                           qz.websocket.isActive();
                };

                // Initialize connection when page loads
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initializeGlobalQzConnection);
                } else {
                    // DOM already loaded
                    setTimeout(initializeGlobalQzConnection, 100);
                }

                // Keep connection alive - prevent disconnection on page unload
                window.addEventListener('beforeunload', function() {
                    // Do NOT disconnect - keep connection for other tabs/pages
                    console.log('Page unloading - preserving global QZ Tray connection');
                });

                // Expose connection manager to window
                window.qzConnectionManager = {
                    isConnected: window.isQzConnected,
                    ensureConnection: window.ensureQzConnection,
                    reconnect: initializeGlobalQzConnection
                };

                console.log('Global QZ Tray Connection Manager initialized');
            })();
        </script>
    </body>
</html>
