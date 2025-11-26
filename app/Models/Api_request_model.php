<?php

namespace App\Models;

use Carbon\Carbon;
use Google\Service\MyBusinessAccountManagement\Admin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Api_request_model extends Model
{
    use HasFactory;
    protected $table = 'api_requests';
    protected $primaryKey = 'id';
    protected static $debugBuffer = [];
    protected static $stockCache = [];
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
        'request',
        'status'
    ];



    public function push_testing()
    {
        unset($sub_grade);
        $return = [];
        $imeis = [];
        $admins = Admin_model::pluck('first_name','id')->toArray();
        $adminLookup = self::buildLookup($admins);
        // $products = Products_model::pluck('model','id')->toArray();
        $storages = Storage_model::pluck('name','id')->toArray();
        $storageLookup = self::buildLookup($storages);
        $colors = Color_model::pluck('name','id')->toArray();
        $colorLookup = self::buildLookup($colors);
        $grades = Grade_model::pluck('name','id')->toArray();
        $gradeLookup = self::buildLookup($grades);

        $requests = Api_request_model::where('status', null)
        // ->where('request', 'LIKE', '%10565%')
        ->limit(1000)->get();
        // $requests = Api_request_model::orderBy('id','asc')->get();
        $log_info = 'Add these products manually:'."\n";
        foreach($requests as $request){
            unset($sub_grade);
            $data = $request->request;
            $datas = $data;
            if ($request->json == 1) {
                $datas = json_decode($data);
            }elseif (strpos($datas, '"{\"ModelNo') != 0) {
                $datas = json_decode($datas);
                $datas = json_decode($datas);
                // echo "Hello";
                unset($datas->OEMData);
                // dd($datas);
                $request->request = json_encode($datas);
                $request->json = 1;
                $request->save();

                continue;
            } else {
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
                unset($datas->OEMData);
                // dd($datas);
                $request->request = json_encode($datas);
                $request->json = 1;
                $request->save();

                continue;
            }
            // echo "<br>";
            // print_r($datas);

            $stock = self::resolveStock($datas);


            if(config('app.url') == 'https://sdpos.nibritaintech.com' && in_array(trim($datas->PCName), ['PC12', 'PC13', 'PC14', 'PC15', 'PC16'])){

                $request->send_to_yk();
                continue;

            }
            // if domain = sdpos.nibritaintech.com
            if(config('app.url') == 'https://sdpos.nibritaintech.com' && $stock == null && (str_contains(strtolower($datas->BatchID), 'eg') || str_contains(strtolower($datas->TesterName), 'rizwan') || str_contains(strtolower($datas->TesterName), 'aqeel'))){

                $request->send_to_eg();
                continue;

            }
            if(!$stock && $datas->Imei == '' && $datas->Imei2 == ''){
                self::recordDebugPoint($request, 'skipped request because stock lookup failed and no IMEI present', [
                    'serial' => $datas->Serial ?? null,
                ]);
                continue;
            }
            $storage = 0;
            $memoryExact = self::normalizeValue($datas->Memory ?? '');
            if($memoryExact !== '' && isset($storageLookup[$memoryExact])){
                $storage = $storageLookup[$memoryExact];
            }elseif(strlen((string) $datas->Memory) > 2){
                $trimmed = self::normalizeValue(substr((string) $datas->Memory, 0, -2));
                if($trimmed !== '' && isset($storageLookup[$trimmed])){
                    $storage = $storageLookup[$trimmed];
                }
            }
            $imeiKey = (string) ($datas->Imei ?? '');
            if(!array_key_exists($imeiKey, $imeis)){
                $imeis[$imeiKey] = true;
                $return[] = $datas;

            }

            $colorName = self::normalizeValue($datas->Color);

            if (isset($colorLookup[$colorName])) {
                $color = $colorLookup[$colorName];
            } else {
                $newColor = Color_model::create([
                    'name' => $colorName
                ]);
                $colors[$newColor->id] = $newColor->name;
                $colorLookup[$colorName] = $newColor->id;
                $color = $newColor->id;
            }


            $gradeName = self::normalizeValue($datas->Grade);
            if (isset($gradeLookup[$gradeName])) {
                $grade = $gradeLookup[$gradeName];
            }else{

                if(str_contains($gradeName, '|')){
                    $gradeParts = explode('|', $gradeName);
                    $gradeName1 = $gradeParts[0];
                    $grade = self::gradeFromLookupOrAlias($gradeName1, $gradeLookup, $stock);

                    $gradeName2 = $gradeParts[1] ?? '';
                    if($gradeName2 !== ''){
                        $sub_grade = self::gradeFromLookupOrAlias($gradeName2, $gradeLookup);
                    }

                }elseif(str_contains($gradeName, '/ ')){
                    $gradeParts = explode('/ ', $gradeName);
                    $gradeName1 = $gradeParts[0];
                    $grade = self::gradeFromLookupOrAlias($gradeName1, $gradeLookup, $stock);

                    $gradeName2 = $gradeParts[1] ?? '';
                    if($gradeName2 !== ''){
                        $sub_grade = self::gradeFromLookupOrAlias($gradeName2, $gradeLookup);
                    }

                }else{
                    $grade = self::gradeFromLookupOrAlias($gradeName, $gradeLookup, $stock, true);
                }

                if($grade === null){
                    self::recordDebugPoint($request, 'unknown grade mapping encountered', [
                        'grade' => $gradeName,
                        'request_sample' => substr((string) $request->request, 0, 500),
                    ]);
                    echo $gradeName;
                    continue;
                }
            }

            $adminName = self::normalizeValue($datas->TesterName);

            if (isset($adminLookup[$adminName])) {
                $admin = $adminLookup[$adminName];
            }else{
                if(config('app.url') == 'https://sdpos.nibritaintech.com' && $stock != null){


                    if($stock != null && $stock->last_item() != null && $stock->last_item()->order->customer_id == 3955 && $stock->status == 2){

                        $admin = 11;
                    }elseif($stock != null && $stock->last_item() != null && $stock->last_item()->order->customer_id == 16059 && $stock->status == 2){

                        $admin = 43;
                    }else{
                        echo "Please create/change Team Member First Name to: ".$adminName;
                        continue;
                    }
                }else{
                    self::recordDebugPoint($request, 'tester name missing from admin list', [
                        'tester' => $adminName,
                        'stock_id' => $stock->id ?? null,
                    ]);
                    echo "Hello";
                    echo "Please create/change Team Member First Name to: ".$adminName;
                    continue;
                }
                // }
            }


            if($stock != null){
                $p = $stock->variation->product ?? null;
                if(((str_contains(strtolower($datas->Comments), 'dual-esim') || str_contains(strtolower($datas->Comments), 'dual esim') || str_contains(strtolower($datas->Comments), 'dual_esim') || str_contains(strtolower($datas->Comments), 'dual e-sim')) && $p->brand == 1) && !str_contains($p->model, 'Dual eSIM')){
                    $product = Products_model::firstOrNew(['model'=>$p->model.' Dual eSIM']);
                    if(!$product->id){
                        // Log::info($p->model.' '.'Dual eSIM');
                        $log = 1;
                        $log_info .= $p->model.' '.'Dual eSIM'."\n";
                        continue;
                        // $product->category = $p->category;
                        // $product->brand = $p->brand;
                        // $product->model = $p->model.' Dual eSIM';
                        // $product->save();
                    }
                    $p = $product;


                    $new_variation = [
                        'product_id' => $p->id,
                        'storage' => $stock->variation->storage,
                        'color' => $stock->variation->color,
                        'grade' => $stock->variation->grade,
                    ];
                    if(isset($sub_grade) && $grade > 5){
                        $new_variation['sub_grade'] = $sub_grade;
                    }

                    $variation = Variation_model::firstOrNew($new_variation);
                    if($variation->id == null){
                        $variation->status = 1;
                        $variation->save();
                    }
                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock->id,
                        'api_request_id' => $request->id,
                        'old_variation_id' => $stock->variation_id,
                        'new_variation_id' => $variation->id,
                        'description' => "Dual-eSim Declared by tester | DrPhone",
                        'admin_id' => $admin,
                        'created_at' => Carbon::parse($datas->Time)->format('Y-m-d H:i:s'),
                    ]);

                    $stock->variation_id = $variation->id;
                    $stock->save();

                    $stock = Stock_model::find($stock->id);
                    self::cacheStockForKeys(self::buildStockKeys($datas), $stock);

                }

                if(((str_contains(strtolower($datas->Comments), 'dual-esim') || str_contains(strtolower($datas->Comments), 'dual esim') || str_contains(strtolower($datas->Comments), 'dual_esim') || str_contains(strtolower($datas->Comments), 'dual sim') || str_contains(strtolower($datas->Comments), 'dual-sim') || str_contains(strtolower($datas->Comments), 'dual_sim')) && $p->brand == 2) && !str_contains($p->model, 'Dual Sim')){
                    $product = Products_model::firstOrNew(['model'=>$p->model.' Dual Sim']);
                    if(!$product->id){
                        // Log::info($p->model.' '.'Dual Sim');
                        $log = 1;
                        $log_info .= $p->model.' '.'Dual Sim'."\n";
                        continue;
                        // $product->category = $p->category;
                        // $product->brand = $p->brand;
                        // $product->model = $p->model.' Dual Sim';
                        // $product->save();
                    }
                    $p = $product;


                    $new_variation = [
                        'product_id' => $p->id,
                        'storage' => $stock->variation->storage,
                        'color' => $stock->variation->color,
                        'grade' => $stock->variation->grade,
                    ];
                    if(isset($sub_grade) && $grade > 5){
                        $new_variation['sub_grade'] = $sub_grade;
                    }

                    $variation = Variation_model::firstOrNew($new_variation);
                    if($variation->id == null){
                        $variation->status = 1;
                        $variation->save();
                    }
                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock->id,
                        'api_request_id' => $request->id,
                        'old_variation_id' => $stock->variation_id,
                        'new_variation_id' => $variation->id,
                        'description' => "Dual-Sim Declared by tester | DrPhone",
                        'admin_id' => $admin,
                        'created_at' => Carbon::parse($datas->Time)->format('Y-m-d H:i:s'),
                    ]);

                    $stock->variation_id = $variation->id;
                    $stock->save();

                    $stock = Stock_model::find($stock->id);
                    self::cacheStockForKeys(self::buildStockKeys($datas), $stock);

                }
                if((str_contains(strtolower($datas->Comments), 'new-battery') || str_contains(strtolower($datas->Comments), 'new battery') || str_contains(strtolower($datas->Comments), 'new_battery')) && !str_contains($p->model, 'New Battery')){
                    $product = Products_model::firstOrNew(['model'=>$p->model.' New Battery']);
                    if(!$product->id){
                        // Log::info($p->model.' '.'New Battery');
                        $log_info .= $p->model.' '.'New Battery'."\n";
                        continue;
                        // $product->category = $p->category;
                        // $product->brand = $p->brand;
                        // $product->model = $p->model.' New Battery';
                        // $product->save();
                    }
                    $p = $product;


                    $new_variation = [
                        'product_id' => $p->id,
                        'storage' => $stock->variation->storage,
                        'color' => $stock->variation->color,
                        'grade' => $stock->variation->grade,
                    ];
                    if(isset($sub_grade) && $grade > 5){
                        $new_variation['sub_grade'] = $sub_grade;
                    }

                    $variation = Variation_model::firstOrNew($new_variation);
                    if($variation->id == null){
                        $variation->status = 1;
                        $variation->save();
                    }
                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock->id,
                        'api_request_id' => $request->id,
                        'old_variation_id' => $stock->variation_id,
                        'new_variation_id' => $variation->id,
                        'description' => "New-Battery Declared by tester | DrPhone",
                        'admin_id' => $admin,
                        'created_at' => Carbon::parse($datas->Time)->format('Y-m-d H:i:s'),
                    ]);

                    $stock->variation_id = $variation->id;
                    $stock->save();

                    $stock = Stock_model::find($stock->id);
                    self::cacheStockForKeys(self::buildStockKeys($datas), $stock);

                }
                $new_variation = [
                    'product_id' => $stock->variation->product_id ?? null,
                    'storage' => $stock->variation->storage ?? null,
                    'color' => $stock->variation->color ?? null,
                    'grade' => $stock->variation->grade ?? null,
                ];
                if(isset($sub_grade)){
                    $new_variation['sub_grade'] = $sub_grade;
                }
                if($storage != 0){
                    $new_variation['storage'] = $storage;
                }
                unset($message);
                if($stock->variation != null){
                    if($stock->variation != null && $stock->variation->storage != null && $stock->variation->storage != 0 && $stock->variation->storage != $storage && $storage != 0){
                        $message = "Storage changed from: ".$stock->variation->storage_id->name." to: ".$storages[$storage];
                        // dd($message, $stock, $datas);
                    }
                    if($stock->variation->color == null){
                        $check_merge_color = Product_color_merge_model::where(['product_id' => $stock->variation->product_id, 'color_from' => $color])->first();
                        if($check_merge_color != null){
                            $color = $check_merge_color->color_to;
                        }
                        $new_variation['color'] = $color;
                    }

                    if(($stock->variation->grade == 9 || $stock->variation->grade == 7 || $stock->variation->grade == $grade) && $grade != ''){
                        $new_variation['grade'] = $grade;
                    }
                    if($stock->status == 1){
                        $new_variation['grade'] = $grade;
                    }
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

                    if($stock->last_item() != null && $stock->last_item()->order->customer_id == 3955 && $stock->status == 2){


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


                        if($response){
                            echo "<pre>";
                            print_r($response);
                            echo "</pre>";
                            echo "<br><br><br>Hello<br><br><br>";
                        }

                    }


                    if(isset($message)){
                        $message = preg_replace('/\s+/', ' ', $message);
                        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

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

                    $m = $datas->Comments;

                    $m = preg_replace('/\s+/', ' ', $m);
                    $m = htmlspecialchars($m, ENT_QUOTES, 'UTF-8');

                    $stock_operation = new Stock_operations_model();
                    $stock_operation->new_operation($stock->id, null, 1, $request->id, $stock->variation_id, $variation->id, $fail." | ".$m." | DrPhone", $admin, Carbon::parse($datas->Time)->format('Y-m-d H:i:s'));
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

                    $region = Region_model::firstOrNew(['name' => $datas->Regioncode]);
                    if(!$region->id){
                        $region->save();
                    }
                    if($stock->region_id == null || $stock->region_id == 0){
                        $stock->region_id = $region->id;
                    }elseif($stock->region_id != $region->id && $stock->region_id != 0 && $stock->region_id != null && $region->id != 0 && $region->id != null){
                        $stock_operation = Stock_operations_model::create([
                            'stock_id' => $stock->id,
                            'api_request_id' => $request->id,
                            'old_variation_id' => $stock->variation_id,
                            'new_variation_id' => $variation->id,
                            'description' => "Region changed from: ".($stock->region->name ?? null)." to: ".($region->name ?? null)." | DrPhone",
                            'admin_id' => $admin,
                            'created_at' => Carbon::parse($datas->Time)->format('Y-m-d H:i:s'),
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
                    echo " Stock is already sold";
                    $request->stock_id = $stock->id;
                    $request->status = 2;
                    $request->save();
                }
            }
        }
        if(isset($log) && $log == 1){
            $log_info .= "Add these products manually:"."\n";
            Log::info($log_info);
        }

        self::flushDebugPoints();
        self::resetPushTestingCaches();

        return $return;
    }

    protected static function recordDebugPoint(Api_request_model $request, string $reason, array $context = []): void
    {
        $requestId = $request->id ?? 0;
        if(!isset(self::$debugBuffer[$requestId])){
            self::$debugBuffer[$requestId] = [];
        }
        self::$debugBuffer[$requestId][] = [
            'reason' => $reason,
            'context' => $context,
        ];
    }

    protected static function flushDebugPoints(): void
    {
        foreach(self::$debugBuffer as $requestId => $entries){
            Log::debug('api_request push_testing combined debug', [
                'api_request_id' => $requestId,
                'debug_entries' => $entries,
            ]);
        }

        self::$debugBuffer = [];
    }

    protected static function resetPushTestingCaches(): void
    {
        self::$stockCache = [];
    }

    protected static function resolveStock($datas): ?Stock_model
    {
        $keys = self::buildStockKeys($datas);
        foreach($keys as $key){
            if($key === null){
                continue;
            }
            if(array_key_exists($key, self::$stockCache)){
                $cached = self::$stockCache[$key];
                return $cached ?: null;
            }
        }

        $hasIdentifier = false;
        $query = Stock_model::query()->with(['variation.product', 'variation.storage_id']);

        $identifiers = [
            ['field' => 'imei', 'value' => $datas->Imei ?? null],
            ['field' => 'imei', 'value' => $datas->Imei2 ?? null],
            ['field' => 'serial_number', 'value' => $datas->Serial ?? null],
        ];

        foreach($identifiers as $identifier){
            $value = trim((string) $identifier['value']);
            if($value === ''){
                continue;
            }
            if($hasIdentifier){
                $query->orWhere($identifier['field'], $value);
            }else{
                $query->where($identifier['field'], $value);
                $hasIdentifier = true;
            }
        }

        if(!$hasIdentifier){
            self::cacheStockForKeys($keys, null);
            return null;
        }

        $stock = $query->first();

        self::cacheStockForKeys($keys, $stock);

        return $stock;
    }

    protected static function cacheStockForKeys(array $keys, ?Stock_model $stock): void
    {
        foreach($keys as $key){
            if($key === null){
                continue;
            }
            self::$stockCache[$key] = $stock ?: false;
        }
    }

    protected static function buildStockKeys($datas): array
    {
        $keys = [];

        $identifiers = [
            ['prefix' => 'imei:', 'value' => $datas->Imei ?? null],
            ['prefix' => 'imei:', 'value' => $datas->Imei2 ?? null],
            ['prefix' => 'serial:', 'value' => $datas->Serial ?? null],
        ];

        foreach($identifiers as $identifier){
            $value = trim((string) $identifier['value']);
            if($value === ''){
                continue;
            }
            $keys[] = $identifier['prefix'].$value;
        }

        return $keys;
    }

    protected static function buildLookup(array $items): array
    {
        $lookup = [];
        foreach($items as $id => $value){
            $normalized = self::normalizeValue($value);
            if($normalized === ''){
                continue;
            }
            $lookup[$normalized] = $id;
        }

        return $lookup;
    }

    protected static function normalizeValue($value): string
    {
        return strtolower(trim((string) $value));
    }

    protected static function gradeFromLookupOrAlias($value, array $gradeLookup, ?Stock_model $stock = null, bool $allowBlankDefault = false): ?int
    {
        $normalized = self::normalizeValue($value);

        if($normalized === ''){
            return $allowBlankDefault ? 7 : null;
        }

        if(isset($gradeLookup[$normalized])){
            return $gradeLookup[$normalized];
        }

        switch($normalized){
            case 'ws':
                return 11;
            case 'bt':
                return 21;
            case 'cd':
                return 24;
            case 'ug':
            case 'a+':
            case 'a/a+':
                return 7;
            case 'd':
                return $stock->variation->grade ?? null;
            case 'a':
            case 'verygood':
                return 2;
            case 'a-':
            case 'b':
                return 3;
            case 'ab':
            case 'c':
            case 'ok':
                return 5;
            default:
                return null;
        }
    }

    public function stock(){
        return $this->hasOne(Stock_model::class, 'id', 'stock_id');
    }
    public function find_serial_request($serial){
        $requests = Api_request_model::whereBetween('created_at', [
                $this->created_at->copy()->subDays(5),
                $this->created_at->copy()->addDays(5)
            ])
            ->whereNotNull('stock_id')
            ->get();

        foreach ($requests as $request) {

            $data = $request->request;
            $datas = json_decode($data);

            if (is_string($datas)) {
                continue;
            }
            if ($datas->Serial == $serial) {
                $this->update([
                    'stock_id' => $request->stock_id,
                    'status' => 1
                ]);
                return $request;
            }
        }

        return null;
    }

    public function send_to_eg(){
        $request = $this;
        $datas = json_decode($request->request);
        $stock = $this->stock;

        // dd($datas, $stock, $request);
            // if domain = sdpos.nibritaintech.com
        if(config('app.url') == 'https://sdpos.nibritaintech.com'){

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


            if($response){
                echo "<pre>";
                print_r($response);
                echo "</pre>";
                echo "<br><br><br>Hello<br><br><br>";
            }

            $request->status = 3;
            $request->save();

        }
    }
    public function send_to_yk(){
        $request = $this;
        $datas = json_decode($request->request);
        $stock = $this->stock;

        // dd($datas, $stock, $request);
            // if domain = sdpos.nibritaintech.com
        if(config('app.url') == 'https://sdpos.nibritaintech.com'){

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://ykpos.nibritaintech.com/api/request',
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


            if($response){
                echo "<pre>";
                print_r($response);
                echo "</pre>";
                echo "<br><br><br>Hello<br><br><br>";
            }

            $request->status = 3;
            $request->save();

        }
    }
}
