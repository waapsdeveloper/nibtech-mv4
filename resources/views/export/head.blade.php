<!-- resources/views/invoices/invoice.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Invoice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background-color: #fff; /* Set a white background for printing */
        }

        .invoice-header {
            text-align: center;
            margin-bottom: 20px;
        }

        /* Add your styling for the company-info and right-details sections */
        .company-info {
            position: absolute;
            top: 0;
            left: 0;
            margin: 5px 20px; /* Adjust the margin as needed */
        }

        .right-details {
            position: absolute;
            top: 0;
            right: 0;
            margin: 20px; /* Adjust the margin as needed */
        }

        /* Other existing styles... */

        .invoice-details {
            margin-bottom: 30px;
        }

        .invoice-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .invoice-items th, .invoice-items td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .total-amount {
            text-align: right;
            margin-top: 20px;
            font-weight: bold;
        }

        /* Additional styles for printing */
        @media print {
            body {
                margin: 0;
            }

            .invoice-header,
            .invoice-details,
            .invoice-items,
            .total-amount {
                margin: 0;
            }

            .invoice-items th, .invoice-items td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
