<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Label with History</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            width: 62mm;
            height: 100mm;
            margin: 0;
            padding: 0;
        }
        .label {
            border: 1px solid #000;
            padding: 5px;
            width: 100%;
            height: 100%;
        }
        h4, p {
            margin: 5px 0;
        }
        .content {
            font-size: 10px;
        }
        .barcode {
            margin-top: 10px;
            text-align: center;
        }
        table {
            font-size: 9px;
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 2px;
            border: 1px solid #000;
        }
    </style>
</head>
<body>
    <div class="label">
        <h4>Product Label</h4>

        <!-- Product Basic Information -->
        <div class="content">
            <p><strong>Order ID:</strong> {{ $order->reference_id }}</p>
            <p><strong>Type:</strong> {{ $order->order_type->name }}</p>
            <p><strong>Customer:</strong> {{ $order->customer->first_name ?? '' }} {{ $order->customer->last_name ?? '' }}</p>
            <p><strong>SKU:</strong> {{ $variation->sku }}</p>
            <p><strong>Product:</strong> {{ $variation->product->model }}</p>
            <p><strong>Grade:</strong> {{ $variation->grade->name ?? 'N/A' }}</p>
            <p><strong>Storage:</strong> {{ $variation->storage->name ?? 'N/A' }}</p>
            <p><strong>Color:</strong> {{ $variation->color->name ?? 'N/A' }}</p>
        </div>

        <!-- Movement History Table -->
        <h4>History</h4>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Admin</th>
                    <th>Date</th>
                    <th>Old SKU</th>
                    <th>New SKU</th>
                    <th>IMEI</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td>{{ $movement->type }}</td>
                        <td>{{ $movement->admin->first_name ?? 'N/A' }}</td>
                        <td>{{ $movement->created_at }}</td>
                        <td>{{ $movement->old_variation->sku ?? 'N/A' }}</td>
                        <td>{{ $movement->new_variation->sku ?? 'N/A' }}</td>
                        <td>{{ $movement->stock->imei ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- IMEI Barcode Section -->
        <div class="barcode">
            <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($imei, 'C39') }}" alt="barcode" />
            <p>{{ $imei }}</p>
        </div>
    </div>
</body>
</html>
