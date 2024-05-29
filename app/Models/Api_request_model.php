<?php

namespace App\Models;

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

        $admins = Admin_model::pluck('first_name','id')->toArray();
        $lowercaseAdmins = array_map('strtolower', $admins);
        $products = Products_model::pluck('model','id')->toArray();
        $storages = Storage_model::pluck('name','id')->toArray();
        $colors = Color_model::pluck('name','id')->toArray();
            // Convert each color name to lowercase
        $lowercaseColors = array_map('strtolower', $colors);
        $grades = Grade_model::pluck('name','id')->toArray();
            // Convert each grade name to lowercase
        $lowercaseGrades = array_map('strtolower', $grades);

        $requests = Api_request_model::where('status',null)->orderBy('id','asc')->get();
        // $requests = Api_request_model::orderBy('id','asc')->get();
        foreach($requests as $request){
            $data = $request->request;
            $datas = json_decode(json_decode(preg_split('/(?<=\}),(?=\{)/', $data)[0]));
            if($datas == null || ($datas->Imei == '' && $datas->Serial == '')){
                continue;
            }
            $stock = Stock_model::where('imei',$datas->Imei)->orWhere('imei',$datas->Imei2)->orWhere('serial_number',$datas->Serial)->first();
            // $stock = Stock_model::where('imei',$datas->Imei2)->first();
            // if(!$stock){
            //     continue;
            // }else{

            //     $stock_operation = Stock_operations_model::create([
            //         'stock_id' => $stock->id,
            //         'old_variation_id' => $stock->variation_id,
            //         'new_variation_id' => $stock->variation->id,
            //         'description' => $datas->Comments." | IMEI changed from: ".$datas->Imei2." | Testing API Push",
            //         'admin_id' => NULL,
            //     ]);
            //     $stock->imei = $datas->Imei;
            //     $stock->save();

            // echo "<pre>";

            // print_r($stock);
            // print_r($datas);
            // echo "</pre>";
            // continue;
            // }
            if(in_array($datas->ModelName, $products)){
                $product = array_search($datas->ModelName,$products);
            }

            if(in_array($datas->Memory, $storages)){
                $storage = array_search($datas->Memory,$storages);
            }

            echo "<pre>";

            // print_r($request);
            print_r($datas);
            echo "</pre>";

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
                if($gradeName == '' || $gradeName == 'a+' || $gradeName == 'a/a+' || $gradeName == 'ug'){
                    $grade = 7;
                }elseif($gradeName == 'a'){
                    $grade = 2;
                }elseif($gradeName == 'ok'){
                    $grade = 5;
                }else{

                    echo $gradeName;
                    continue;
                }
            }

            $adminName = strtolower($datas->TesterName); // Convert grade name to lowercase

            if (in_array($adminName, $lowercaseAdmins)) {
                // If the grade exists in the predefined grades array,
                // retrieve its index
                $admin = array_search($adminName, $lowercaseAdmins);
            }else{
                if($adminName == 'paras khan'){
                    $admin = 6;
                }elseif($adminName == 'sangeeta punia'){
                    $admin = 8;
                }else{

                    echo $adminName;
                    continue;
                }
            }

            if($stock != null && ($stock->variation->storage == $storage || $stock->variation->storage == 0)){
                $new_variation = [
                    'product_id' => $stock->variation->product_id,
                    'storage' => $stock->variation->storage,
                    'color' => $stock->variation->color,
                    'grade' => $stock->variation->grade
                ];

                if($stock->variation->storage == null || $stock->variation->storage == 0 || $stock->variation->storage == $storage){
                    $new_variation['storage'] = $storage;
                }
                if($stock->variation->color == null || $stock->variation->color == $color){
                    $new_variation['color'] = $color;
                }

                if(($stock->variation->grade == 9 || $stock->variation->grade == $grade) && $grade != ''){
                    $new_variation['grade'] = $grade;
                }
                if($stock->status == 1){
                    $new_variation['grade'] = $grade;
                }
                $variation = Variation_model::firstOrNew($new_variation);
                if($stock->status == 1){

                    if($stock->imei == $datas->Imei2){

                        $stock_operation = Stock_operations_model::create([
                            'stock_id' => $stock->id,
                            'old_variation_id' => $stock->variation_id,
                            'new_variation_id' => $stock->variation->id,
                            'description' => $datas->Fail." | ".$datas->Comments." | IMEI changed from: ".$datas->Imei2." | DrPhone",
                            'admin_id' => $admin,
                        ]);
                        $stock->imei = $datas->Imei;
                    }else{

                        $stock_operation = Stock_operations_model::create([
                            'stock_id' => $stock->id,
                            'old_variation_id' => $stock->variation_id,
                            'new_variation_id' => $variation->id,
                            'description' => $datas->Fail." | ".$datas->Comments." | DrPhone",
                            'admin_id' => $admin,
                        ]);
                    }
                    $variation->status = 1;
                    $variation->save();
                    $stock->variation_id = $variation->id;
                    $stock->save();
                    $request->stock_id = $stock->id;
                    $request->status = 1;
                    $request->save();

                }elseif($stock->status == 2){

                    $request->stock_id = $stock->id;
                    $request->status = 1;
                    $request->save();
                }
                echo "<pre>";

                print_r($stock);
                echo "</pre>";
            }
        }

    }
}
