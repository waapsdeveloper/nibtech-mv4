<?php

namespace App\Models;

use Carbon\Carbon;
use Google\Service\MyBusinessAccountManagement\Admin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Api_request_model extends Model
{
    use HasFactory;
    protected $table = 'api_requests';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
        'request',
        'status'
    ];



    public function push_testing()
    {
        $imeis = [];
        $admins = Admin_model::pluck('first_name','id')->toArray();
        $lowercaseAdmins = array_map('strtolower', $admins);
        // $products = Products_model::pluck('model','id')->toArray();
        $storages = Storage_model::pluck('name','id')->toArray();
        $colors = Color_model::pluck('name','id')->toArray();
            // Convert each color name to lowercase
        $lowercaseColors = array_map('strtolower', $colors);
        $grades = Grade_model::pluck('name','id')->toArray();
            // Convert each grade name to lowercase
        $lowercaseGrades = array_map('strtolower', $grades);

        $requests = Api_request_model::where('status', null)->orderBy('id','asc')->get();
        // $requests = Api_request_model::orderBy('id','asc')->get();
        foreach($requests as $request){
            unset($sub_grade);
            $data = $request->request;
            $datas = $data;
            if (strpos($datas, '"{\"ModelNo') != 0) {
                $datas = json_decode($datas);
                $datas = json_decode($datas);
                // echo "Hello";
            } else{
                if (strpos($data, '{') !== false && strpos($data, '}') !== false) {
                    $datas = preg_split('/(?<=\}),(?=\{)/', $data)[0];
                }
                if (is_string($datas)) {
                    $datas = json_decode($datas);
                }
                if (is_string($datas)) {
                    $datas = json_decode($datas);
                }
                // echo "Hell2o";
            }
            // echo "<br>";
            // print_r($datas);


            $stock = Stock_model::where('imei',$datas->Imei)->orWhere('imei',$datas->Imei2)->orWhere('serial_number',$datas->Serial)->first();

            if(!$stock && $datas->Imei == '' && $datas->Imei2 == ''){
                $api_request = Api_request_model::where('stock_id','!=',null)->where('status','!=',null)->first();
                if($api_request){
                    $stock = Stock_model::find($api_request->stock_id);
                }
            }

            if(in_array($datas->Memory, $storages)){
                $storage = array_search($datas->Memory,$storages);
            }elseif(in_array(substr($datas->Memory, 0, -2), $storages)){
                $storage = array_search(substr($datas->Memory, 0, -2),$storages);
            }else{
                $storage = 0;
            }
            if(!in_array($datas->Imei, $imeis)){
                $imeis[] = $datas->Imei;
            echo "<div class='col-md-4'><pre>";

            // print_r($request);
            print_r($datas);
            echo "</pre></div>";
            }

            $colorName = strtolower($datas->Color); // Convert color name to lowercase

            if (in_array($colorName, $lowercaseColors)) {
                // If the color exists in the predefined colors array,
                // retrieve its index
                $color = array_search($colorName, $lowercaseColors);
            } else {
                // If the color doesn't exist in the predefined colors array,
                // create a new color record in the database
                $newColor = Color_model::create([
                    'name' => $colorName
                ]);
                $colors = Color_model::pluck('name','id')->toArray();
                $lowercaseColors = array_map('strtolower', $colors);
                // Retrieve the ID of the newly created color
                $color = $newColor->id;
            }


            $gradeName = strtolower($datas->Grade); // Convert grade name to lowercase

            if (in_array($gradeName, $lowercaseGrades)) {
                // If the grade exists in the predefined grades array,
                // retrieve its index
                $grade = array_search($gradeName, $lowercaseGrades);
            }else{

                if(str_contains($gradeName, '|')){
                    $gradeName1 = explode('|', $gradeName)[0];
                    if($gradeName1 == 'ws'){
                        $grade = 11;
                    }elseif($gradeName1 == 'bt'){
                        $grade = 21;
                    }else{
                        $grade = array_search($gradeName1, $lowercaseGrades);
                    }

                    $gradeName2 = explode('|', $gradeName)[1];
                    if($gradeName2 == 'ok'){
                        $sub_grade = 5;
                    }else{
                        $sub_grade = array_search($gradeName2, $lowercaseGrades);
                    }

                }elseif($gradeName == '' || $gradeName == 'a+' || $gradeName == 'a/a+' || $gradeName == 'ug'){
                    $grade = 7;
                }elseif($gradeName == 'd'){
                    $grade = $stock->variation->grade;
                }elseif($gradeName == 'a'){
                    $grade = 2;
                }elseif(in_array($gradeName, ['a-','b'])){
                    $grade = 3;
                }elseif(in_array($gradeName, ['ab','c'])){
                    $grade = 5;
                }elseif($gradeName == 'ok'){
                    $grade = 5;
                }else{

                    echo $gradeName;
                    continue;
                }
            }

            $adminName = strtolower(trim($datas->TesterName)); // Convert grade name to lowercase

            if (in_array($adminName, $lowercaseAdmins)) {
                // If the grade exists in the predefined grades array,
                // retrieve its index
                $admin = array_search($adminName, $lowercaseAdmins);
            }else{
                if($adminName == 'paras khan'){
                    $admin = 6;
                }elseif(trim($adminName) == 'sangeeta punia'){
                    $admin = 8;
                }elseif($adminName == 'owais'){
                    $admin = 2;
                // }elseif($adminName == '' && $datas->PCName == 'PC6'){
                //     $admin = 16;
                }else{

                    echo "Please create/change Team Member First Name to: ".$adminName;
                    continue;
                }
            }

            if($stock != null){
                $new_variation = [
                    'product_id' => $stock->variation->product_id,
                    'storage' => $stock->variation->storage,
                    'color' => $stock->variation->color,
                    'grade' => $stock->variation->grade,
                ];
                if(isset($sub_grade)){
                    $new_variation['sub_grade'] = $sub_grade;
                }

                    $new_variation['storage'] = $storage;
                if($stock->variation->storage != null && $stock->variation->storage != 0 && $stock->variation->storage != $storage){
                    $message = "Storage changed from: ".$stock->variation->storage_id->name." to: ".$storages[$storage];
                }
                if($stock->variation->color == null || $stock->variation->color == $color){
                    $new_variation['color'] = $color;
                }

                if(($stock->variation->grade == 9 || $stock->variation->grade == 7 || $stock->variation->grade == $grade) && $grade != ''){
                    $new_variation['grade'] = $grade;
                }
                if($stock->status == 1){
                    $new_variation['grade'] = $grade;
                }
                if($stock->imei == $datas->Imei2 && $stock->imei != null){
                    if(isset($message)){
                        $message .= " | IMEI changed from: ".$datas->Imei2;
                    }else{
                        $message = "IMEI changed from: ".$datas->Imei2;
                    }
                    $stock->imei = $datas->Imei;
                }
                $variation = Variation_model::firstOrNew($new_variation);
                if($stock->status != 2 || $stock->last_item()->order->customer_id == 3955){

                    if($stock->last_item()->order->customer_id == 3955){


                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                          CURLOPT_URL => 'https://egpos.nibritaintech.com/api/request',
                          CURLOPT_RETURNTRANSFER => true,
                          CURLOPT_ENCODING => '',
                          CURLOPT_MAXREDIRS => 10,
                          CURLOPT_TIMEOUT => 0,
                          CURLOPT_FOLLOWLOCATION => true,
                          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                          CURLOPT_CUSTOMREQUEST => 'POST',
                          CURLOPT_POSTFIELDS => json_encode($datas),
                          CURLOPT_HTTPHEADER => array(
                            'Accept: application/json',
                            'Content-Type: application/json',
                            'Authorization: Bearer 2|otpLfHymDGDscNuKjk9CQMx620avGG0aWgMpuPAp5d1d27d2'
                          ),
                        ));

                        $response = curl_exec($curl);

                        curl_close($curl);
                        echo $response;

                        // $curl = curl_init();

                        // curl_setopt_array($curl, array(
                        //   CURLOPT_URL => 'https://egpos.nibritaintech.com/api/request',
                        //   CURLOPT_RETURNTRANSFER => true,
                        //   CURLOPT_ENCODING => '',
                        //   CURLOPT_MAXREDIRS => 10,
                        //   CURLOPT_TIMEOUT => 0,
                        //   CURLOPT_FOLLOWLOCATION => true,
                        //   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        //   CURLOPT_CUSTOMREQUEST => 'POST',
                        //   CURLOPT_POSTFIELDS => json_encode($datas),
                        //   CURLOPT_HTTPHEADER => array(
                        //     'Accept: application/json',
                        //     'Content-Type: application/json',
                        //     'Authorization: 32ba140e3260848a75db19c1e877b94d6887c6207cc24c1627f99bc8e9503928'
                        //   ),
                        // ));

                        // $response = curl_exec($curl);

                        // curl_close($curl);
                        if($response){
                            echo "<pre>";
                            print_r($response);
                            echo "</pre>";
                            echo "<br><br><br>Hello<br><br><br>";
                        }

                    }


                    if(isset($message)){

                        $stock_operation = Stock_operations_model::create([
                            'stock_id' => $stock->id,
                            'api_request_id' => $request->id,
                            'old_variation_id' => $stock->variation_id,
                            'new_variation_id' => $stock->variation_id,
                            'description' => $message." | DrPhone",
                            'admin_id' => $admin,
                            'created_at' => Carbon::parse($datas->Time)->format('Y-m-d H:i:s'),
                        ]);
                    }
                    if(strlen($datas->Fail) > 200){
                        $fail = substr($datas->Fail, 0, 200);
                    }else{
                        $fail = $datas->Fail;
                    }
                    $stock_operation = new Stock_operations_model();
                    $stock_operation->new_operation($stock->id, null, 1, $request->id, $stock->variation_id, $variation->id, $fail." | ".$datas->Comments." | DrPhone", $admin, Carbon::parse($datas->Time)->format('Y-m-d H:i:s'));
                    // $stock_operation = Stock_operations_model::create([
                    //     'stock_id' => $stock->id,
                    //     'api_request_id' => $request->id,
                    //     'process_id' => 1,
                    //     'old_variation_id' => $stock->variation_id,
                    //     'new_variation_id' => $variation->id,
                    //     'description' => $fail." | ".$datas->Comments." | DrPhone",
                    //     'admin_id' => $admin,
                    //     'created_at' => Carbon::parse($datas->Time)->format('Y-m-d H:i:s'),
                    // ]);

                    $variation->status = 1;
                    $variation->save();
                    $stock->variation_id = $variation->id;
                    $stock->save();
                    $request->stock_id = $stock->id;
                    $request->status = 1;
                    $request->save();

                }elseif($stock->status == 2){

                    $request->stock_id = $stock->id;
                    $request->status = 2;
                    $request->save();
                }
            }
        }

    }
}
