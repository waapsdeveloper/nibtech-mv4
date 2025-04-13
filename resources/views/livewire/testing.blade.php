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
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Testing</span> --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Testing</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Testing Data</h4></center>
            </div>
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
        <div class="card">
            <div class="card-header pb-0">

                <div style='float:right;'>
                    <form method='post' action='".url("testing/upload_excel")."' enctype='multipart/form-data'>
                        @csrf
                        <input type='file' name='sheet'><input type='submit' value='Upload'>
                    </form>
                </div>
            </div>
            <div class="card-body"><div class="table-responsive">

                <pre>
                    @foreach ($requests as $request)
                        @php
                            $result = json_decode($request->request);
                            $datas = $result;
                            if($datas->Imei == '' && $datas->Imei2 == '' && $datas->Serial != ''){
                                echo $request->find_serial_request($datas->Serial);
                            }
                            if(str_contains(strtolower($datas->BatchID), 'eg')){
                                $request->send_to_eg();
                            }
                            // echo "Test DateTime s: ".$result->created_at;
                            echo "<br>";
                            // echo "<a href='".url('testing/repush/'.$result->id)."'> Repush Test</a><br>";
                            print_r($result);
                            // echo json_encode($result);
                        @endphp
                    @endforeach
                    </pre>

            </div>
        </div>



    @endsection

    @section('scripts')

		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

    @endsection
