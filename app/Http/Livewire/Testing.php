<?php

namespace App\Http\Livewire;


use App\Models\Api_request_model;
use Livewire\Component;

use App\Models\Listing_model;
use App\Models\Order_item_model;
use App\Models\Process_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class Testing extends Component
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


        $testing = new Api_request_model();
        $testing->push_testing();
        // $this->remove_extra_variations();

        die;

        // $data['requests'] = $requests;




        // return view('livewire.testing', $data); // Return the Blade view instance with data
    }

    public function repush($id){
        $request = Api_request_model::find($id);
        $request->stock_id = null;
        $request->status = null;
        $request->save();
    }



}
