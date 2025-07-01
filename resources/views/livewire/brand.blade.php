@extends('layouts.app')

    @section('styles')
    <style>
        .rows{
            border: 1px solid #016a5949;
        }
        .columns{
            background-color:#016a5949;
            padding-top:5px
        }
        .childs{
            padding-top:5px
        }
    </style>
    @endsection
<br>
    @section('content')
        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">Brand</span>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                                Brand
                        </li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
        <br>
        <div class="tx-right">

                <a href="{{url('add-brand')}}" class="btn btn-success float-right"><i class="mdi mdi-plus"></i> Add Brand</a>
        </div>
        <br>
        @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
            <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
            <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
        </div>
        <br>
        @php
        session()->forget('success');
        @endphp
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
                <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            <script>
                alert("{{session('error')}}");
            </script>
        <br>
        @php
        session()->forget('error');
        @endphp
        @endif
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Brands</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>{{ __('locale.S.No') }}</b></small></th>
                                        <th><small><b>Name</b></small></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                    @endphp
                                    @foreach ($brands as $brand)
                                    @php
                                        $i++;
                                    @endphp
                                        <tr>
                                            <td title="{{$brand->id}}">{{$i}}</td>
                                            <td>{{$brand->name}}</td>
                                            <td><center><a href="edit-brand/{{$brand->id}}" class="text text-success w-100 vh-100">{{ __('locale.Edit') }}</a></center></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <br>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<button onclick="printReceipt()">🖨️ Test Print</button>
        @php
            session()->forget('success');
        @endphp
    @endsection

    @section('scripts')

		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

        <!-- Load RSVP (required for promises) -->
        {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/rsvp/4.8.5/rsvp.min.js"></script> --}}

        <!-- Load SHA support -->
        {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jsSHA/2.4.2/sha.js"></script> --}}

        <!-- Load QZ Tray -->
        <script src="{{asset('assets/js/qz-tray.js')}}"></script>

        <!-- Set up QZ Tray configuration -->
        {{-- <script>
            // 1. Set certificate promise (test mode)
            qz.security.setCertificatePromise(() => Promise.resolve(`
            -----BEGIN CERTIFICATE-----
MIIECzCCAvOgAwIBAgIGAZfB2PAEMA0GCSqGSIb3DQEBCwUAMIGiMQswCQYDVQQG
EwJVUzELMAkGA1UECAwCTlkxEjAQBgNVBAcMCUNhbmFzdG90YTEbMBkGA1UECgwS
UVogSW5kdXN0cmllcywgTExDMRswGQYDVQQLDBJRWiBJbmR1c3RyaWVzLCBMTEMx
HDAaBgkqhkiG9w0BCQEWDXN1cHBvcnRAcXouaW8xGjAYBgNVBAMMEVFaIFRyYXkg
RGVtbyBDZXJ0MB4XDTI1MDYyOTE3MTgyOVoXDTQ1MDYyOTE3MTgyOVowgaIxCzAJ
BgNVBAYTAlVTMQswCQYDVQQIDAJOWTESMBAGA1UEBwwJQ2FuYXN0b3RhMRswGQYD
VQQKDBJRWiBJbmR1c3RyaWVzLCBMTEMxGzAZBgNVBAsMElFaIEluZHVzdHJpZXMs
IExMQzEcMBoGCSqGSIb3DQEJARYNc3VwcG9ydEBxei5pbzEaMBgGA1UEAwwRUVog
VHJheSBEZW1vIENlcnQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDR
686K4jD1vFTSw0dKH/Drd78qqgO9U3Lgi6YB5t50tfDFLrpEQUSW1gGzOk82mBG+
CW7cBgVF9lEyMGhhp+/mdWOoC1UARum6QavrO9bzKxjka0H7EifG8Cd2+6kY1ybW
ASmWSgQoR0plwi0oNheD+XfpKz3zPkcxEsjmJ/5jSCFuuLpZwZA+8M5Pzrt2MmX5
n3HFKQjX5GLXzhRiz6F7+8fShlb+tOieKcPevAkR2c0Tjco+z5DyEccA2aAirWF0
NDM7dDtAOdXsmoXC7GZenOWV0meza7wrUVsTOZTO29noGo/ihcqbm19U6+gp5dQT
vhBZ+wDyZLzcQT4Fk3chAgMBAAGjRTBDMBIGA1UdEwEB/wQIMAYBAf8CAQEwDgYD
VR0PAQH/BAQDAgEGMB0GA1UdDgQWBBQTkdY7/8UDc4v37lTC3AIM32HLrzANBgkq
hkiG9w0BAQsFAAOCAQEAxvwTagwcyB3QBRhNFWG6punNWFCqpeRqmToDvYJzLYyf
xF6PNCSdTTp+TYGBNHo95JwI6sva1YWEtEHThtVW2ABpsIacf0jDP2BfC1rzjxae
Y3z3rlLsjQmrdhs+3BCvye0PskivzzyDc6TAN85be5ctchXgQHZYfMpvfd49i5dF
KoGF9j1MP0Qi0B7wfcYvb2RYgjTIirmDMIVSC6gZX3XVgw3rH8jC4VZs/UJwEmZ0
AwrGTEE6z5JBr4+P7zwEk++9CACWtvxyO+/VzsiCm+L7xOxPV12qgj/m5aCucHeZ
rxiqK6VMem9QmZu5k4CjZc3qPkuC2PK4+wCp13vwmQ==
-----END CERTIFICATE-----
            `));

            // 2. Set signature promise (test mode)
            qz.security.setSignaturePromise((toSign) => {
                return Promise.resolve("UNSIGNED"); // ⚠️ Only for development!
            });

            // 3. Connect to QZ Tray
            function connectQZ() {
                qz.websocket.connect().then(() => {
                    console.log("✅ Connected to QZ Tray");
                }).catch(err => {
                    console.error("❌ QZ Tray connection error:", err);
                });
            }

            // Optional: auto-connect when page is ready
            document.addEventListener('DOMContentLoaded', connectQZ);
        </script>


    <script>

        function printReceipt() {
            const config = qz.configs.create("RICOH MP 5054"); // replace with actual printer name

            const data = [
                '\x1B\x40', // Reset
                'BritainTech POS\n',
                '----------------------\n',
                'Product A x1  £50.00\n',
                'Product B x2  £30.00\n',
                '----------------------\n',
                'Total:       £110.00\n',
                '\nThank you!\n\n\n\n',
                '\x1B\x69' // Full cut
            ];

            qz.print(config, data).then(() => {
                console.log("Printed Successfully");
            }).catch(err => console.error(err));
        }
        // function openCashDrawer() {
        //     const config = qz.configs.create("RICOH MP 5054"); // replace with actual printer name
        //     const data = ['\x1B\x70\x00\x19\xFA']; // Pulse drawer pin 2 (common command)

        //     qz.print(config, data).then(() => {
        //         console.log("Cash drawer opened.");
        //     }).catch(err => console.error(err));
        // }

        // Test Print Function
        // function testPrint() {
        //     // disconnectQZ();
        //     let printerName;
        //     qz.printers.getDefault()
        //         .then((printer) => {
        //             printerName = printer;
        //             const config = qz.configs.create(printerName);
        //             const data = [
        //                 { type: 'raw', format: 'plain', data: '*** Test Print ***\nHello from QZ Tray!\n\n' }
        //             ];
        //             return qz.print(config, data);
        //         })
        //         .then(() => {
        //             console.log("✅ Print sent successfully");
        //             disconnectQZ();
        //         })
        //         .catch((err) => {
        //             console.error("❌ Error during print", err);
        //         });
        // }

        // Disconnect
        function disconnectQZ() {
            if (qz.websocket.isActive()) {
                qz.websocket.disconnect();
            }
        }
        // After order success
        // setTimeout(() => {
        //     printReceipt();
        //     // openCashDrawer();
        // }, 1000);

        qz.printers.getDefault().then(console.log).catch(console.error);

    </script> --}}
<script>
    async function initQZTrayWithCachedPrinter() {
        await connectQZ();

        let cachedPrinter = localStorage.getItem("qz_selected_printer");
        if (!cachedPrinter) {
            const printers = await qz.printers.find();
            let selection = prompt("Select printer:\n" + printers.join("\n"));

            if (!selection || !printers.includes(selection)) {
                alert("Printer not selected or not found.");
                throw new Error("Printer not selected");
            }

            localStorage.setItem("qz_selected_printer", selection);
            cachedPrinter = selection;
        }

        return qz.configs.create(cachedPrinter);
    }

    function connectQZ() {
        if (!qz.websocket.isActive()) {
            return qz.websocket.connect().then(() => {
                console.log("✅ Connected to QZ Tray");
            });
        } else {
            return Promise.resolve();
        }
    }

    function disconnectQZ() {
        if (qz.websocket.isActive()) {
            qz.websocket.disconnect();
        }
    }

    function printReceipt() {
        initQZTrayWithCachedPrinter().then(config => {
            const data = [{
        type: 'pixel',
        format: 'html',
        flavor: 'plain',
        data: '<h1>Hello JavaScript!</h1>'
    }];
            return qz.print(config, data);
        }).then(() => {
            console.log("✅ Receipt printed.");
        }).catch(err => {
            console.error("❌ Print error:", err);
        });
    }

    function openCashDrawer() {
        initQZTrayWithCachedPrinter().then(config => {
            const data = ['\x1B\x70\x00\x19\xFA']; // drawer pulse
            return qz.print(config, data);
        }).then(() => {
            console.log("✅ Cash drawer opened.");
        }).catch(err => {
            console.error("❌ Cash drawer error:", err);
        });
    }

    function resetPrinterSelection() {
        localStorage.removeItem("qz_selected_printer");
        alert("Printer selection reset.");
    }
</script>

    {{-- <script>

// const qz = require("qz-tray");

qz.websocket.connect().then(() => {
    return qz.printers.find();
}).then((printers) => {
    console.log(printers);
    let config = qz.configs.create('RICOH MP 5054'); // replace with your printer name
    return qz.print(config, [{
        type: 'pixel',
        format: 'html',
        flavor: 'plain',
        data: '<h1>Hello JavaScript!</h1>'
    }]);
}).then(() => {
    return qz.websocket.disconnect();
}).then(() => {
    // process.exit(0);
}).catch((err) => {
    console.error(err);
    // process.exit(1);
});

    </script> --}}

    @endsection
