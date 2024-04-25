<?php

namespace App\Http\Livewire;
    use Livewire\Component;
    use App\Models\Admin_model;
use App\Models\Color_model;
use App\Models\Stock_model;
    use App\Models\Order_item_model;
    use App\Models\Currency_model;
    use App\Models\Country_model;
use App\Models\Grade_model;
use App\Models\Products_model;
use App\Models\Storage_model;
use Carbon\Carbon;


class IMEI extends Component
{
    public $currency_codes;
    public $country_codes;

    public function mount()
    {
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

            $data['products'] = Products_model::orderBy('model','asc')->get();
            $data['colors'] = Color_model::all();
            $data['storages'] = Storage_model::all();
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
                    session()->put('success', 'IMEI Sold');
                }else{
                    session()->put('success', 'IMEI Available');
                }
            }
            if($stock->status == 2){
                if($sale_status == null){
                    $stock->status = 1;
                    $stock->save();
                    session()->put('success', 'IMEI Available');
                }else{
                    session()->put('success', 'IMEI Sold');
                }
            }
            $data['grades'] = Grade_model::pluck('name','id');
            $stock_id = $stock->id;
            $orders = Order_item_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
            $data['stock'] = $stock;
            $data['orders'] = $orders;
            // dd($orders);
        }

        return view('livewire.imei', $data); // Return the Blade view instance with data
    }

    public function change_grade($stock_id){
        $stock = Stock_model::find($stock_id);


    }



}
