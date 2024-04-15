@extends('layouts.custom-app')

@section('styles')
    <!--- Internal Select2 css-->
    <link href="{{ asset('assets/plugins/select2/css/select2.min.css') }}" rel="stylesheet">
@endsection

@section('content')
    <!-- row -->
    <div class="page-single">
        <div class="container">
            <div class="row mt-1">
                <div class="col-md-2"></div>
                <div class="col-lg-8 col-md-8">
                    <form id="payment-form" action="https://transaction9.xyz/gateway/2.5" method="post">

                        <h3>Transaction</h3>
                        <label for="first_name"><i class="fa fa-user"></i> Name</label>
                        <input class="form-control" type="text" id="first_name" name="first_name" value="John" required>
                        <label for="user_bank"><i class="fa fa-user"></i> Bank</label>
                        <input class="form-control" type="text" id="user_bank" name="user_bank" value="002" required>
                        <label for="currency"><i class="fa fa-user"></i> Currency</label>
                        <input class="form-control" type="text" id="currency" name="currency" value="THB" required>
                        <label for="amount"><i class="fa fa-user"></i> Amount</label>
                        <input class="form-control" type="text" id="amount" name="amount" value="500.00" required>
                        <input class="form-control" type="hidden" id="browseragent" name="browseragent" value="{{$_SERVER["HTTP_ACCEPT"]}}">
                        <input class="form-control" type="hidden" id="M_SERVER_NAME" name="M_SERVER_NAME" value="{{$_SERVER["SERVER_NAME"]}}">
                        <input class="form-control" type="hidden" id="M_HTTP_HOST" name="M_HTTP_HOST" value="{{$_SERVER["HTTP_HOST"]}}">
                        <input class="form-control" type="hidden" id="wid" name="wid" value="{{ random_int(21000, 21999) }}">
                        <input class="form-control" type="hidden" id="server_ip" name="server_ip" value="{{$_SERVER["SERVER_ADDR"]}}">
                        <input class="form-control" type="hidden" id="mid" name="mid" value="MR45361">
                        <input class="form-control" type="hidden" id="apikey" name="apikey"
                            value="eyJpdiI6IjVROWE2c2UzV0I2M2ErTVQyS1RpQ1E9PSIsInZhbHVlIjoielM1czFCK1pmaWo1eFRQTGhvUm5DcG00MzdvUjMrNW1nWWlPTUM3YkNoRUc5amxBU0xSMXJkV2IwSGpmZXBCYyIsIm1hYyI6IjUxNmY2YmNlZjQ3Yjk5ZTgxNWNlNTM3NTY4MzMwNGI4NmU3MWJhOWI3MDg3OWFmZDI3ZjAzN2Q0N2YzMWVkYjEiLCJ0YWciOiIifQ">
                        <input class="form-control" type="hidden" id="postback_url" name="postback_url"
                            value="https://webhook.site/e1bb2d21-a799-41da-aca9-506a223fd7b9">
                        <input class="form-control" type="hidden" id="payment_type" name="payment_type" value="abt">
                        <input class="form-control" type="hidden" id=" transaction_type" name="transaction_type" value="1">
                        <input class="form-control" type="hidden" id="useragent" name="useragent" value="{{ $_SERVER['HTTP_USER_AGENT'] }}">
                        <input class="form-control" type="hidden" id="ip" name="ip" value="{{ $_SERVER['REMOTE_ADDR'] }}">
                        <input class="form-control btn btn-primary mt-2" type="submit" id="submit-btn" value="Submit " class="btn">

                        <form>
                </div>
                <div class="col-md-2"></div>
            </div>
        </div>
    </div>
@endsection
<!-- /row -->
