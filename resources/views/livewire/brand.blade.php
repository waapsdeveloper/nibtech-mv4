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

<div id="qz-alert" style="position: fixed; width: 60%; margin: 0 4% 0 36%; z-index: 900;"></div>
<div id="qz-pin" style="position: fixed; width: 30%; margin: 0 66% 0 4%; z-index: 900;"></div>

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


            <div id="qz-connection" class="panel panel-default">
                <div class="panel-heading">
                    <button class="close tip" data-toggle="tooltip" title="Launch QZ" id="launch" href="#" onclick="launchQZ();" style="display: none;">
                        <i class="fa fa-external-link"></i>
                    </button>
                    <h3 class="panel-title">
                        Connection: <span id="qz-status" class="text-muted" style="font-weight: bold;">Unknown</span>
                    </h3>
                </div>

                <div class="panel-body">
                    <div class="btn-toolbar">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-success" onclick="startConnection();">Connect</button>
                            <button id="toggleConnectionGroup" type="button" class="btn btn-success"
                                    onclick="checkGroupActive('toggleConnectionGroup', 'connectionGroup'); $('#connectionHost').select();"
                                    data-toggle="tooltip" data-placement="bottom" title="Connect to QZ Tray running on a print server"><span class="fa fa-caret-down"></span>&nbsp;</button>
                            <button type="button" class="btn btn-warning" onclick="endConnection();">Disconnect</button>
                        </div>
                    </div>
                </div>
            </div>

            <hr />

            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">Printer</h3>
                </div>

                <div class="panel-body">
                    <div class="form-group">
                        <label for="printerSearch">Search:</label>
                        <input type="text" id="printerSearch" value="zebra" class="form-control" />
                    </div>
                    <div class="form-group">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-default btn-sm" onclick="findPrinter($('#printerSearch').val(), true);">Find Printer</button>
                            <button type="button" class="btn btn-default btn-sm" onclick="findDefaultPrinter(true);">Find Default Printer</button>
                            <button type="button" class="btn btn-default btn-sm" onclick="findPrinters();">Find All Printers</button>
                        </div>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-default btn-sm" onclick="detailPrinters();">Get Printer Details</button>
                        </div>
                    </div>
                    <hr />
                    <div class="form-group">
                        <label>Current printer:</label>
                        <div id="configPrinter">NONE</div>
                    </div>
                    <div class="form-group">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-default btn-sm" onclick="setPrinter($('#printerSearch').val());">Set To Search</button>
                            <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#askHostModal">Set To Host</button>
                        </div>
                        <button type="button" class="btn btn-warning btn-sm" onclick="clearQueue($('#printerSearch').val());">Clear Queue</button>
                    </div>
                </div>
            </div>
<button type="button" class="btn btn-success" onclick="startConnection();">Connect</button>
<button onclick="printCurrentPage()">🖨️ Test Print</button>
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


<script>


    /// Pixel Printers ///
    // function printHTML() {
    //     var config = getUpdatedConfig();
    //     var opts = getUpdatedOptions(true);
    //     var printData = [
    //         {
    //         type: 'pixel',
    //         format: 'html',
    //         flavor: 'plain',
    //         data: `<iframe src="{{ url('order/export_invoice_new/269207') }}" style="width: 100%; height: 100%;"></iframe>`,
    //         options: opts
    //         }
    //     ];

    //     qz.print(config, printData).catch(displayError);
    // }

    function printCurrentPage() {
        const htmlContent = document.documentElement.outerHTML;

        console.log(htmlContent);
        const config = qz.configs.create("RICOH MP 5054", {
            density: 300, // DPI (print quality)
            units: "px", // use pixels
            scaleContent: true, // scale to fit page
            rasterize: true, // forces pixel rendering for HTML
            pxWidth: 800, // set desired print width
            pxHeight: 1100 // set desired print height
        });

        const data = [{
            type: 'pixel',
            format: 'html',
            flavor: 'plain',
            data: htmlContent
        }];

        qz.print(config, data).then(() => {
            console.log("✅ Printed current page successfully");
        }).catch(err => {
            console.error("❌ Print error:", err);
        });
    }

    function printHTML() {
        const config = getUpdatedConfig();
        const opts = getUpdatedOptions(true);


        const htmlContent = document.documentElement.outerHTML; // full HTML of current page
        const printData = [{
            type: 'pixel',
            format: 'html',
            flavor: 'plain',
            data: htmlContent,
            // options: opts
        }];

        qz.print(config, printData).then(() => {
            console.log("✅ Printed current page successfully");
        }).catch(err => {
            console.error("❌ Print error:", err);
        });
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
