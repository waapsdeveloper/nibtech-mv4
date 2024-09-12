@php
    $locale = session()->get('locale', 'en');
        app()->setLocale($locale);
@endphp
<!-- main-header -->
<div class="main-header side-header sticky nav nav-item">
    <div class="main-container container-fluid">
        <div class="main-header-left ">
            <div class="responsive-logo">
                <a href="{{url('index')}}" class="header-logo">
                    <img src="{{asset('assets/img/brand/logo1.png')}}" class="mobile-logo logo-1" alt="logo" height="45">
                    <img src="{{asset('assets/img/brand/logo-white1.png')}}" class="mobile-logo dark-logo-1" alt="logo" height="45">
                </a>
            </div>
            <div class="app-sidebar__toggle" data-bs-toggle="sidebar">
                <a class="open-toggle" href="javascript:void(0);"><i class="header-icon fe fe-align-left" ></i></a>
                <a class="close-toggle" href="javascript:void(0);"><i class="header-icon fe fe-x"></i></a>
            </div>
            <div class="logo-horizontal">
                <a href="{{url('index')}}" class="header-logo">
                    <img src="{{asset('assets/img/brand/logo1.png')}}" class="mobile-logo logo-1" alt="logo"  >
                    <img src="{{asset('assets/img/brand/logo-white1.png')}}" class="mobile-logo dark-logo-1" alt="logo"  >
                </a>
            </div>
        </div>
        <div class="main-header-right">
            <button class="navbar-toggler navresponsive-toggler d-md-none ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent-4" aria-controls="navbarSupportedContent-4" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon fe fe-more-vertical "></span>
            </button>
            <div class="mb-0 navbar navbar-expand-lg navbar-nav-right responsive-navbar navbar-dark p-0">
                <div class="collapse navbar-collapse" id="navbarSupportedContent-4" >
                    <ul class="nav nav-item header-icons navbar-nav-right ms-auto" style="float:right;">
                        <li class="dropdown nav-item" style="padding-top:10px">
                            {{-- <a class="new nav-link" data-bs-target="#country-selector" data-bs-toggle="modal" href=""><svg class="header-icon-svgs" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm7.931 9h-2.764a14.67 14.67 0 0 0-1.792-6.243A8.013 8.013 0 0 1 19.931 11zM12.53 4.027c1.035 1.364 2.427 3.78 2.627 6.973H9.03c.139-2.596.994-5.028 2.451-6.974.172-.01.344-.026.519-.026.179 0 .354.016.53.027zm-3.842.7C7.704 6.618 7.136 8.762 7.03 11H4.069a8.013 8.013 0 0 1 4.619-6.273zM4.069 13h2.974c.136 2.379.665 4.478 1.556 6.23A8.01 8.01 0 0 1 4.069 13zm7.381 6.973C10.049 18.275 9.222 15.896 9.041 13h6.113c-.208 2.773-1.117 5.196-2.603 6.972-.182.012-.364.028-.551.028-.186 0-.367-.016-.55-.027zm4.011-.772c.955-1.794 1.538-3.901 1.691-6.201h2.778a8.005 8.005 0 0 1-4.469 6.201z"></path></svg></a> --}}
                        </li>
                        <li class="dropdown nav-item">
                            <a class="new nav-link theme-layout nav-link-bg" >
                                <span @if ((session('user')->hasPermission('view_cost') || session('user')->hasPermission('view_price')) && session('amount') == null)
                                    class="d-none"
                                @endif>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z"/></svg>
                                </span>
                                <span @if ((session('user')->hasPermission('view_cost') || session('user')->hasPermission('view_price')) && session('amount') == null)
                                    class="d-none"
                                @endif>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M38.8 5.1C28.4-3.1 13.3-1.2 5.1 9.2S-1.2 34.7 9.2 42.9l592 464c10.4 8.2 25.5 6.3 33.7-4.1s6.3-25.5-4.1-33.7L525.6 386.7c39.6-40.6 66.4-86.1 79.9-118.4c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C465.5 68.8 400.8 32 320 32c-68.2 0-125 26.3-169.3 60.8L38.8 5.1zM223.1 149.5C248.6 126.2 282.7 112 320 112c79.5 0 144 64.5 144 144c0 24.9-6.3 48.3-17.4 68.7L408 294.5c8.4-19.3 10.6-41.4 4.8-63.3c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3c0 10.2-2.4 19.8-6.6 28.3l-90.3-70.8zM373 389.9c-16.4 6.5-34.3 10.1-53 10.1c-79.5 0-144-64.5-144-144c0-6.9 .5-13.6 1.4-20.2L83.1 161.5C60.3 191.2 44 220.8 34.5 243.7c-3.3 7.9-3.3 16.7 0 24.6c14.9 35.7 46.2 87.7 93 131.1C174.5 443.2 239.2 480 320 480c47.8 0 89.9-12.9 126.2-32.5L373 389.9z"/></svg>
                                </span>
                            </a>
                            {{-- <a class="new nav-link theme-layout nav-link-bg layout-setting" >
                                <span class="dark-layout"><svg xmlns="http://www.w3.org/2000/svg" class="header-icon-svgs" width="24" height="24" viewBox="0 0 24 24"><path d="M20.742 13.045a8.088 8.088 0 0 1-2.077.271c-2.135 0-4.14-.83-5.646-2.336a8.025 8.025 0 0 1-2.064-7.723A1 1 0 0 0 9.73 2.034a10.014 10.014 0 0 0-4.489 2.582c-3.898 3.898-3.898 10.243 0 14.143a9.937 9.937 0 0 0 7.072 2.93 9.93 9.93 0 0 0 7.07-2.929 10.007 10.007 0 0 0 2.583-4.491 1.001 1.001 0 0 0-1.224-1.224zm-2.772 4.301a7.947 7.947 0 0 1-5.656 2.343 7.953 7.953 0 0 1-5.658-2.344c-3.118-3.119-3.118-8.195 0-11.314a7.923 7.923 0 0 1 2.06-1.483 10.027 10.027 0 0 0 2.89 7.848 9.972 9.972 0 0 0 7.848 2.891 8.036 8.036 0 0 1-1.484 2.059z"/></svg></span>
                                <span class="light-layout"><svg xmlns="http://www.w3.org/2000/svg" class="header-icon-svgs" width="24" height="24" viewBox="0 0 24 24"><path d="M6.993 12c0 2.761 2.246 5.007 5.007 5.007s5.007-2.246 5.007-5.007S14.761 6.993 12 6.993 6.993 9.239 6.993 12zM12 8.993c1.658 0 3.007 1.349 3.007 3.007S13.658 15.007 12 15.007 8.993 13.658 8.993 12 10.342 8.993 12 8.993zM10.998 19h2v3h-2zm0-17h2v3h-2zm-9 9h3v2h-3zm17 0h3v2h-3zM4.219 18.363l2.12-2.122 1.415 1.414-2.12 2.122zM16.24 6.344l2.122-2.122 1.414 1.414-2.122 2.122zM6.342 7.759 4.22 5.637l1.415-1.414 2.12 2.122zm13.434 10.605-1.414 1.414-2.122-2.122 1.414-1.414z"/></svg></span>
                            </a> --}}
                        </li>
                        <li class="dropdown main-profile-menu nav nav-item nav-link ps-lg-2">
                            <a class="" href="" data-bs-toggle="dropdown">
                                <div class="d-flex wd-100p">
                                    {{-- <div class="main-img-user"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 96 96" version="1.1">
                                        <path d="" stroke="none" fill="#ac142c" fill-rule="evenodd"/>
                                        <path d="M 37 5.909 C -11.15 21.018 -2.108 90.81 48 90.81 C 96.174 90.81 106.248 25.475 62.147 6.276 C 56.554 3.841 42.342 3.791 37 5.909 M 39 12.431 C 14.586 19.224 2.932 50.64 17.948 69.184 L 19.782 71.449 L 20.831 67.651 C 21.936 63.649 27.863 55.679 30.971 54.015 C 32.359 53.273 33.963 53.679 37.549 55.683 C 44.134 59.364 51.876 59.358 58.481 55.667 C 61.105 54.2 63.461 53 63.716 53 C 65.371 53 73.849 63.514 74.996 66.987 C 76.629 71.935 77.502 71.305 81.614 62.217 C 93.768 35.352 67.387 4.532 39 12.431 M 40.296 20.318 C 22.926 29.5 28.833 55 48.331 55 C 63.428 55 70.764 36.955 61.378 24.996 C 56.97 19.38 48.258 17.289 40.296 20.318" stroke="none" fill-rule="evenodd" style="fill: rgb(56, 202, 179);"/>
                                        </svg></div> --}}
                                    <div class="ms-3 my-auto">
                                        <h6 class="tx-15 font-weight-semibold mb-0">{{session('fname')." ".session('lname')}}&nbsp;&nbsp;&nbsp;&nbsp;</h6>
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="{{url('profile')}}"><i class="far fa-user-circle"></i></i>{{ __('locale.Profile') }}</a>
                                <a class="dropdown-item" href="{{url('logout')}}"><i class="far fa-arrow-alt-circle-left"></i> {{ __('locale.Sign Out') }}</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- /main-header -->
