<!-- resources/views/barcode_view.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMEI Barcode</title>
</head>
<body>
        Hello
    @if ($barcode === 'IMEI not available')
        <p>{{ $barcode }}</p>
    @else
        Hello
        <img src="data:image/png;base64,{{ $barcode }}" alt="IMEI Barcode">
        Hello
    @endif
        Hello
</body>
</html>
