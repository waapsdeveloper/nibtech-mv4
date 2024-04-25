<?php

namespace App\Http\Livewire;
    use Livewire\Component;
    use App\Models\Admin_model;
    use App\Models\Stock_model;
    use App\Models\Order_item_model;
    use App\Models\Currency_model;
    use App\Models\Country_model;
    use Carbon\Carbon;


class IMEI extends Component
{
    public $currency_codes;
    public $country_codes;

    public function mount()
    {
        $this->currency_codes = Currency_model::pluck('id','code');
        $this->country_codes = Country_model::pluck('id','code');
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }
    }
    public function render()
    {
        $data['last_hour'] = Carbon::now()->subHour(1);
        $data['admins'] = Admin_model::where('id', '!=', 1)->get();
        $user_id = session('user_id');

        if (request('imei')) {
            if (ctype_digit(request('imei'))) {
                $i = request('imei');
                $s = null;
            } else {
                $i = null;
                $s = request('imei');
            }

            $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();
            if (request('imei') == '' || !$stock || $stock->status == null) {
                session()->put('error', 'IMEI Invalid / Not Found');
                // return redirect()->back(); // Redirect here is not recommended
                return view('livewire.imei', $data); // Return the Blade view instance with data
            }
            if($stock->status == 1){
                $sale_status = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>$stock->purchase_item->id])->first();
                // print_r($sale_status);
                if($sale_status != null){
                    $stock->status = 2;
                    $stock->save();
                    session()->put('success', 'IMEI Sold');
                }else{
                    session()->put('success', 'IMEI AVailable');
                }
            }
            if($stock->status == 2){
                $sale_status = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>$stock->purchase_item->id])->first();
                // print_r($sale_status);
                if($sale_status == null){
                    $stock->status = 1;
                    $stock->save();
                    session()->put('success', 'IMEI AVailable');
                }else{
                    session()->put('success', 'IMEI Sold');
                }
            }
            $stock_id = $stock->id;
            $orders = Order_item_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
            $data['orders'] = $orders;
            // dd($orders);
        }

        return view('livewire.imei', $data); // Return the Blade view instance with data
    }




}
