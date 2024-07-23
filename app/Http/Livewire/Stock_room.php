<?php

namespace App\Http\Livewire;
use Livewire\Component;
use App\Models\Admin_model;
use App\Models\Api_request_model;
use App\Models\Color_model;
use App\Models\Stock_model;
use App\Models\Order_item_model;
use App\Models\Grade_model;
use App\Models\Process_stock_model;
use App\Models\Products_model;
use App\Models\Stock_movement_model;
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
use App\Models\Variation_model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Stock_room extends Component
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

        $data['title_page'] = "Stock Room";
        $data['admins'] = Admin_model::where('id', '!=', 1)->get();
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $user_id = session('user_id');
        $user = session('user');
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        if($user->hasPermission('view_all_stock_movements')){
            // $data['stock_count'] = Stock_movement_model::where(['received_at'=>null])->select('admin_id', 'COUNT(*) as count')->pluck('count', 'admin_id');

            $data['stock_count'] = Stock_movement_model::whereNull('received_at')
            ->with('admin:id,first_name') // Load the related admin with only 'id' and 'first_name' fields
            ->select('admin_id', DB::raw('COUNT(*) as count'))
            ->groupBy('admin_id')
            ->get();
        }else{
            $data['stock_count'] = Stock_movement_model::where(['admin_id'=>$user_id, 'received_at'=>null])->count();

        }

        if(request('show') == 1){
            if($user->hasPermission('view_all_stock_movements')){
                $data['stocks'] = Stock_movement_model::where(['admin_id'=>request('admin_id'), 'received_at'=>null])
                ->orderBy('id', 'desc') // Secondary order by reference_id
                // ->select('orders.*')
                ->paginate($per_page)
                ->onEachSide(5)
                ->appends(request()->except('page'));
            }else{
                $data['stocks'] = Stock_movement_model::where(['admin_id'=>$user_id, 'received_at'=>null])
                ->orderBy('id', 'desc') // Secondary order by reference_id
                // ->select('orders.*')
                ->paginate($per_page)
                ->onEachSide(5)
                ->appends(request()->except('page'));
            }
            
        }

        return view('livewire.stock_room', $data); // Return the Blade view instance with data
    }

    public function exit(){
        
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
            if($stock == null){
                session()->put('error', 'IMEI Invalid / Not Found');
                return redirect()->back(); // Redirect here is not recommended

            }
            $movement = Stock_movement_model::where(['stock_id'=>$stock->id,'received_at'=>null])->first();
            if($movement == null){
                if($movement->admin_id == $user_id){

                    session()->put('error', 'IMEI Already added in your sheet');
                }else{

                    session()->put('error', 'IMEI Already added to '.$movement->admin->first_name);
                }
                return redirect()->back(); // Redirect here is not recommended

            }

            $stock_movement = Stock_movement_model::create([
                'stock_id' => $stock->id,
                'admin_id' => $user_id,
                'description' => $stock->description,
                'exit_at' => Carbon::now()
            ]);
            $model = $stock->variation->product->model ?? '?';
            $storage = $stock->variation->storage_id->name ?? '?';
            $color = $stock->variation->color_id->name ?? '?';
            $grade = $stock->variation->grade_id->name ?? '?';
            
            session()->put('success', 'Stock Exit: '.$model.' - '.$storage.' - '.$color.' - '.$grade);
            return redirect()->back(); // Redirect here is not recommended

        }



    }
    public function receive(){
        
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
            if($stock == null){
                session()->put('error', 'IMEI Invalid / Not Found');
                return redirect()->back(); // Redirect here is not recommended

            }
            $stock_movement = Stock_movement_model::where(['stock_id'=>$stock->id, 'received_at'=>null])->first();
            
            if($stock_movement == null){
                session()->put('error', 'Exit Entry Missing');
                return redirect()->back(); // Redirect here is not recommended

            }
            
            $stock_movement = Stock_movement_model::where(['stock_id'=>$stock->id, 'received_at'=>null])->update([
                'received_by' => $user_id,
                'received_at' => Carbon::now()
            ]);

            $model = $stock->variation->product->model ?? '?';
            $storage = $stock->variation->storage_id->name ?? '?';
            $color = $stock->variation->color_id->name ?? '?';
            $grade = $stock->variation->grade_id->name ?? '?';
            
            session()->put('success', 'Stock Received: '.$model.' - '.$storage.' - '.$color.' - '.$grade);
            return redirect()->back(); // Redirect here is not recommended
        }



    }




}
