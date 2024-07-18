<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Invoice</title>
    <style>
        body {
            margin: 0;
            padding: 0;
        }
        iframe {
            width: 100%;
            height: 100vh;
            border: none;
        }
    </style>
</head>
<body>
    <iframe src="{{ url('/export_bulksale_invoice/' . $order_id) }}"></iframe>
</body>
</html>
