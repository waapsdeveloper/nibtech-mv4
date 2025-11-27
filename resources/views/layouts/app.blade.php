<!DOCTYPE html>
<html lang="en">
	<head>

		<meta charset="UTF-8">
		<meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="Description" content="SDPOS – By SoftPeak Technologies">
		<meta name="Author" content="SoftPeak Technologies">
        <meta name="csrf-token" content="{{ csrf_token() }}">

		<!-- Title -->
        @php
            $session_url = session('url');
        @endphp
		<title> {{ session('page_title') ?? null }} - {{ env('APP_NAME')}} </title>
        <!-- FAVICON -->
        <link rel="icon" href="{{asset('assets/img/brand').'/'.env('APP_ICON')}}" type="image/x-icon"/>

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
            @media print {
                .main-header{
                    position: relative;
                }
            }

        </style>
        <script src="//unpkg.com/alpinejs" defer></script>

        <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
        @livewireStyles
        {{-- <livewire:styles /> --}}
	</head>

	<body class="ltr main-body app sidebar-mini">

		<!-- Loader -->
		<div id="global-loader">
			<img src="{{asset('assets/img/loader.svg')}}" class="loader-img" alt="Loader">
		</div>
		<!-- /Loader -->

		<!-- Page -->
		<div class="page">
			<div>

                @include('layouts.components.app-header')

                @include('layouts.components.app-sidebar')

                @include('layouts.components.sidebar-right')

			</div>

			<!-- main-content -->
			<div class="main-content app-content">

				<!-- container -->
				<div class="main-container container-fluid">

                    @yield('content')

				</div>
				<!-- Container closed -->
			</div>
			<!-- main-content closed -->

            {{-- @include('layouts.components.sidebar-right') --}}

            {{-- @include('layouts.components.modal') --}}

            @yield('modal')

            @include('layouts.components.footer')
		</div>
		<!-- End Page -->

        @include('layouts.components.scripts')

                    {{-- <livewire:chat-box /> --}}
        <livewire:chat-manager />

        {{-- <livewire:chat-sidebar /> --}}

        {{-- @livewireScripts(['includeNavigate' => true]) --}}
        @livewireScripts
        <script src="//unpkg.com/alpinejs" defer></script>
        {{-- <livewire:scripts /> --}}
        <script>

            $(document).ready(function() {
                $('.select2').select2();
            });
        </script>
        @if (request('autoprint') == 1)
            <script>
                $(document).ready(function() {
                    setTimeout(function() {
                        // Check if print button exists before clicking
                        var printBtn = document.getElementById('printBtn');
                        if (printBtn) {
                            printBtn.click();
                        } else {
                            console.warn('Print button not found on this page');
                        }
                    }, 2000);
                });
            </script>
        @endif

        {{-- Global QZ Tray Connection Manager --}}
        {{-- Note: qz-tray.js is already loaded in layouts.components.scripts --}}
        @if(env('QZ_TRAY_ENABLED', true))
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

                    // Use existing startConnection from functions.js
                    if (typeof startConnection === 'function') {
                        startConnection({ retries: 3, delay: 1 });

                        // Monitor connection success
                        var checkInterval = setInterval(function() {
                            if (qz.websocket.isActive()) {
                                clearInterval(checkInterval);
                                window.qzGlobalConnectionEstablished = true;
                                window.qzGlobalConnectionInProgress = false;
                                console.log('✓ Global QZ Tray connection established successfully');
                            }
                        }, 500);

                        // Timeout after 10 seconds
                        setTimeout(function() {
                            clearInterval(checkInterval);
                            if (!qz.websocket.isActive()) {
                                window.qzGlobalConnectionInProgress = false;
                                console.log('⚠ Global QZ Tray connection timeout (will retry when needed)');
                            }
                        }, 10000);
                    } else {
                        // Fallback to direct connection
                        qz.websocket.connect()
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

                        // Use existing startConnection from functions.js
                        if (typeof startConnection === 'function' && !qz.websocket.isActive()) {
                            try {
                                startConnection({ retries: 2, delay: 1 });
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
        @else
        <script>
            // QZ Tray is disabled - provide stub functions for compatibility
            window.qzConnectionManager = {
                isConnected: function() { return false; },
                ensureConnection: function() { return Promise.reject(new Error('QZ Tray is disabled')); },
                reconnect: function() { console.log('QZ Tray is disabled'); }
            };
            window.isQzConnected = function() { return false; };
            window.ensureQzConnection = function() { return Promise.reject(new Error('QZ Tray is disabled')); };
            console.log('QZ Tray is disabled via QZ_TRAY_ENABLED environment variable');
        </script>
        @endif
    </body>
</html>
