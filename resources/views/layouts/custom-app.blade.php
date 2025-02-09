<!DOCTYPE html>
<html lang="en">
	<head>

		<meta charset="UTF-8">
		<meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta http-equiv="refresh" content="{{ config('session.lifetime') * 60 }}">
		{{-- <meta name="Description" content="Nowa – Laravel Bootstrap 5 Admin & Dashboard Template">
		<meta name="Author" content="Spruko Technologies Private Limited">
		<meta name="Keywords" content="admin dashboard, admin dashboard laravel, admin panel template, blade template, blade template laravel, bootstrap template, dashboard laravel, laravel admin, laravel admin dashboard, laravel admin panel, laravel admin template, laravel bootstrap admin template, laravel bootstrap template, laravel template"/> --}}

		<!-- Title -->
		<title> {{ env('APP_NAME') }} – Admin Penal </title>

		<!-- JQUERY JS -->
		<script src="{{asset('assets/plugins/jquery/jquery.min.js')}}"></script>
		@include('layouts.components.custom-styles')
        <style>

            :root {
                    --primary-bg-color: #{{ env('PRIMARY_BG_COLOR', '052468') }};
                    --primary-color: #{{ env('PRIMARY_COLOR', '052468') }};
                    --primary-bg-hover: #{{ env('PRIMARY_BG_HOVER', '143272') }};
            }

        </style>

    </head>
	<body class="ltr error-page1">

		@yield('class')

            <!-- Loader -->
            <div id="global-loader">
                <img src="{{asset('assets/img/loader.svg')}}" class="loader-img" alt="Loader">
            </div>
            <!-- /Loader -->


            <div class="square-box">
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
            </div>
            <div class="page" >

                @yield('content')

            </div>
        </div>

		@include('layouts.components.custom-scripts')

    </body>
</html>
