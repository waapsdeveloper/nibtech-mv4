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
        {{-- Note: qz-tray.js is already loaded in layouts.components.scripts --}}
        @php($isQzEnabled = true)
        <script src="{{ asset('assets/js/functions.js') }}"></script>
        <script>
            /**
             * Global QZ Tray Connection Manager
             * Establishes and maintains a single WebSocket connection across all pages
             * Uses existing functions.js for certificate configuration
             * Only initializes if QZ_TRAY_ENABLED env variable is true
             */
            (function() {
                // Check if QZ Tray manager has already been initialized (prevent duplicate initialization)
                if (window.qzConnectionManagerInitialized) {
                    console.log('QZ Tray Connection Manager already initialized - skipping duplicate');
                    return;
                }

                // Mark as initialized
                window.qzConnectionManagerInitialized = true;

                // Global flag to track connection state
                window.qzGlobalConnectionEstablished = false;
                window.qzGlobalConnectionInProgress = false;

                // Preferred connection configuration (force ws:// and known hosts)
                const connectionConfig = {
                    host: ['localhost', '127.0.0.1', 'localhost.qz.io'],
                    usingSecure: (window.location && window.location.protocol === 'https:'),
                    port: {
                        secure: [8181, 8282, 8383, 8484],
                        insecure: [8182, 8283, 8384, 8485],
                        portIndex: 0
                    },
                    retries: 3,
                    delay: 0.5
                };

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

                    // Ensure connection options include localhost/127.0.0.1 and allow insecure fallback
                    try {
                        qz.websocket.connectConfig = Object.assign(
                            {},
                            qz.websocket.connectConfig || {},
                            connectionConfig
                        );
                    } catch (e) {
                        console.debug('Unable to set QZ connection config', e);
                    }

                    // Helper to check if connection is ready (active socket)
                    function isConnectionReady() {
                        return qz.websocket.isActive();
                    }

                    // Skip if already connected and fully ready
                    if (isConnectionReady()) {
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

                    // Use existing startConnection from functions.js
                    if (typeof startConnection === 'function') {
                        startConnection(connectionConfig);

                        // Monitor connection success - check for full readiness
                        var checkInterval = setInterval(function() {
                            if (isConnectionReady()) {
                                clearInterval(checkInterval);
                                window.qzGlobalConnectionEstablished = true;
                                window.qzGlobalConnectionInProgress = false;
                                console.log('✓ Global QZ Tray connection established successfully');
                            }
                        }, 500);

                        // Timeout after 15 seconds
                        setTimeout(function() {
                            clearInterval(checkInterval);
                            if (!isConnectionReady()) {
                                window.qzGlobalConnectionInProgress = false;
                                console.log('⚠ Global QZ Tray connection timeout (will retry when needed)');
                            }
                        }, 15000);
                    } else {
                        // Fallback to direct connection
                        qz.websocket.connect()
                            .then(function() {
                                // Wait for sendData to be available
                                var readyCheck = setInterval(function() {
                                    if (isConnectionReady()) {
                                        clearInterval(readyCheck);
                                        window.qzGlobalConnectionEstablished = true;
                                        window.qzGlobalConnectionInProgress = false;
                                        console.log('✓ Global QZ Tray connection established successfully');
                                    }
                                }, 100);

                                setTimeout(function() {
                                    clearInterval(readyCheck);
                                    if (!window.qzGlobalConnectionEstablished) {
                                        window.qzGlobalConnectionInProgress = false;
                                        console.log('⚠ QZ connection not ready after connect');
                                    }
                                }, 3000);
                            })
                            .catch(function(err) {
                                window.qzGlobalConnectionInProgress = false;
                                console.log('⚠ Global QZ Tray connection failed (will retry when needed):', err.message || err);
                            });
                    }
                }

                /**
                 * Ensure connection is active
                 * Child pages can call this to verify connection before printing
                 */
                window.ensureQzConnection = function(timeout = 5000) {
                    return new Promise(function(resolve, reject) {
                        // Check if connection is fully ready (not just active, but sendData method is available)
                        function isConnectionReady() {
                            return typeof qz !== 'undefined'
                                && qz.websocket
                                && qz.websocket.isActive();
                        }

                        // Already connected and ready
                        if (isConnectionReady()) {
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

                        // Use existing startConnection from functions.js
                        if (typeof startConnection === 'function' && !qz.websocket.isActive()) {
                            try {
                                startConnection(Object.assign({}, connectionConfig, { retries: 2, delay: 1 }));
                            } catch (error) {
                                console.debug('Connection attempt failed:', error);
                            }
                        }

                        // Wait for connection to be fully ready
                        const startTime = Date.now();
                        const checkInterval = setInterval(function() {
                            if (isConnectionReady()) {
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
                           qz.websocket.isActive() &&
                           qz.websocket.connection &&
                           typeof qz.websocket.connection.sendData === 'function';
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
