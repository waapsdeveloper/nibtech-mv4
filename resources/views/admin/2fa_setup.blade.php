<div class="form-group">
    <label for="qrCode">{{ __('Scan the QR Code with Google Authenticator') }}</label>
    <div>
        {!! $qrCodeImage !!}
    </div>
</div>

<div class="form-group mt-3">
    <label for="secretCode">{{ __('Or enter this code manually in your authenticator app:') }}</label>
    <input type="text" class="form-control" value="{{ $secret }}" readonly>
</div>
