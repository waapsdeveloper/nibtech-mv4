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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/rsvp/4.8.5/rsvp.min.js"></script>

        <!-- Load SHA support -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jsSHA/2.4.2/sha.js"></script>

        <!-- Load QZ Tray -->
        <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.1.0/qz-tray.js"></script>

        <!-- Set up QZ Tray configuration -->
        <script>
            // 1. Set certificate promise (test mode)
            qz.security.setCertificatePromise(() => Promise.resolve("-----BEGIN CERTIFICATE-----\nFAKE\n-----END CERTIFICATE-----"));

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


    {{-- <script>

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
        function openCashDrawer() {
            const config = qz.configs.create("RICOH MP 5054"); // replace with actual printer name
            const data = ['\x1B\x70\x00\x19\xFA']; // Pulse drawer pin 2 (common command)

            qz.print(config, data).then(() => {
                console.log("Cash drawer opened.");
            }).catch(err => console.error(err));
        }

        // After order success
        setTimeout(() => {
            printReceipt();
            openCashDrawer();
        }, 1000);

        qz.printers.getDefault().then(console.log).catch(console.error);

    </script> --}}
    @endsection
