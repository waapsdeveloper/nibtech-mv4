@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="card custom-card">
                <div class="card-body d-md-flex">
                    <div class="my-md-auto mt-4 prof-details">
                        <h4 class="font-weight-semibold ms-md-4 ms-0 mb-1 pb-0">{{ session('fname') . " " . session('lname') }}</h4>
                        <p class="text-muted ms-md-4 ms-0 mb-2"><span><i class="fa fa-envelope me-2"></i></span><span class="font-weight-semibold me-2">{{ __('locale.Email') }}:</span><span>{{ $admin->email }}</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-sm">
        <div class="col-lg-12 col-md-12">
            <div class="custom-card main-content-body-profile">
                <div class="tab-content">
                    <div class="main-content-body border-top-0">
                        <div class="card">
                            <div class="card-body border-0">
                                <div class="mb-4 main-content-label">{{ __('locale.Personal Information') }}</div>
                                <form class="form-horizontal" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <div class="row row-sm">
                                            <div class="col-md-3">
                                                <label class="form-label">{{ __('locale.First Name') }}</label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" name="first_name" placeholder="{{ __('locale.First Name') }}" value="{{ $admin->first_name }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="row row-sm">
                                            <div class="col-md-3">
                                                <label class="form-label">{{ __('locale.Last Name') }}</label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" name="last_name" placeholder="{{ __('locale.Last Name') }}" value="{{ $admin->last_name }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4 main-content-label">{{ __('locale.Contact Info') }}</div>
                                    <div class="form-group">
                                        <div class="row row-sm">
                                            <div class="col-md-3">
                                                <label class="form-label">{{ __('locale.Email') }}</label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="email" class="form-control" name="email" value="{{ $admin->email }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="row row-sm">
                                            <div class="col-md-3">
                                                <label class="form-label">{{ __('locale.Password') }}</label>
                                            </div>
                                            <div class="col-md-8 col-sm-8">
                                                <input type="password" class="form-control" value="**********" disabled>
                                            </div>
                                            <div class="col-md-1">
                                                <a class="btn btn-primary" data-bs-toggle="modal" href="#modaldemo1">{{ __('locale.Change') }}</a>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Add section for 2FA --}}
                                    <div class="mt-4 mb-4 main-content-label">Two-Factor Authentication</div>

                                    @if ($admin->google2fa_secret)
                                        @if (isset($qrCodeImage))

                                        <div class="form-group">
                                            <label for="qrCode">Scan the QR Code with Google Authenticator</label>
                                            <div>
                                                {!! $qrCodeImage !!}
                                            </div>
                                        </div>

                                        <div class="form-group mt-3">
                                            <label for="secretCode">Or enter this code manually in your authenticator app:</label>
                                            <input type="text" class="form-control" value="{{ $secret }}" readonly>
                                        </div>

                                        @endif
                                        <div class="form-group mt-3">
                                            <a href="{{ route('disable2fa') }}" class="btn btn-danger">{{ __('Disable 2FA') }}</a>
                                        </div>
                                    @else
                                        <div class="form-group mt-3">
                                            <a href="{{ route('enable2fa') }}" class="btn btn-success">{{ __('Enable 2FA') }}</a>
                                        </div>
                                    @endif
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
