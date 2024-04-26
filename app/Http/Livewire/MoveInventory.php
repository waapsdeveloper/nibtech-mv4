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
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
use App\Models\Variation_model;
use Carbon\Carbon;


class MoveInventory extends Component
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

        $data['grades'] = Grade_model::all();

        if(request('grade')){
            session()->put('grade',request('grade'));
            session()->put('success',request('grade'));

        }
        $grade = session('grade');
        if(request('description')){
            session()->put('description',request('description'));
        }

        if($grade){
            // if(isset(session('added_imeis')[$grade])){
                // $added_imeis = session('added_imeis')[$grade];
                $stocks = Stock_operations_model::where('created_at','>=',now()->format('Y-m-d')." 00:00:00")
                ->whereHas('new_variation', function ($query) use ($grade) {
                    $query->where('grade', $grade);
                })
                ->whereHas('stock', function ($query) {
                    $query->where('status', 1);
                })->get();
                $data['stocks'] = $stocks;
            //     dd($stocks);
            // }

            $data['grade'] = Grade_model::find($grade);
        }

        return view('livewire.move_inventory', $data); // Return the Blade view instance with data
    }

    public function change_grade(){
        $grade = request('grade');
        $description = request('description');
        if(request('grade')){
            session()->put('grade',request('grade'));
            session()->put('success',request('grade')." 2");
        }
        session()->put('description',request('description'));


        if (request('imei')) {
            if (ctype_digit(request('imei'))) {
                $i = request('imei');
                $s = null;
            } else {
                $i = null;
                $s = request('imei');
            }

            $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();
            if (request('imei') == '' || !$stock || $stock->status != 1) {
                session()->put('error', 'IMEI Invalid / Not Available');
                return redirect()->back();
            }
            $stock_id = $stock->id;
            $new_variation = Variation_model::firstOrNew([
                'product_id' => $stock->variation->product_id,
                'color' => $stock->variation->color,
                'storage' => $stock->variation->storage,
                'grade' => $grade,
            ]);
            $new_variation->status = 1;
            $new_variation->save();
            $stock_operation = Stock_operations_model::create([
                'stock_id' => $stock_id,
                'old_variation_id' => $stock->variation_id,
                'new_variation_id' => $new_variation->id,
                'description' => $description,
                'admin_id' => session('user_id'),
            ]);
            $stock->variation_id = $new_variation->id;
            $stock->save();

            // session()->put('added_imeis['.$grade.'][]', $stock_id);
            // dd($orders);
        }


        session()->put('success', 'Stock Sent Successfully');
        return redirect()->back();

    }



}
