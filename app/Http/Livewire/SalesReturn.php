<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
    use Livewire\Component;
    use App\Models\Admin_model;
    use App\Models\Variation_model;
    use App\Models\Products_model;
    use App\Models\Stock_model;
    use App\Models\Order_model;
    use App\Models\Order_item_model;
    use App\Models\Order_status_model;
    use App\Models\Customer_model;
    use App\Models\Currency_model;
    use App\Models\Country_model;
    use App\Models\Storage_model;
    use Carbon\Carbon;
    use App\Exports\OrdersExport;
    use App\Exports\PickListExport;
    use App\Exports\LabelsExport;
    use App\Exports\DeliveryNotesExport;
    use App\Exports\OrdersheetExport;
    use Illuminate\Support\Facades\DB;
    use Maatwebsite\Excel\Facades\Excel;
    use TCPDF;
    use App\Mail\InvoiceMail;
use App\Models\Color_model;
use App\Models\Grade_model;
use App\Models\Order_issue_model;
use App\Models\Stock_operations_model;
use Illuminate\Support\Facades\Mail;


class SalesReturn extends Component
{

    public function mount()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }
    }
    public function render()
    {

        // $data['latest_reference'] = Order_model::where('order_type_id',4)->orderBy('reference_id','DESC')->first()->reference_id;
        $data['vendors'] = Customer_model::where('is_vendor',1)->pluck('first_name','id');
        $data['order_statuses'] = Order_status_model::get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }

        $data['orders'] = Order_model::select(
            'orders.id',
            'orders.reference_id',
            // DB::raw('SUM(order_items.price) as total_price'),
            // DB::raw('COUNT(order_items.id) as total_quantity'),
            // DB::raw('COUNT(CASE WHEN stock.status = 1 THEN order_items.id END) as available_stock'),
            'orders.created_at')
        ->where('orders.order_type_id', 4)
        // ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        // ->join('stock', 'order_items.stock_id', '=', 'stock.id')
        ->when(request('start_date'), function ($q) {
            return $q->where('orders.created_at', '>=', request('start_date'));
        })
        ->when(request('end_date'), function ($q) {
            return $q->where('orders.created_at', '<=', request('end_date') . " 23:59:59");
        })
        ->when(request('order_id'), function ($q) {
            return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
        })
        // ->groupBy('orders.id', 'orders.reference_id', 'orders.created_at')
        ->orderBy('orders.reference_id', 'desc') // Secondary order by reference_id
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        // dd($data['orders']);
        return view('livewire.return')->with($data);
    }
    public function return_approve($order_id){
        $order = Order_model::find($order_id);
        $order->tracking_number = request('tracking_number');
        $order->status = 3;
        $order->save();

        return redirect()->back();
    }
    public function delete_order($order_id){

        $stock = Stock_model::where(['order_id'=>$order_id,'status'=>2])->first();
        if($stock != null){
            session()->put('error', "Order cannot be deleted");
            return redirect()->back();
        }

        $items = Order_item_model::where('order_id',$order_id)->get();
        foreach($items as $orderItem){
            if($orderItem->stock){
                // Access the variation through orderItem->stock->variation
                $variation = $orderItem->stock->variation;

                $variation->stock += 1;
                Stock_model::find($orderItem->stock_id)->update([
                    'status' => 2
                ]);
            }
            $orderItem->delete();
        }
        Order_model::where('id',$order_id)->delete();
        Order_issue_model::where('order_id',$order_id)->delete();
        session()->put('success', 'Order deleted successfully');
        return redirect()->back();

    }
    public function delete_order_item($item_id){

        $orderItem = Order_item_model::find($item_id);

        if($orderItem->stock->status == 2){
            session()->put('error', "Order Item cannot be deleted");
            return redirect()->back();
        }
        // Access the variation through orderItem->stock->variation
        $variation = $orderItem->stock->variation;

        $variation->stock -= 1;
        $variation->save();

        // No variation record found or product_id and sku are both null, delete the order item

        // $orderItem->stock->delete();
        Stock_model::find($orderItem->stock_id)->update(['status'=>2]);
        $orderItem->delete();
        // $orderItem->forceDelete();

        session()->put('success', 'Stock deleted successfully');

        return redirect()->back();

    }
    public function return_detail($order_id){

        $data['imei'] = request('imei');
        if (request('imei')) {
            if (ctype_digit(request('imei'))) {
                $i = request('imei');
                $s = null;
            } else {
                $i = null;
                $s = request('imei');
            }

            $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();

            $data['products'] = Products_model::orderBy('model','asc')->get();
            $data['colors'] = Color_model::all();
            $data['storages'] = Storage_model::all();
            $data['grades'] = Grade_model::all();
            if (request('imei') == '' || !$stock || $stock->status == null) {
                session()->put('error', 'IMEI Invalid / Not Found');
                // return redirect()->back(); // Redirect here is not recommended
                return view('livewire.imei', $data); // Return the Blade view instance with data
            }
            $sale_status = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>$stock->purchase_item->id])->first();
            if($stock->status == 1){
                if($sale_status != null){
                    $stock->status = 2;
                    $stock->save();
                }else{
                }
            }
            if($stock->status == 2){
                if($sale_status == null){
                    $stock->status = 1;
                    $stock->save();
                }else{
                }
            }
            if($sale_status != null){
                $item = Order_item_model::where('stock_id',$stock->id)->orderBy('id','desc')->first();
                if(in_array($item->order->order_type_id, [3,5])){
                    $data['restock']['order_id'] = $order_id;
                    $data['restock']['reference_id'] = $item->order->reference_id;
                    $data['restock']['stock_id'] = $stock->id;
                    $data['restock']['price'] = $item->price;
                    $data['restock']['linked_id'] = $item->id;
                }
            }
            $stock_id = $stock->id;
            $orders = Order_item_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
            $data['stock'] = $stock;
            $data['orders'] = $orders;
            // dd($orders);

            $stocks = Stock_operations_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
            if($stocks->count() > 0){
                $data['stocks'] = $stocks;
            }

        }
        $variations = Variation_model::with([
            'stocks' => function ($query) use ($order_id) {
                $query->whereHas('order_item', function ($query) use ($order_id) {
                    $query->where('order_id', $order_id);
                });
            },
            'stocks.order_item'
        ])
        ->whereHas('stocks', function ($query) use ($order_id) {
            $query->whereHas('order_item', function ($query) use ($order_id) {
                $query->where('order_id', $order_id);
            });
        })
        ->orderBy('grade', 'desc')
        ->get();

        // Remove variations with no associated stocks
        $variations = $variations->filter(function ($variation) {
            return $variation->stocks->isNotEmpty();
        });

        $data['variations'] = $variations;

        $data['all_variations'] = Variation_model::where('grade',9)->get();
        $data['order'] = Order_model::find($order_id);
        $data['order_id'] = $order_id;
        $data['currency'] = $data['order']->currency_id->sign;


        // echo "<pre>";
        // // print_r($items->stocks);
        // print_r($items);

        // echo "</pre>";
        // dd($data['variations']);
        return view('livewire.return_detail')->with($data);

    }
    public function add_return(){

        $order = Order_model::create([
            'reference_id' => 11001,
            'status' => 1,
            'currency' => 4,
            'order_type_id' => 4,
            'processed_by' => session('user_id')
        ]);

        return redirect(url('return/detail').'/'.$order->id);
    }
    private function insert_return_item($products, $storages, $order, $n, $c, $i, $s, $g = null, $dr = null){

        $names = explode(" ",$n);
        $last = end($names);
        if(in_array($last, $storages)){
            $gb = array_search($last,$storages);
            array_pop($names);
            $n = implode(" ", $names);
        }else{
            $gb = 0;
        }

        $stock = Stock_model::firstOrNew(['imei' => $i, 'serial_number' => $s]);
        if($stock->id != null){
            if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
            $issue['data']['row'] = $dr;
            $issue['data']['name'] = $n;
            $issue['data']['storage'] = $st;
            $issue['data']['imei'] = $i.$s;
            $issue['data']['cost'] = $c;
            $issue['data']['stock_id'] = $stock->id;
            if($stock->order_id == $order->id && $stock->status == 1){
                $issue['message'] = 'Duplicate IMEI';
            }else{
                if($stock->status != 2){
                    $issue['message'] = 'IMEI Available In Inventory';
                }else{
                    $issue['message'] = 'IMEI Rereturn';
                }
            }

        }else{
            if(in_array(strtolower($n), array_map('strtolower',$products)) && ($i != null || $s != null)){
                $product = array_search(strtolower($n), array_map('strtolower',$products));
                $storage = $gb;

                // echo $product." ".$grade." ".$storage." | ";

                $variation = Variation_model::firstOrNew(['product_id' => $product, 'grade' => 9, 'storage' => $storage]);

                $variation->stock += 1;
                $variation->status = 1;
                $variation->save();

                $stock->product_id = $product;
                $stock->variation_id = $variation->id;
                $stock->added_by = session('user_id');
                $stock->order_id = $order->id;
                $stock->status = 1;
                $stock->save();

                $order_item = Order_item_model::firstOrNew(['order_id' => $order->id, 'variation_id' => $variation->id, 'stock_id' => $stock->id]);
                $order_item->quantity = 1;
                $order_item->price = $c;
                $order_item->status = 3;
                $order_item->save();


            }else{
                if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                if($n != null){
                    $error = $n . " " . $st . " " . $i.$s . " || ";
                    $issue['data']['row'] = $dr;
                    $issue['data']['name'] = $n;
                    $issue['data']['storage'] = $st;
                    $issue['data']['imei'] = $i.$s;
                    $issue['data']['cost'] = $c;
                    $issue['data']['stock_id'] = '';
                    if($i == null && $s == null){
                        $issue['message'] = 'IMEI Not Found';
                    }else{
                        $issue['message'] = 'Product Name Not Accepted';
                    }

                }
            }
        }


        if($issue != []){
            Order_issue_model::create([
                'order_id' => $order->id,
                'data' => json_encode($issue['data']),
                'message' => $issue['message'],
            ]);
        }

    }
    public function add_return_item($order_id){
        $return = request('return');
        // print_r($return);
        if($order_id == $return['order_id']){
            $variation = Variation_model::firstOrNew(['product_id' => $return['product'], 'storage' => $return['storage'], 'color' => $return['color'], 'grade' => $return['grade']]);

            $variation->stock += 1;
            $variation->status = 1;
            $variation->save();

            $stock = Stock_model::find($return['stock_id']);

            if($stock->id){
                $item = Order_item_model::where(['order_id'=>$order_id, 'stock_id' => $stock->id])->first();
                // print_r($stock);
                if($item == null){



                    $order_item = new Order_item_model();
                    $order_item->order_id = $order_id;
                    $order_item->reference_id = $return['reference_id'];
                    $order_item->variation_id = $variation->id;
                    $order_item->stock_id = $stock->id;
                    $order_item->quantity = 1;
                    $order_item->price = $return['price'];
                    $order_item->status = 3;
                    $order_item->linked_id = $return['linked_id'];
                    $order_item->admin_id = session('user_id');
                    $order_item->save();

                    print_r($order_item);

                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock->id,
                        'old_variation_id' => $stock->variation_id,
                        'new_variation_id' => $variation->id,
                        'description' => $return['description'],
                        'admin_id' => session('user_id'),
                    ]);

                    $stock->variation_id = $variation->id;
                    $stock->status = 1;
                    $stock->save();

                    session()->put('success','Item added');
                }else{
                    session()->put('error','Item already added');
                }
            }else{
                session()->put('error','Stock Not Found');
            }

        }else{
            session()->put('error',"Don't ANGRY ME");
        }

        return redirect()->back();

    }
    public function remove_issues(){
        // dd(request('ids'));
        $ids = request('ids');
        $issues = Order_issue_model::whereIn('id',$ids)->get();
        if(request('remove_entries') == 1){
            $issues->delete();
        }
        if(request('insert_variation') == 1){
            $variation = request('variation');
            foreach($issues as $issue){
                $data = json_decode($issue->data);
                // echo $variation." ".$data->imei." ".$data->cost;

                if($this->add_return_item($issue->order_id, $data->imei, $variation, $data->cost) == 1){
                    $issue->delete();
                }

            }
        }
        return redirect()->back();

    }
    public function export_invoice($orderId)
    {

        // Find the order
        $order = Order_model::with('customer', 'order_items')->find($orderId);

        // Generate PDF for the invoice content
        $data = [
            'order' => $order,
            'customer' => $order->customer,
            'orderItems' => $order->order_items,
        ];

        // Create a new TCPDF instance
        $pdf = new TCPDF();

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle('Invoice');
        // $pdf->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('times', '', 12);

        // Additional content from your view
        $html = view('export.invoice', $data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        // dd($pdfContent);
        // Send the invoice via email
        Mail::to($order->customer->email)->send(new InvoiceMail($data));

        // Optionally, save the PDF locally
        // file_put_contents('invoice.pdf', $pdfContent);

        // Get the PDF content
        // $pdf->Output('', 'I');

        $pdfContent = $pdf->Output('', 'S');
        // Return a response or redirect

        // Pass the PDF content to the view
        return view('livewire.show_pdf')->with(['pdfContent'=> $pdfContent, 'delivery_note'=>$order->delivery_note_url]);
    }
    public function dispatch($id)
    {
        $order = Order_model::where('id',$id)->first();
        $bm = new BackMarketAPIController();

        // $orderObj = $bm->getOneOrder($order->reference_id);
        $orderObj = $this->updateBMOrder($order->reference_id, false, null, true);
        $tester = request('tester');
        $sku = request('sku');
        $imeis = request('imei');

        // Initialize an empty result array
        $skus = [];

        // Loop through the numbers array
        foreach ($sku as $index => $number) {
            // If the value doesn't exist as a key in the skus array, create it
            if (!isset($skus[$number])) {
                $skus[$number] = [];
            }
            // Add the current number to the skus array along with its index in the original array
            $skus[$number][$index] = $number;
        }
        // print_r(request('imei'));
        if($orderObj->state == 3){
            foreach(request('imei') as $i => $imei){

                $variant = Variation_model::where('sku',$sku[$i])->first();
                if($variant->storage != null){
                    $storage2 = $variant->storage_id->name . " - ";
                }else{
                    $storage2 = null;
                }
                if($variant->color != null){
                    $color2 = $variant->color_id->name . " - ";
                }else{
                    $color2 = null;
                }

                $serial_number = null;
                $imei = trim($imei);
                if(!ctype_digit($imei)){
                    $serial_number = $imei;
                    $imei = null;

                }else{

                    if(strlen($imei) != 15){

                        session()->put('error', "IMEI invalid");
                        return redirect()->back();
                    }
                }

                $stock[$i] = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();
                // if (ctype_digit($imei)) {

                //     $stock[$i] = Stock_model::where('imei',trim($imei))->first();
                    if(!$stock[$i]){
                        session()->put('error', "Stock not Found");
                        return redirect()->back();

                    }
                    if($stock[$i]->status != 1){
                        session()->put('error', "Stock Already Sold");
                        return redirect()->back();
                    }
                    if($stock[$i]->order->status == 2){
                        session()->put('error', "Stock List Awaiting Approval");
                        return redirect()->back();
                    }
                    // if($stock[$i]){
                        if($stock[$i]->variation->storage != null){
                            $storage = $stock[$i]->variation->storage_id->name . " - ";
                        }else{
                            $storage = null;
                        }
                        if($stock[$i]->variation->color != null){
                            $color = $stock[$i]->variation->color_id->name . " - ";
                        }else{
                            $color = null;
                        }
                        if(($stock[$i]->variation->product_id == $variant->product_id) || ($variant->product_id == 144 && $stock[$i]->variation->product_id == 229)){
                        }else{
                            session()->put('error', "Product Model not matched");
                            return redirect()->back();
                        }
                        if(($stock[$i]->variation->storage == $variant->storage) || ($variant->storage == 5 && $stock[$i]->variation->storage == 0 && $variant->product->brand == 2) || ($variant->product_id == 78 && $variant->storage == 4 && $stock[$i]->variation->storage == 5)){
                        }else{
                            session()->put('error', "Product Storage not matched");
                            return redirect()->back();
                        }
                        echo "<script>
                        if (confirm('System Model: " . $stock[$i]->variation->product->model . " - " . $storage . $color . $stock[$i]->variation->grade_id->name . "\\nRequired Model: " . $variant->product->model . " - " . $storage2 . $color2 . $variant->grade_id->name . "')) {
                            // User clicked OK, do nothing or perform any other action
                        } else {
                            // User clicked Cancel, redirect to the previous page
                            window.history.back();
                        }
                        </script>";
                    // }
                    // $serial = false;
                    // dd($stock[$i]);
                // }else{

                //     $stock[$i] = Stock_model::where('serial_number',trim($imei))->first();
                //     if(!$stock[$i]){
                //         session()->put('error', "Stock not Found");
                //         return redirect()->back();
                //     }
                //     if($stock[$i]->status != 1){
                //         session()->put('error', "Stock Already Sold");
                //         return redirect()->back();
                //     }
                //     if($stock[$i]->order->status == 2){
                //         session()->put('error', "Stock List Awaiting Approval");
                //         return redirect()->back();
                //     }
                //     if($stock[$i]){
                //         if($stock[$i]->variation->storage != null){
                //             $storage = $stock[$i]->variation->storage_id->name . " - ";
                //         }else{
                //             $storage = null;
                //         }
                //         if($stock[$i]->variation->color != null){
                //             $color = $stock[$i]->variation->color_id->name . " - ";
                //         }else{
                //             $color = null;
                //         }
                //         echo "<script>
                //         if (confirm('System Model: " . $stock[$i]->variation->product->model . " - " . $storage . $color . $stock[$i]->variation->grade_id->name . "\\nRequired Model: " . $variant->product->model . " - " . $storage2 . $color2 . $variant->grade_id->name . "')) {
                //             // User clicked OK, do nothing or perform any other action
                //         } else {
                //             // User clicked Cancel, redirect to the previous page
                //             window.history.back();
                //         }
                //         </script>";
                //     }
                //     $serial = true;
                // }
                // if(!$stock[$i]){
                //     if($serial == false){
                //         $add_imei = trim($imei);
                //         $add_serial = null;
                //     }else{
                //         $add_imei = null;
                //         $add_serial = trim($imei);
                //     }
                //     $stock[$i] = Stock_model::firstOrNew(['imei'=>$add_imei,'serial_number'=>$add_serial]);
                //     $stock[$i]->variation_id = $variant->id;
                // }
                // if($stock[$i]){
                    $stock[$i]->tester = $tester[$i];
                    $stock[$i]->status = 2;
                    $stock[$i]->save();
                // }

                // $orderObj = $bm->getOneOrder($order->reference_id);
                $orderObj = $this->updateBMOrder($order->reference_id, true, $tester[$i], true);
            }
            $order = Order_model::find($order->id);
            $items = $order->order_items;
            if(count($items) > 1 || $items[0]->quantity > 1){
                $indexes = 0;
                foreach($skus as $each_sku){
                    if($indexes == 0 && count($each_sku) == 1){
                        $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
                    }elseif($indexes == 0 && count($each_sku) > 1){
                        // dd("Hello");
                        $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],false,$orderObj->tracking_number,$serial_number);
                    }elseif($indexes > 0 && count($each_sku) == 1){
                        $detail = $bm->orderlineIMEI($order->reference_id,trim($imeis[0]),$serial_number);
                    }else{

                    }
                    $indexes++;
                }
            }else{
                $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
            }
            print_r($detail);

            if(is_string($detail)){
                session()->put('error', $detail);
                return redirect()->back();
            }


            foreach ($skus as $each) {
                $inde = 0;
                foreach ($each as $idt => $s) {
                    $variation = Variation_model::where('sku',$s)->first();
                    $item = Order_item_model::where(['order_id'=>$id, 'variation_id'=>$variation->id])->first();
                    if ($inde != 0) {

                        $new_item = new Order_item_model();
                        $new_item->order_id = $id;
                        $new_item->variation_id = $item->variation_id;
                        $new_item->quantity = $item->quantity;
                        $new_item->status = $item->status;
                    }else{
                        $new_item = $item;
                    }
                    $new_item->price = $item->price/count($each);
                    if($stock[$idt]){
                    $new_item->stock_id = $stock[$idt]->id;
                    // $new_item->linked_id = Order_item_model::where(['order_id'=>$stock[$idt]->order_id,'stock_id'=>$stock[$idt]->id])->first()->id;
                    }
                    $new_item->save();
                    $inde ++;
                }
            }

            // print_r($d[6]);
        }

        $orderObj = $this->updateBMOrder($order->reference_id, true);
        $order = Order_model::find($order->id);
        if(!isset($detail)){

            $invoice_url = url(session('url').'export_invoice').'/'.$id;
            // JavaScript to open two tabs and print
            echo '<script>
            var newTab1 = window.open("'.$order->delivery_note_url.'", "_blank");
            var newTab2 = window.open("'.$invoice_url.'", "_blank");

            newTab2.onload = function() {
                newTab2.print();
            };
            newTab1.onload = function() {
                newTab1.print();
            };

            window.location.href = document.referrer;
            </script>';

        }
        if(!$detail->orderlines){
            dd($detail);
        }
        if($detail->orderlines[0]->imei == null && $detail->orderlines[0]->serial_number  == null){
            $content = "Hi, here are the IMEIs/Serial numbers for this order. \n";
            foreach ($imeis as $im) {
                $content .= $im . "\n";
            }
            $content .= "Regards \n".session('fname');

            // JavaScript code to automatically copy content to clipboard
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const el = document.createElement('textarea');
                    el.value = '$content';
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                });
            </script>";


            // JavaScript to open two tabs and print
            echo '<script>
            window.open("https://backmarket.fr/bo_merchant/orders/all?orderId='.$order->reference_id.'", "_blank");
            window.location.href = document.referrer;
            </script>';
        }else{

            $invoice_url = url(session('url').'export_invoice').'/'.$id;
            // JavaScript to open two tabs and print
            echo '<script>
            var newTab1 = window.open("'.$order->delivery_note_url.'", "_blank");
            var newTab2 = window.open("'.$invoice_url.'", "_blank");

            newTab2.onload = function() {
                newTab2.print();
            };
            newTab1.onload = function() {
                newTab1.print();
            };

            window.location.href = document.referrer;
            </script>';
        }


    }
    public function delete_item($id){
        Order_item_model::find($id)->delete();
        return redirect()->back();
    }
    public function correction(){
        $item = Order_item_model::find(request('correction')['item_id']);
        if($item->order->processed_at > Carbon::now()->subHour(2) || session('user_id') == 1){

            $imei = request('correction')['imei'];
            $serial_number = null;
            if(!ctype_digit($imei)){
                $serial_number = $imei;
                $imei = null;
            }

            $stock = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();
            if(!$stock){
                session()->put('error', 'Stock not found');
                return redirect()->back();
            }
            $stock->variation_id = $item->variation_id;
            $stock->tester = request('correction')['tester'];
            $stock->added_by = session('user_id');
            if($stock->status == 1){
                $stock->status = 2;
            }
            $stock->save();

            $item->stock_id = $stock->id;
            $item->linked_id = $stock->return_item->id;
            $item->save();

            $message = "Hi, here is the correct IMEI/Serial number for this order. \n".$imei.$serial_number." ".$stock->tester."\n Regards, \n" . session('fname');
            session()->put('success', $message);
            session()->put('copy', $message);
        }else{
            session()->put('error', 'Update deadline exceeded');
        }
        return redirect()->back();
    }

    public function sales_return($id)
    {
        $item = Order_item_model::find($id);
        $order = Order_model::find($item->order_id);

        $return = new Order_model();
        $return->reference_id = $order->reference_id;
        $return->customer_id = $order->customer_id;
        $return->order_type_id = 4;
        $return->currency = $order->currency;
        $return->price = $order->price;
        $return->delivery_note_url = request('message');
        $return->status = request('status');
        $return->save();

        $order->update(['reference_id'=>null]);

        $return_item = new Order_item_model();
        $return_item->order_id = $return->id;
        $return_item->reference_id = $item->reference_id;
        $return_item->variation_id = $item->variation_id;
        $return_item->stock_id = $item->stock_id;
        $return_item->quantity = 1;
        $return_item->price = $item->price;
        $return_item->status = request('status');
        $return_item->linked_id = $item->id;
        $return_item->save();

        $item->update(['reference_id'=>null]);

        if($item->stock->status == 2){
            $item->stock->update(['status'=>3]);
        }elseif($item->stock->status == null){
            $item->stock->delete();
        }else{}


    }

    public function recheck($order_id, $refresh = false, $invoice = false, $tester = null){

        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');

        $orderObj = $bm->getOneOrder($order_id);

        $order_model->updateOrderInDB($orderObj, $invoice, $bm, $currency_codes, $country_codes);

        $order_item_model->updateOrderItemsInDB($orderObj, $tester, $bm);
        if($refresh == true){
            $order = Order_model::where('reference_id',$order_id)->first();

            $invoice_url = url(session('url').'export_invoice').'/'.$order->id;
             // JavaScript to open two tabs and print
            echo '<script>
            var newTab2 = window.open("'.$invoice_url.'", "_blank");
            var newTab1 = window.open("'.$order->delivery_note_url.'", "_blank");

            newTab1.onload = function() {
                newTab1.print();
            };

            newTab2.onload = function() {
                newTab2.print();
            };

            window.close();
            </script>';
        }


        // return redirect()->back();

    }
    public function import()
    {
        // $bm = new BackMarketAPIController();
        // // Replace 'your-excel-file.xlsx' with the actual path to your Excel file
        // $excelFilePath = storage_path(request('file'));

        // $data = Excel::toArray([], $excelFilePath)[0];
        // if(request('product') != null){
        //     foreach($data as $dr => $d){
        //         // $name = ;
        //     }
        // }else{

        //     // Print or use the resulting array
        //     // dd($data);
        //     $i = 0;
        //     foreach($data as $d){
        //         $orderObj = $bm->getOneOrder($d[1]);
        //         $this->updateBMOrder($d[1]);
        //         if($orderObj->state == 3){
        //             print_r($bm->shippingOrderlines($d[1],trim($d[6]),$orderObj->tracking_number));
        //             // $orderObj = $bm->getOneOrder($d[1]);
        //             // $this->updateBMOrder($d[1]);
        //             $i ++;
        //             print_r($orderObj);
        //             print_r($d[6]);
        //         }
        //         if($i == 100){break;}
        //     }
        // }

    }

    public function export()
    {
        // dd(request());
        // return Excel::download(new OrdersExport, 'your_export_file.xlsx');
        if(request('order') != null){
            $pdfExport = new OrdersExport();
            $pdfExport->generatePdf();
        }
            if(request('ordersheet') != null){
                return Excel::download(new OrdersheetExport, 'orders.xlsx');
            // echo "<script>window.close();</script>";
        }
        if(request('picklist') != null){
            $pdfExport = new PickListExport();
            $pdfExport->generatePdf();
        }
    }
    public function export_label()
    {
        // return Excel::download(new OrdersExport, 'your_export_file.xlsx');

        $pdfExport = new LabelsExport();
        $pdfExport->generatePdf();
    }
    public function export_note()
    {
        // return Excel::download(new OrdersExport, 'your_export_file.xlsx');

        $pdfExport = new DeliveryNotesExport();
        $pdfExport->generatePdf();
    }
    public function track_order($order_id){
        $order = Order_model::find($order_id);
        $orderObj = $this->updateBMOrder($order->reference_id, false, null, true);
        return redirect($orderObj->tracking_url);
    }
    public function getLabel($order_id)
    {

        $bm = new BackMarketAPIController();
        $this->updateBMOrder($order_id);
        $bm->getOrderLabel($order_id);
        return redirect()->back();

    }
    public function getapiorders($page = null)
    {


        if($page == 1){
            for($i = 1; $i <= 10; $i++){
                $j = $i*20;
                echo $url = url(session('url').'refresh_order').'/'.$j;
                echo '<script>
                var newTab1 = window.open("'.$url.'", "_blank");
                </script>';
            }
            $this->updateBMOrdersAll($page);
        }else if($page){
            $this->updateBMOrdersAll($page);

        }else{
            $this->updateBMOrdersAll();

        }



            echo '<script>window.close();</script>';



    }

    public function updateBMOrdersNew($return = false)
    {
        $bm = new BackMarketAPIController();
        $resArray = $bm->getNewOrders();
        $orders = [];
        if ($resArray !== null) {
            foreach ($resArray as $orderObj) {
                if (!empty($orderObj)) {
                    foreach($orderObj->orderlines as $orderline){
                        $this->validateOrderlines($orderObj->order_id, $orderline->listing);
                    }
                    $orders[] = $orderObj->order_id;
                }
            }
            foreach($orders as $or){
                $this->updateBMOrder($or);
            }

        } else {
            echo 'No new orders (in state 0 or 1) exist!';
        }
        $orders2 = Order_model::whereIn('status',[0,1])->where('order_type_id',3)->get();
        foreach($orders2 as $order){
            $this->updateBMOrder($order->reference_id);
        }


        $last_id = Order_item_model::where('care_id','!=',null)->orderBy('reference_id','desc')->first()->care_id;
        $care = $bm->getAllCare(false, ['last_id'=>$last_id,'page-size'=>50]);
        // $care = $bm->getAllCare(false, ['page-size'=>50]);
        // print_r($care);
        $care_line = collect($care)->pluck('id','orderline')->toArray();
        $care_keys = array_keys($care_line);


        // Assuming $care_line is already defined from the previous code
        $careLineKeys = array_keys($care_line);

        // Construct the raw SQL expression for the CASE statement
        // $caseExpression = "CASE ";
        foreach ($care_line as $id => $care) {
            // $caseExpression .= "WHEN reference_id = $id THEN $care ";
            Order_item_model::where('reference_id',$id)->update(['care_id' => $care]);
        }

        if($return = true){
            session()->put('success',count($orders).' Orders Loaded Successfull');
            return redirect()->back();
        }


    }
    public function updateBMOrder($order_id, $invoice = false, $tester = null, $data = false){
        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');

        $orderObj = $bm->getOneOrder($order_id);
        if($orderObj->delivery_note == null){
            $orderObj = $bm->getOneOrder($order_id);
        }

        $order_model->updateOrderInDB($orderObj, $invoice, $bm, $currency_codes, $country_codes);

        $order_item_model->updateOrderItemsInDB($orderObj, $tester, $bm);
        if($data == true){
            return $orderObj;
        }else{
            return redirect()->back();
        }



    }
    public function updateBMOrdersAll($page = 1)
    {

        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');



        $resArray = $bm->getAllOrders($page, ['page-size'=>50]);
        if ($resArray !== null) {
            // print_r($resArray);
            foreach ($resArray as $orderObj) {
                if (!empty($orderObj)) {
                // print_r($orderObj);
                $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);
                $order_item_model->updateOrderItemsInDB($orderObj,null,$bm);
                // $this->updateOrderItemsInDB($orderObj);
                }
                // print_r($orderObj);
                // if($i == 0){ break; } else { $i++; }
            }
        } else {
            echo 'No orders have been modified in 3 months!';
        }
    }

    private function validateOrderlines($order_id, $sku, $validated = true)
    {
        $bm = new BackMarketAPIController();
        $end_point = 'orders/' . $order_id;
        $new_state = 2;

        // construct the request body
        $request = ['order_id' => $order_id, 'new_state' => $new_state, 'sku' => $sku];
        $request_JSON = json_encode($request);

        $result = $bm->apiPost($end_point, $request_JSON);

        return $result;
    }


}
