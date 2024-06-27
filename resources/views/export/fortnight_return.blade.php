<!-- resources/views/invoices/invoice.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Fortnight Report</title>

    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            font-size: 14px;
            /* line-height: 10px; */
        }

        .invoice-container {
            max-width: 800px;
            /* margin: 20px auto; */
            /* padding: 20px; */
            /* box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); */
        }

        .company-info, .invoice-header, .customer-details, .order-details, .order-items, .total-amount {
            /* margin-bottom: 20px; */
        }

        .invoice-header h2, .customer-details h3, .order-details h3, .total-amount h3 {
            /* border-bottom: 2px solid #333; */
            /* padding-bottom: 5px;
            margin-bottom: 10px; */
        }

        .order-items table {
            /* width: 100%;
            border-collapse: collapse;
            margin-top: 20px; */
        }


        .total-amount {
            /* text-align: right; */
        }
        /* @page {
            size: landscape;

        } */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            border: 1px solid #016a5949;
            padding: 8px;
            text-align: left;
        }

    </style>
</head>
<body>

    {{-- <iframe src="{{ $order->delivery_note_url }}"></iframe> --}}
    <div class="invoice-container">


        <table class="table table-bordered table-hover mb-0 text-md-nowrap" id="datatable">
            <thead>
                <tr>
                    <th><small><b>No</b></small></th>
                    <th width="100"><small><b>Order Detail</b></small></th>
                    <th><table><tr>
                    <th><small><b>Old Variation --> New Variation</b></small></th>
                    <th><small><b>Reason</b></small></th>
                    <th><small><b>Processor DateTime</b></small></th>
                    </tr></table></th>
                </tr>
            </thead>
            <tbody>
                @php
                    $i = 0;
                @endphp
                @foreach ($latest_items as $item)


                        <tr>

                            <td>{{ $i + 1 }}</td>
                            <td align="center" width="130">
                                <span title="Order Number">{{ $item->reference_id }}</span><br>
                                <span title="Customer">{{ $item->refund_order->customer->first_name." ".$item->refund_order->customer->last_name }}</span><br>
                                <span title="Product">
                                    <strong>{{ $item->refund_order->order_items[0]->variation->sku }}</strong><br>
                                    {{$item->refund_order->order_items[0]->variation->product->model ?? "Model not defined"}} - {{(isset($item->refund_order->order_items[0]->variation->storage_id)?$item->refund_order->order_items[0]->variation->storage_id->name . " - " : null)}}<br>
                                    {{(isset($item->refund_order->order_items[0]->variation->color_id)?$item->refund_order->order_items[0]->variation->color_id->name. " - ":null)}} <strong><u>{{ $item->refund_order->order_items[0]->variation->grade_id->name }}</u></strong><br>
                                </span>
                                <span title="IMEI | Invoiced By | Tested By">
                                    {{ $item->stock->imei.$item->stock->serial_number }}
                                    @isset($item->refund_order->processed_by) | {{ $item->refund_order->admin->first_name[0] }} | @endisset
                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                </span><br>
                                <span title="Vendor | Lot">{{ $item->stock->order->customer->first_name ?? "Purchase Order Missing"}} | {{$item->stock->order->reference_id ?? null}}</span><br>
                                <span title="Invoiced at">{{ $item->refund_order->processed_at }}</span><br>
                                <span title="Refunded at">{{ $item->created_at }}</span>
                            </td>
                            <td>
                                <table>
                                    @foreach ($item->stock->stock_operations as $index => $operation)
                                        <tr>
                                            <td title="{{ $operation->id }}">
                                                @if ($operation->old_variation ?? false)
                                                    <strong>{{ $operation->old_variation->sku }}</strong>{{ " - " . $operation->old_variation->product->model . " - " . (isset($operation->old_variation->storage_id)?$operation->old_variation->storage_id->name . " - " : null) . (isset($operation->old_variation->color_id)?$operation->old_variation->color_id->name. " - ":null)}} <strong><u>{{ (isset($operation->old_variation->grade_id)?$operation->old_variation->grade_id->name:null)}} </u></strong>
                                                @endif
                                            -->
                                                @if ($operation->new_variation ?? false)
                                                    <strong>{{ $operation->new_variation->sku }}</strong>{{ " - " . $operation->new_variation->product->model . " - " . (isset($operation->new_variation->storage_id)?$operation->new_variation->storage_id->name . " - " : null) . (isset($operation->new_variation->color_id)?$operation->new_variation->color_id->name. " - ":null)}} <strong><u>{{ $operation->new_variation->grade_id->name ?? "Grade Missing" }}</u></strong>
                                                @endif
                                            </td>
                                            <td>{{ $operation->description }}</td>
                                            <td>{{ $operation->admin->first_name ?? null }} {{ $operation->created_at }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @php
                        $i ++;
                    @endphp
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
