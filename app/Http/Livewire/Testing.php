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
use Maatwebsite\Excel\Facades\Excel;

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

        echo "<div style='float:right;'><form method='post' action='".url("testing/upload_excel")."' enctype='multipart/form-data'>".csrf_field()."<input type='file' name='sheet'><input type='submit' value='Upload'></form></div>";
        $testing = new Api_request_model();
        $testing->push_testing();
        // $this->remove_extra_variations();

        die;

        // $data['requests'] = $requests;




        // return view('livewire.testing', $data); // Return the Blade view instance with data
    }
    public function upload_excel(){
        request()->validate([
            'sheet' => 'required|file|mimes:xlsx,xls',
        ]);
        // Store the uploaded file in a temporary location
        $filePath = request()->file('sheet')->store('temp');
        $excelFilePath = storage_path('app/'.$filePath);
        $data = Excel::toArray([], $excelFilePath)[0];
        $dh = $data[0];
        // print_r($dh);
        unset($data[0]);

        foreach ($data as $key => $row) {
            $new_data = [];
            foreach ($row as $col => $cell) {
                // Validate the cell data
                if (preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $cell)) {
                    Log::warning("JavaScript code detected at row $key, column $col: $cell");
                    continue;
                }
                if (preg_match('/<\?php\b[^>]*>(.*?)<\?>/is', $cell)) {
                    Log::warning("PHP code detected at row $key, column $col: $cell");
                    continue;
                }
                $new_data[$dh[$col]] = $cell;
            }
            $api_request = Api_request_model::firstOrNew([
                'request' => json_encode($new_data),
            ]);
            $api_request->save();
        }

        // Delete the temporary file

        session()->flash('message', 'Excel file uploaded successfully');
        return redirect()->back();

    }
    public function repush($id){
        $request = Api_request_model::find($id);
        $request->stock_id = null;
        $request->status = null;
        $request->save();

        return redirect()->back();
    }



}
