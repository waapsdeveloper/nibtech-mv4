{{-- @extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Two Factor Authentication</div>

                <div class="card-body">
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('2fa.verify') }}">
                        @csrf

                        <div class="form-group row">
                            <label for="one_time_password" class="col-md-4 col-form-label text-md-right">One Time Password</label>

                            <div class="col-md-6">
                                <input id="one_time_password" type="text" class="form-control @error('one_time_password') is-invalid @enderror" name="one_time_password" required>

                                @error('one_time_password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    Verify
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr>

                    <form method="POST" action="{{ route('2fa.setup') }}">
                        @csrf

                        <div class="form-group row">
                            <label for="secret" class="col-md-4 col-form-label text-md-right">Secret</label>

                            <div class="col-md-6">
                                <input id="secret" type="text" class="form-control @error('secret') is-invalid @enderror" name="secret" value="{{ $secret }}" required>

                                @error('secret')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    Setup 2FA
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-3">
                        <p>Scan the following QR code with your Google Authenticator app:</p>
                        <div>{!! $inlineUrl !!}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection --}}
