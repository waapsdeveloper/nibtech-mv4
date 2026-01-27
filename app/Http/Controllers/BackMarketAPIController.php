<?php

namespace App\Http\Controllers;

use App\Models\Country_model;
use App\Models\Marketplace_model;
use App\Services\V2\SlackLogService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;



class BackMarketAPIController extends Controller
{
    protected static $base_url;
    protected static $COUNTRY_CODE = 'en-gb';
    protected static $YOUR_ACCESS_TOKEN;
    protected static $YOUR_USER_AGENT;

    public function __construct() {
        $token = null;

        try {
            $token = Marketplace_model::where('name', 'BackMarket')->first()?->api_key;
        } catch (Throwable $exception) {
            Log::warning('BackMarket API token lookup failed.', [
                'message' => $exception->getMessage(),
            ]);
        }

        $envToken = env('BM_API1');
        self::$YOUR_ACCESS_TOKEN = $token ?? $envToken;

        self::$base_url = rtrim(config('services.backmarket.base_url', 'https://www.backmarket.fr/ws/'), '/') . '/';

        if (! self::$YOUR_ACCESS_TOKEN) {
            throw new RuntimeException('Back Market API token is missing. Set BM_API1 or populate the marketplace table.');
        }

        self::$YOUR_USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36";
    }
    public function requestGet($end_point, $retryCount = 0){
        if(substr($end_point, 0, 1) === '/') {
            $end_point = substr($end_point, 1);
        }

        $api_call_data['Content-Type'] = 'application/json';
        $api_call_data['Accept'] = 'application/json';
        $api_call_data['Accept-Language'] = self::$COUNTRY_CODE;
        $api_call_data['Authorization'] = 'Basic ' . self::$YOUR_ACCESS_TOKEN;
        $api_call_data['User-Agent'] = self::$YOUR_USER_AGENT;

        $headers = [];
        foreach($api_call_data as $key => $value) {
            array_push($headers, "$key:$value");
        }

        $target_url = self::$base_url . $end_point;

        // Specify the URL
        $url = "https://www.backmarket.fr/ws/sav";

        // Make the GET request
        $response = Http::withHeaders([
            "Accept" => "application/json",
            "Authorization" => $api_call_data['Authorization'],
        ])->get($target_url);
        // print_r($response);
        $result = json_decode($response);

        if (isset($result->error) && $result->error->code == 'E008') {
            if ($retryCount < 5) {
                $waitTime = pow(2, $retryCount);
                sleep($waitTime);
                return $this->requestGet($end_point, $retryCount + 1);
            } else {
                // throw new Exception("Exceeded maximum retries for API request");

                // Log::channel('slack')->info("Care API: Exceeded maximum retries for API request".json_encode($result));
            }
        }

        return $result;
    }
    public function requestPatch($end_point, $patch_data){
        if(substr($end_point, 0, 1) === '/') {
            $end_point = substr($end_point, 1);
        }

        $api_call_data['Content-Type'] = 'application/json';
        $api_call_data['Accept'] = 'application/json';
        $api_call_data['Accept-Language'] = self::$COUNTRY_CODE;
        $api_call_data['Authorization'] = 'Basic ' . self::$YOUR_ACCESS_TOKEN;
        $api_call_data['User-Agent'] = self::$YOUR_USER_AGENT;

        $headers = [];
        foreach($api_call_data as $key => $value) {
            array_push($headers, "$key:$value");
        }

        $target_url = self::$base_url . $end_point;

        // Specify the URL
        $url = "https://www.backmarket.fr/ws/sav";

        // Basic Authorization Header
        $authorizationHeader = "Basic ".config('backmarket.api_key_2');

        // Make the PATCH request
        $response = Http::withHeaders([
            'Content-Type' => 'application/json', // Set the Content-Type header to JSON
            'Authorization' => $authorizationHeader, // Add authorization token/header if required
        ])->patch($target_url, $patch_data);

        return json_decode($response);
    }
    public function apiGet($end_point, $country_code = null) {
        if($country_code == null){
            $country_code = self::$COUNTRY_CODE;
        }
        if(substr($end_point, 0, 1) === '/') {
            $end_point = substr($end_point, 1);
        }

        $api_call_data['Content-Type'] = 'application/json';
        $api_call_data['Accept'] = 'application/json';
        $api_call_data['Accept-Language'] = $country_code;
        $api_call_data['Authorization'] = 'Basic ' . self::$YOUR_ACCESS_TOKEN;
        $api_call_data['User-Agent'] = self::$YOUR_USER_AGENT;

        $headers = [];
        foreach($api_call_data as $key => $value) {
            array_push($headers, "$key:$value");
        }

        $target_url = self::$base_url . $end_point;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Increased timeout for bulk operations (was 60, now 300 = 5 minutes)
        // This prevents premature timeouts during getAllListings() which can take 9+ minutes
        curl_setopt($ch, CURLOPT_TIMEOUT, '300');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, '30'); // Connection timeout separate from total timeout
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

        $get_result = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // if(config('app.name') == 'EliteGadget'){
        //     Log::info("API GET: ".json_encode($api_call_data).$get_result);
        // }
        return json_decode($get_result);
    }

    public function apiPostWithMeta($end_point, $request = '', $country_code = null): array {
        if($country_code == null){
            $country_code = self::$COUNTRY_CODE;
        }
        if(substr($end_point, 0, 1) === '/') {
            $end_point = substr($end_point, 1);
        }

        $api_call_data['Content-Type'] = 'application/json';
        $api_call_data['Accept'] = 'application/json';
        $api_call_data['Accept-Language'] = $country_code;
        $api_call_data['Authorization'] = 'Basic ' . self::$YOUR_ACCESS_TOKEN;
        $api_call_data['User-Agent'] = self::$YOUR_USER_AGENT;

        $headers = [];
        foreach($api_call_data as $key => $value) {
            array_push($headers, "$key:$value");
        }

        $target_url = self::$base_url . $end_point;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, '60');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POST, true);
        if ($request) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        }

        $post_result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode($post_result, true);

        return [
            'status' => $http_code,
            'decoded' => $decoded,
            'raw' => $post_result,
            'error' => $curl_error ?: null,
        ];
    }

    public function apiPost($end_point, $request = '', $country_code = null) {
        $meta = $this->apiPostWithMeta($end_point, $request, $country_code);

        return $meta['decoded'];
    }

    public function sendCareMessageMeta($folderId, string $message, $country_code = null): array
    {
        if($country_code == null){
            $country_code = self::$COUNTRY_CODE;
        }

        $folderId = trim((string) $folderId);

        if ($folderId === '') {
            throw new RuntimeException('Care folder id is required to post messages.');
        }

        $url = rtrim(self::$base_url, '/') . '/sav/' . $folderId . '/msg';

        // Use config('backmarket.api_key_2') if available (for Care API), otherwise fall back to main token
        $authToken = config('backmarket.api_key_2') ?: self::$YOUR_ACCESS_TOKEN;

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . $authToken,
            'Accept-Language' => $country_code,
            'User-Agent' => self::$YOUR_USER_AGENT,
        ])->asMultipart()->post($url, [
            ['name' => 'message', 'contents' => $message],
        ]);

        return [
            'status' => $response->status(),
            'decoded' => $response->json(),
            'raw' => $response->body(),
            'error' => $response->successful() ? null : ($response->body() ?: 'HTTP '.$response->status()),
            'url' => $url,
            'token_source' => config('backmarket.api_key_2') ? 'config(backmarket.api_key_2)' : 'BM_API1',
        ];
    }

    public function apiPatch($end_point, $request = '', $content_type='application/json') {
        if(substr($end_point, 0, 1) === '/') {
            $end_point = substr($end_point, 1);
        }

        $api_call_data['Content-Type'] = $content_type;
        $api_call_data['Accept'] = $content_type;
        $api_call_data['Accept-Language'] = self::$COUNTRY_CODE;
        $api_call_data['Authorization'] = 'Basic ' . self::$YOUR_ACCESS_TOKEN;
        $api_call_data['User-Agent'] = self::$YOUR_USER_AGENT;

        $headers = [];
        foreach($api_call_data as $key => $value) {
            array_push($headers, "$key:$value");
        }

        $target_url = self::$base_url . $end_point;

        // Send the POST request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, '60');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POST, true);
        if ($request) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        }

        $post_result = curl_exec($ch);

        $error = (curl_error($ch));
        echo $error;
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($post_result);
    }

    public function sendCareMessageWithAttachment($folderId, string $message, array $attachment): array
    {
        $folderId = trim((string) $folderId);

        if ($folderId === '') {
            throw new RuntimeException('Care folder id is required to post attachments.');
        }

        $url = rtrim(self::$base_url, '/') . '/sav/' . $folderId . '/msg';

        // Use config('backmarket.api_key_2') if available (for Care API), otherwise fall back to main token
        $authToken = config('backmarket.api_key_2') ?: self::$YOUR_ACCESS_TOKEN;

        $response = Http::asMultipart()
            ->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . $authToken,
            ])
            // Back Market docs show a single part named "attachment"; avoid array syntax to ensure the file is linked.
            ->attach(
                'attachment',
                $attachment['data'] ?? '',
                $attachment['name'] ?? 'invoice.pdf',
                isset($attachment['mime']) && $attachment['mime'] ? ['Content-Type' => $attachment['mime']] : []
            )
            ->post($url, [
                'message' => $message,
            ]);

        if ($response->failed()) {
            Log::error('Care API attachment post failed', [
                'folder_id' => $folderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Care API attachment post failed (HTTP ' . $response->status() . '): ' . $response->body());
        }

        return $response->json() ?? [];
    }

    public function shippingOrderlines($order_id, $sku, $imei, $tracking_number, $serial = null) {
        $end_point = 'orders/'.$order_id;

        $new_state = 3;
        // construct the request body when state == 3
        if($imei == false){
            // dd('Hello');
            $request_shipping = array('order_id' => $order_id, 'sku' => $sku, 'new_state' => $new_state, 'tracking_number' => $tracking_number );
        }elseif($serial != null){
            $request_shipping = array('order_id' => $order_id, 'sku' => $sku, 'new_state' => $new_state, 'tracking_number' => $tracking_number, 'serial_number' => $serial );
        }else{
            $request_shipping = array('order_id' => $order_id, 'sku' => $sku, 'new_state' => $new_state, 'tracking_number' => $tracking_number, 'imei' => $imei );
        }

        $request_JSON = json_encode($request_shipping);

        // echo $end_point."\n";
        // echo $request_JSON;
        $result = $this->apiPost($end_point, $request_JSON);

        return $result;
    }

    public function orderlineIMEI($orderline_id, $imei, $serial = null) {
        $end_point = 'orderlines/'.$orderline_id;

        // construct the request body when state == 3
        if($serial != null){
            $request_shipping = array( 'serial_number' => $serial );
        }else{
            $request_shipping = array( 'imei' => $imei );
        }

        $request_JSON = json_encode($request_shipping);

        // echo $end_point."\n";
        // echo $request_JSON;
        $result = $this->apiPatch($end_point, $request_JSON);

        return $result;
    }

    public function careFeed(Request $request)
    {
        $since = $request->input('since');
        $filters = array_filter($request->only([
            'state',
            'priority',
            'topic',
            'orderline',
            'order_id',
            'last_id',
            'page',
            'page_size',
        ]), function ($value) {
            return $value !== null && $value !== '';
        });

        if ($request->filled('extra')) {
            parse_str($request->input('extra'), $extraFilters);
            foreach ($extraFilters as $key => $value) {
                if ($value !== null && $value !== '') {
                    $filters[$key] = $value;
                }
            }
        }

        try {
            $cases = $this->getAllCare($since ?: false, $filters);
        } catch (\Throwable $e) {
            Log::error('Care API probe failed', [
                'since' => $since,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Care API request failed.',
                'error' => $e->getMessage(),
            ], 500);
        }

        if (! is_array($cases)) {
            return response()->json([
                'message' => 'Care API response was empty or invalid.',
                'since' => $since,
                'filters' => $filters,
            ], 502);
        }

        return response()->json([
            'count' => count($cases),
            'since' => $since ?: null,
            'filters' => $filters,
            'data' => $cases,
        ]);
    }


    public function getAllCare($date_modification = false, $param = []) {
        if ($date_modification == false) {
            $date_modification = date("Y-m-d-H-i", time() - 2 * 24 * 60 * 60);
        }

        $query = array_merge([
            'last_modification_date' => $date_modification,
        ], $param);

        $end_point = 'sav?' . http_build_query($query);

        sleep(10);

        $result = $this->requestGet($end_point);
        if(isset($result->results)){
            $result_array = $result->results;

            $result_next = $result;
            $i = 1;
            $page = 1;
            // dd($result);
            while (($result_next->next) != null) {
                if($result_next->results){
                    sleep(5);

                    $i++;
                    $page++;
                    $end_point_next_tail = '&page=' . "$page";
                    $end_point_next = $end_point . $end_point_next_tail;
                    $result_next = $this->requestGet($end_point_next);
                    if(!isset($result_next->results)){
                        print_r($result_next);
                        break;
                    }
                    $result_next_array = $result_next->results;

                    foreach ($result_next_array as $key => $value) {
                        array_push($result_array, $result_next_array[$key]);
                    }
                    if($i == 50){
                        break;
                    }
                }
            }

            return $result_array;
        }else{
            SlackLogService::post('care_api', 'info', "Care API: " . json_encode($result), ['endpoint' => 'sav']);
        }
    }
    public function getCare($id) {
        $end_point = 'sav/'.$id;

        $result = $this->requestGet($end_point);
        return $result;
        if(isset($result->results)){
            $result_array = $result->results;

            $result_next = $result;
            $i = 1;
            $page = 1;
            // dd($result);
            while (($result_next->next) != null) {
                if($result_next->results){
                    sleep(5);

                    $i++;
                    $page++;
                    $end_point_next_tail = '&page=' . "$page";
                    $end_point_next = $end_point . $end_point_next_tail;
                    $result_next = $this->requestGet($end_point_next);
                    if(!isset($result_next->results)){
                        print_r($result_next);
                        break;
                    }else{
                        $result_next_array = $result_next->results;

                        foreach ($result_next_array as $key => $value) {
                            array_push($result_array, $result_next_array[$key]);
                        }
                        if($i == 50){
                            break;
                        }
                    }
                }
            }

            return $result_array;
        }else{
            SlackLogService::post('care_api', 'info', "Care API: " . json_encode($result), ['endpoint' => 'sav/' . $id]);
        }
    }
    public function getAllOrders($page = 1, $param = [], $date_modification = false) {
        $end_point = 'orders';

        if (!$date_modification) {
            $s = 0;
            $date_modification = date("Y-m-d+H:i:s", time() - 20 * 60 * 60);
            // $date_modification = "2024-04-21+00:00:00";
        }else{
            $s = 1;
        }

        $end_point .= "?date_modification=$date_modification";

        if (count($param) > 0) {
            $end_point .= '&' . http_build_query($param);
        }

        $result = $this->apiGet($end_point);

        // if($s == 1){
        //     dd($result);
        // }
        if(!isset($result->results)){
            SlackLogService::post('order_api', 'warning', "Order API: Missing results", [
                'response' => json_encode($result),
                'endpoint' => $end_point,
                'date_modification' => $date_modification
            ]);
            die();
        }
        $result_array = $result->results;

        $result_next = $result;
        $i = 1;
        // $page = 1;

        while (($result_next->next) != null) {
            $i++;
            $page++;
            $end_point_next_tail = '&page=' . "$page";
            $end_point_next = $end_point . $end_point_next_tail;
            $result_next = $this->apiGet($end_point_next);
            if(!isset($result_next->results)){
                dd($result_next);
            }
            $result_next_array = $result_next->results;

            foreach ($result_next_array as $key => $value) {
                array_push($result_array, $result_next_array[$key]);
            }
            if($i == 40){
                break;
            }

        }

        return $result_array;
    }

    public function get10Orders($date_modification = false, $date_creation = false, $param = []) {
        $end_point = 'orders';

        if (!$date_modification) {
            $date_modification = now()->subDays(1)->format('Y-m-d+H:i:s');

        }

        $end_point .= "?date_modification=$date_modification";

        if (count($param) > 0) {
            $end_point .= '&' . http_build_query($param);
        }

        $result = $this->apiGet($end_point);

        $result_array = $result->results;

        $result_next = $result;

        $page = 1;

        while (($result_next->next) != null) {
            $page++;
            $end_point_next_tail = '&page=' . "$page";
            $end_point_next = $end_point . $end_point_next_tail;
            $result_next = $this->apiGet($end_point_next);
            $result_next_array = $result_next->results;

            foreach ($result_next_array as $key => $value) {
                array_push($result_array, $result_next_array[$key]);
            }
        }

        return $result_array;
    }
    public function getOneOrder($order_id) {
        $end_point = 'orders/' . $order_id;
        $result = $this->apiGet($end_point);
        return $result;
    }
    public function getOrderlabel($order_id) {
        $end_point = 'shipping/v1/deliveries?order_id=' . $order_id;
        $result = $this->apiGet($end_point);
        return $result;
        // return redirect($result->results[0]->labelUrl);
    }

    public function getlabelData() {
        // $end_point = 'shipping/v1/deliveries?start_date=2024-04-01T00:00:00&end_date=2024-04-02T00:00:00';
        $end_point = 'shipping/v1/deliveries?order_id=45727918';
        $result = $this->apiGet($end_point);

        // $res0_array = $result0->results;
        $res1_array = $result->results;

        // $result0_next = $result0;
        $result1_next = $result;


        $page1 = 1;

        while (($result1_next->next) != null) {
            if($result1_next->results){
                $page1++;
                $end_point_next1_tail = '&page=' . "$page1";
                $end_point_next1 = $end_point . $end_point_next1_tail;
                $result1_next = $this->apiGet($end_point_next1);
                if($result1_next == null){
                    print_r($result1_next);
                }else{

                    $result_next1_array = $result1_next->results;

                    foreach ($result_next1_array as $key => $value) {
                        array_push($res1_array, $result_next1_array[$key]);
                    }
                }
            }
        }

        // return [
        //     // 'new_orders' => $res0_array,
        //     'shipped_orders' => $res1_array,
        // ];

        return $res1_array;
        // return redirect($result->results[0]->labelUrl);
    }
    public function getReturnLabelData() {
        // $end_point = 'shipping/v1/deliveries?start_date=2024-04-01T00:00:00&end_date=2024-04-02T00:00:00';
        $end_point = 'shipping/v1/returns?order_id=45727918';
        $result = $this->apiGet($end_point);

        // $res0_array = $result0->results;
        $res1_array = $result->results;

        // $result0_next = $result0;
        $result1_next = $result;


        $page1 = 1;

        while (($result1_next->next) != null) {
            if($result1_next->results){
                $page1++;
                $end_point_next1_tail = '&page=' . "$page1";
                $end_point_next1 = $end_point . $end_point_next1_tail;
                $result1_next = $this->apiGet($end_point_next1);
                if($result1_next == null){
                    print_r($result1_next);
                }else{

                    $result_next1_array = $result1_next->results;

                    foreach ($result_next1_array as $key => $value) {
                        array_push($res1_array, $result_next1_array[$key]);
                    }
                }
            }
        }

        // return [
        //     // 'new_orders' => $res0_array,
        //     'shipped_orders' => $res1_array,
        // ];

        return $res1_array;
        // return redirect($result->results[0]->labelUrl);
    }

    public function getNewOrders($param = []) {
        // $end_point_0 = 'orders?state=0';
        $end_point_1 = 'orders?state=1';

        if (count($param) > 0) {
            // $end_point_0 .= '&' . http_build_query($param);
            $end_point_1 .= '&' . http_build_query($param);
        }

        // $result0 = $this->apiGet($end_point_0);
        $result1 = $this->apiGet($end_point_1);

        // Check if API call was successful and has results property
        if (!$result1 || !is_object($result1) || !isset($result1->results)) {
            // Return empty array if API call failed or response doesn't have results
            Log::warning("BackMarketAPIController::getNewOrders: API response missing results property", [
                'endpoint' => $end_point_1,
                'result_type' => gettype($result1),
                'result' => $result1
            ]);
            return [];
        }

        // $res0_array = $result0->results;
        $res1_array = $result1->results ?? [];

        // $result0_next = $result0;
        $result1_next = $result1;


        $page1 = 1;

        while (isset($result1_next->next) && ($result1_next->next) != null) {
            if(isset($result1_next->results) && $result1_next->results){
                $page1++;
                $end_point_next1_tail = '&page=' . "$page1";
                $end_point_next1 = $end_point_1 . $end_point_next1_tail;
                $result1_next = $this->apiGet($end_point_next1);
                
                // Check if pagination API call was successful
                if (!$result1_next || !is_object($result1_next) || !isset($result1_next->results)) {
                    break; // Stop pagination if API call fails
                }
                
                $result_next1_array = $result1_next->results;

                if (is_array($result_next1_array)) {
                    foreach ($result_next1_array as $key => $value) {
                        array_push($res1_array, $result_next1_array[$key]);
                    }
                }
            } else {
                break; // Stop if no results in current page
            }
        }

        // return [
        //     // 'new_orders' => $res0_array,
        //     'shipped_orders' => $res1_array,
        // ];

        return $res1_array;
    }


    public function getProduct($product_id) {
        $end_point = 'products/' . $product_id;
        $result = $this->apiGet($end_point);
        return $result;
    }

    public function getProducts($param = []) {
        $end_point = 'products';

        if (count($param) > 0) {
            $end_point .= '?' . http_build_query($param);
        }

        $result = $this->apiGet($end_point);

        $result_array = $result->results;
        $result_next = $result;
        $page = 1;

        while (($result_next->next) != null) {
            $page++;
            $end_point_next_tail = '&page=' . "$page";
            $end_point_next = $end_point . $end_point_next_tail;
            $result_next = $this->apiGet($end_point_next);
            $result_next_array = $result_next->results;

            foreach ($result_next_array as $key => $value) {
                array_push($result_array, $result_next_array[$key]);
            }
        }

        return $result_array;
    }



    public function createListing($request_JSON) {
        $end_point = 'listings';
        $response = $this->apiPost($end_point, $request_JSON);
        return $response;
    }

    public function updateOneListing($listing_id, $request_JSON, $code = null, $skipBuffer = false) {
        // Parse request to get quantity
        $requestData = json_decode($request_JSON, true);
        
        // If quantity is provided, apply buffer if needed (skip buffer for V1 listing)
        if (isset($requestData['quantity']) && !$skipBuffer) {
            $variation = \App\Models\Variation_model::where('reference_id', $listing_id)->first();
            
            if ($variation) {
                // Get marketplace stock (default to marketplace_id = 1 for Back Market)
                $marketplaceStock = \App\Models\MarketplaceStockModel::where([
                    'variation_id' => $variation->id,
                    'marketplace_id' => 1
                ])->first();
                
                // If marketplace stock exists and has buffer_percentage, apply buffer
                if ($marketplaceStock && $marketplaceStock->buffer_percentage > 0) {
                    $originalQuantity = $requestData['quantity'];
                    $bufferPercentage = $marketplaceStock->buffer_percentage;
                    $bufferedQuantity = max(0, floor($originalQuantity * (1 - $bufferPercentage / 100)));
                    
                    // Update request with buffered quantity
                    $requestData['quantity'] = $bufferedQuantity;
                    $request_JSON = json_encode($requestData);
                    
                    // \Illuminate\Support\Facades\Log::info("Applied buffer to stock update", [
                    //     'variation_id' => $variation->id,
                    //     'listing_id' => $listing_id,
                    //     'original_quantity' => $originalQuantity,
                    //     'buffer_percentage' => $bufferPercentage,
                    //     'buffered_quantity' => $bufferedQuantity
                    // ]);
                }
            }
        }
        
        $end_point = 'listings/' . $listing_id;
        if($code != null){
            $response = $this->apiPost($end_point, $request_JSON, $code);
        }else{
            $response = $this->apiPost($end_point, $request_JSON);
        }

        return $response;
    }

    public function updateSeveralListings($csvFile) {
        $end_point = 'listings';

        $csv = $this->readCSV($csvFile);
        $request = ['encoding' => 'latin1', 'delimiter' => ";", 'quotechar' => "\n", 'header' => true, 'catalog' => $csv];
        $request_JSON = json_encode($request);

        $response = $this->apiPost($end_point, $request_JSON);
        return $response;
    }

    public function getOneListing($listing_id) {
        $end_point = 'listings/' . $listing_id;
        $result = $this->apiGet($end_point);
        return $result;
    }

    public function getListingCompetitors($listing_id) {
        $end_point = 'backbox/v1/competitors/' . $listing_id;
        $result = $this->apiGet($end_point);
        return $result;
    }
    // public function getAllListings($publication_state = null, $param = []) {
    //     $end_point = 'listings';

    //     if ($publication_state) {
    //         $param['publication_state'] = $publication_state;
    //     }

    //     if (!empty($param)) {
    //         $end_point .= '?' . http_build_query($param);
    //     }

    //     $result = $this->apiGet($end_point);
    //     return $result->results;
    // }

    public function getAllListings ($publication_state = null, $param = array()) {
        $country_codes = Country_model::where('market_code','!=',null)->pluck('market_code','id')->toArray();
        $totalCountries = count($country_codes);
        $processedCountries = 0;
        $startTime = microtime(true);
        $lastLogTime = $startTime;
        
        // Log start
        Log::info("BackMarketAPIController::getAllListings: Starting bulk fetch", [
            'total_countries' => $totalCountries,
            'publication_state' => $publication_state
        ]);
        
        // Increase page size for better performance (API allows up to 100)
        $pageSize = 100; // Increased from 50 to reduce API calls by 50%
        
        foreach($country_codes as $id => $code){
            $countryStartTime = microtime(true);
            
            $processedCountries++;
            
            // Log progress every 30 seconds or every 2 countries (whichever comes first)
            $currentTime = microtime(true);
            if ($currentTime - $lastLogTime >= 30 || $processedCountries % 2 == 0) {
                $elapsed = round($currentTime - $startTime, 1);
                $avgTimePerCountry = $elapsed / $processedCountries;
                $remainingCountries = $totalCountries - $processedCountries;
                $estimatedRemaining = round($avgTimePerCountry * $remainingCountries, 1);
                
                Log::info("BackMarketAPIController::getAllListings: Progress update", [
                    'processed' => $processedCountries,
                    'total' => $totalCountries,
                    'current_country' => $code,
                    'elapsed_seconds' => $elapsed,
                    'estimated_remaining_seconds' => $estimatedRemaining
                ]);
                $lastLogTime = $currentTime;
            }

            $end_point = 'listings';

            // Use larger page size for better performance (API supports up to 100)
            $end_point .= "?publication_state=$publication_state&page-size={$pageSize}";

            if (count($param) > 0) {
            $end_point .= '&'.http_build_query($param);
            }

            // result of the first page with retry logic
            $result = $this->apiGetWithRetry($end_point, $code, 3);
            
            if (!$result || !isset($result->results)) {
                Log::warning("BackMarketAPIController::getAllListings: Failed to fetch first page for country", [
                    'country_code' => $code,
                    'country_id' => $id
                ]);
                // Initialize empty array for this country to continue processing others
                if(!isset($result_array[$id])){
                    $result_array[$id] = [];
                }
                continue;
            }

            // array results of the first page
            if(!isset($result_array[$id])){
                $result_array[$id] = is_array($result->results) ? $result->results : [];
            }else{
                $firstPageResults = is_array($result->results) ? $result->results : [];
                $result_array[$id] = array_merge($result_array[$id], $firstPageResults);
            }
            $result_next = $result;

            $page = 1;
            $maxPages = 1000; // Safety limit to prevent infinite loops
            // Start batch mode to avoid spamming Slack if multiple pages fail
            SlackLogService::startBatch();
            
            // judge whether there exists the next page
            while (($result_next->next) != null && $page < $maxPages) {
                $page++;
                // get the new end point
                $end_point_next_tail = '&page='."$page";
                $end_point_next = $end_point.$end_point_next_tail;
                
                // the new page object with retry logic
                $result_next = $this->apiGetWithRetry($end_point_next, $code, 2);
                
                // the new page array
                if(!$result_next || !isset($result_next->results)){
                    // Collect log instead of posting immediately (inside loop)
                    SlackLogService::collectBatch('listing_api', 'warning', "Listing API: Missing results (page {$page})", [
                        'response' => $result_next ? json_encode($result_next) : 'null',
                        'endpoint' => $end_point_next,
                        'page' => $page,
                        'country_code' => $code
                    ]);
                    break;
                }
                $result_next_array = $result_next->results;
                
                // add all listings in current page to the $result_array (optimized array merge)
                if (is_array($result_next_array) && !empty($result_next_array)) {
                    // Use array_merge for better performance than foreach loop
                    $result_array[$id] = array_merge($result_array[$id], $result_next_array);
                }
                
                // Memory management: log memory usage every 50 pages
                if ($page % 50 == 0) {
                    $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
                    Log::debug("BackMarketAPIController::getAllListings: Memory usage", [
                        'country_code' => $code,
                        'page' => $page,
                        'memory_mb' => $memoryUsage
                    ]);
                }
            }
            
            // Post batch summary after loop completes (only if there were errors)
            SlackLogService::postBatch();
            
            $countryDuration = round(microtime(true) - $countryStartTime, 2);
            
            if ($countryDuration > 60) {
                Log::info("BackMarketAPIController::getAllListings: Country completed", [
                    'country_code' => $code,
                    'country_id' => $id,
                    'duration_seconds' => $countryDuration,
                    'pages' => $page,
                    'listings_count' => isset($result_array[$id]) ? count($result_array[$id]) : 0
                ]);
            }
        }
        
        $totalDuration = round(microtime(true) - $startTime, 2);
        $totalListings = 0;
        
        foreach ($result_array ?? [] as $countryListings) {
            $totalListings += is_array($countryListings) ? count($countryListings) : 0;
        }
        
        Log::info("BackMarketAPIController::getAllListings: Bulk fetch completed", [
            'total_duration_seconds' => $totalDuration,
            'countries_processed' => $processedCountries,
            'total_listings' => $totalListings,
            'avg_time_per_country_seconds' => $processedCountries > 0 ? round($totalDuration / $processedCountries, 2) : 0
        ]);
        
        return $result_array ?? [];
    }
    
    /**
     * API GET with retry logic and better error handling
     * 
     * @param string $end_point API endpoint
     * @param string|null $code Country code
     * @param int $maxRetries Maximum number of retries (default: 3)
     * @param int $retryDelay Initial delay between retries in seconds (default: 2)
     * @return object|null API response or null on failure
     */
    private function apiGetWithRetry($end_point, $code = null, $maxRetries = 3, $retryDelay = 2) {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $maxRetries) {
            try {
                $result = $this->apiGet($end_point, $code);
                
                // Check if result is valid
                if ($result && (is_object($result) || is_array($result))) {
                    return $result;
                }
                
                // If result is null or invalid, treat as failure
                if ($attempt < $maxRetries - 1) {
                    $attempt++;
                    $delay = $retryDelay * $attempt; // Exponential backoff
                    Log::debug("BackMarketAPIController::apiGetWithRetry: Retrying after invalid response", [
                        'endpoint' => $end_point,
                        'attempt' => $attempt,
                        'delay_seconds' => $delay
                    ]);
                    sleep($delay);
                    continue;
                }
                
                return null;
                
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;
                
                // Check if it's a timeout or network error
                $isRetryable = (
                    strpos($e->getMessage(), 'timeout') !== false ||
                    strpos($e->getMessage(), 'timed out') !== false ||
                    strpos($e->getMessage(), 'Connection') !== false ||
                    strpos($e->getMessage(), 'network') !== false ||
                    $e->getCode() == 0 // cURL errors often have code 0
                );
                
                if ($isRetryable && $attempt < $maxRetries) {
                    $delay = $retryDelay * $attempt; // Exponential backoff
                    Log::warning("BackMarketAPIController::apiGetWithRetry: Retrying after error", [
                        'endpoint' => $end_point,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'delay_seconds' => $delay
                    ]);
                    sleep($delay);
                    continue;
                }
                
                // Non-retryable error or max retries reached
                Log::error("BackMarketAPIController::apiGetWithRetry: Failed after retries", [
                    'endpoint' => $end_point,
                    'attempts' => $attempt,
                    'error' => $e->getMessage(),
                    'is_retryable' => $isRetryable
                ]);
                
                if ($attempt >= $maxRetries) {
                    return null;
                }
            }
        }
        
        return null;
    }
    public function getAllListingsBi ($param = array()) {
        $country_codes = Country_model::where('market_code','!=',null)->pluck('market_code','id')->toArray();
        
        // Start batch mode to avoid spamming Slack if multiple countries fail
        SlackLogService::startBatch();
        
        foreach($country_codes as $id => $code){
            $end_point = 'listings_bi';

            $end_point .= "?page-size=50";

            if (count($param) > 0) {
            $end_point .= '&'.http_build_query($param);
            }

            // result of the first page
            $result = $this->apiGet($end_point, $code);
            // print_r($result);
            if(!isset($result->results)){
                // Collect log instead of posting immediately (inside loop)
                SlackLogService::collectBatch('listing_api', 'warning', "ListingBI API: Missing results for country {$code}", [
                    'endpoint' => $end_point,
                    'response' => json_encode($result),
                    'country_code' => $code,
                    'country_id' => $id
                ]);
                if(!isset($result_array)){
                    die;
                }
                break;
            }
            // array results of the first page
            if(!isset($result_array[$id])){
                $result_array[$id] = $result->results;
            }else{
                array_push($result_array[$id], $result->results);

            }
            $result_next = $result;

            $page = 1;
            // judge whetehr there exists the next page
            while (($result_next->next) != null) {
                sleep(2);
            // for($i = 0; $i <= 3; $i++){
            $page++;
            // get the new end point
            $end_point_next_tail = '&page='."$page";
            $end_point_next = $end_point.$end_point_next_tail;
            // print_r($end_point_next);
            // the new page object
                $result_next = $this->apiGet($end_point_next, $code);
                // print_r($result_next);
            // the new page array
            $result_next_array = $result_next->results;
            // add all listings in current page to the $result_array
            foreach ($result_next_array as $key => $value) {
                array_push($result_array[$id], $result_next_array[$key]);
            }
            }
        }
        
        // Post batch summary after loop completes (only if there were errors)
        SlackLogService::postBatch();
        
        // print_r($result_array);
        return $result_array;
    }
    private function readCSV($file) {
        $data = null;

        if (!is_file($file) && !file_exists($file)) {
            die('File Error!');
        }

        $csv_file = fopen($file, 'r');

        while ($file_data = fgetcsv($csv_file)) {
            if ($file_data[0] !== '') {
                $data .= $file_data[0] . "\n";
            }
        }

        fclose($csv_file);
        return $data;
    }
}

