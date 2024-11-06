<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IMEI Label</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9pt; margin: 5px; }
        .container { padding: 10px; }
        .header { text-align: center; font-weight: bold; margin-bottom: 10px; }
        .info-section, .order-section, .movement-section { margin-bottom: 10px; }
        .info-section strong { display: block; font-size: 10pt; margin-bottom: 5px; }
        .barcode { text-align: center; margin: 10px 0; }
        .section-title { font-weight: bold; margin-top: 5px; }
        .details { margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {{ $variation->product->model ?? 'Model Unknown' }} - {{ $variation->storage_id->name ?? '' }} - {{ $variation->color_id->name ?? '' }}
        </div>

        <div class="info-section">
            <strong>IMEI:</strong> {{ $imei }}
        </div>

        @if($imei !== 'N/A')
            <div class="barcode">
                <!-- Barcode placeholder; actual barcode will be rendered in the controller -->
                <img src="data:image/png;base64,{{ $barcodeImage }}" alt="IMEI Barcode" />
            </div>
        @endif

        <div class="movement-section">
            <div class="section-title">Stock Movement History:</div>
            <div class="details">
                {{ $movementDetails ?? 'No movement history available.' }}
            </div>
        </div>

        <div class="order-section">
            <div class="section-title">Orders History:</div>
            @foreach($orders as $item)
                <div class="details">
                    Order: {{ $item->order->reference_id ?? 'Unknown' }} - Type: {{ $item->order->order_type->name ?? 'N/A' }} - Customer: {{ $item->order->customer->first_name ?? 'Unknown' }} - Status: {{ $item->order->order_status->name ?? 'N/A' }}
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
