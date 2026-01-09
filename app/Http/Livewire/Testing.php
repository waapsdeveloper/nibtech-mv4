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
use Mpdf\Tag\A;

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
        ini_set('max_execution_time', 1200);

        $data['title_page'] = "Testing";
        session()->put('page_title', $data['title_page']);

        // Call push_testing to process and send data individually when page loads
        $model = new Api_request_model();
        $model->push_testing(100);

        $requests = Api_request_model::whereNull('status')->limit(400)->get();

        $data['requests'] = $requests;




        return view('livewire.testing', $data); // Return the Blade view instance with data
    }
    public function filtered_testing_push($request_filter){
        ini_set('max_execution_time', 1200);

        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }

        $model = new Api_request_model();
        $model->push_testing(100, $request_filter);

        session()->flash('message', 'Filtered testing push executed successfully');

        return redirect()->back();
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
        unlink($excelFilePath);

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

    public function add_imei($id){
        $request = Api_request_model::find($id);
        $data = json_decode($request->request, true);
        if($data['Imei'] == null){
            $data['Imei'] = request('imei');
        }
        $request->request = json_encode($data);
        $request->save();

        return redirect()->back()->with('success', 'IMEI Added');

    }


    public function send_to_eg($id){
        $request = Api_request_model::find($id);
        $request->send_to_eg();

        return redirect()->back()->with('success', 'Request sent to EG');
    }
    public function send_to_yk($id){
        $request = Api_request_model::find($id);
        $request->send_to_yk();

        return redirect()->back()->with('success', 'Request sent to YK');
    }


    public function delete_request($id){
        $request = Api_request_model::find($id);
        if($request){
            $request->delete();
            return redirect()->back()->with('success', 'Request deleted successfully');
        }else{
            return redirect()->back()->with('error', 'Request not found');
        }
    }

    public function bulk_send_to_eg(){
        $request_ids = json_decode(request('request_ids'), true);

        if(!is_array($request_ids) || empty($request_ids)){
            return redirect()->back()->with('error', 'No requests selected');
        }

        $count = 0;
        foreach($request_ids as $id){
            $request = Api_request_model::find($id);
            if($request){
                $request->send_to_eg();
                $count++;
            }
        }

        return redirect()->back()->with('success', "$count request(s) sent to EG successfully");
    }

    public function bulk_send_to_yk(){
        $request_ids = json_decode(request('request_ids'), true);

        if(!is_array($request_ids) || empty($request_ids)){
            return redirect()->back()->with('error', 'No requests selected');
        }

        $count = 0;
        foreach($request_ids as $id){
            $request = Api_request_model::find($id);
            if($request){
                $request->send_to_yk();
                $count++;
            }
        }

        return redirect()->back()->with('success', "$count request(s) sent to YK successfully");
    }

    public function bulk_delete(){
        $request_ids = json_decode(request('request_ids'), true);

        if(!is_array($request_ids) || empty($request_ids)){
            return redirect()->back()->with('error', 'No requests selected');
        }

        $count = Api_request_model::whereIn('id', $request_ids)->delete();

        return redirect()->back()->with('success', "$count request(s) deleted successfully");
    }

    public function push_all(){
        ini_set('max_execution_time', 1200);

        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }

        $model = new Api_request_model();
        $result = $model->push_testing(100);

        return redirect()->back()->with('success', 'Push testing completed. Data has been processed and sent to appropriate systems.');
    }

    public function push_one($id){
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }

        $request = Api_request_model::find($id);
        if(!$request){
            return redirect()->back()->with('error', 'Request not found');
        }

        // Process this single request through push_testing logic
        $datas = json_decode($request->request);

        // Check if stock exists in the system
        $stock = Api_request_model::resolveStock($datas);

        if(!$stock){
            return redirect()->back()->with('error', 'Stock not found in system. Cannot push automatically.');
        }

        // Stock exists, proceed with automatic push based on conditions
        if(config('app.url') == 'https://sdpos.nibritaintech.com' && in_array(trim($datas->PCName ?? ''), ['PC12', 'PC13', 'PC14', 'PC15', 'PC16'])){
            $request->send_to_yk();
            return redirect()->back()->with('success', 'Stock found! Request pushed to YK based on PC name');
        }

        if(str_contains(strtolower($datas->BatchID ?? ''), 'eg')){
            $request->send_to_eg();
            return redirect()->back()->with('success', 'Stock found! Request pushed to EG based on Batch ID');
        }

        if(str_contains(strtolower($datas->BatchID ?? ''), 'yk')){
            $request->send_to_yk();
            return redirect()->back()->with('success', 'Stock found! Request pushed to YK based on Batch ID');
        }

        return redirect()->back()->with('success', 'Stock found but no automatic routing rule matched. Please use EG/YK buttons manually.');

        foreach($request_ids as $id){
            $request = Api_request_model::find($id);
            if(!$request) continue;

            $datas = json_decode($request->request);

            // Check conditions similar to push_testing method
            if(config('app.url') == 'https://sdpos.nibritaintech.com' && in_array(trim($datas->PCName ?? ''), ['PC12', 'PC13', 'PC14', 'PC15', 'PC16'])){
                $request->send_to_yk();
                $ykCount++;
            }
            elseif(str_contains(strtolower($datas->BatchID ?? ''), 'eg')){
                $request->send_to_eg();
                $egCount++;
            }
            elseif(str_contains(strtolower($datas->BatchID ?? ''), 'yk')){
                $request->send_to_yk();
                $ykCount++;
            }

            $processedCount++;
        }

        return redirect()->back()->with('success', "Processed $processedCount request(s): $egCount to EG, $ykCount to YK");
    }

}
