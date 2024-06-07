<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;



class BackMarketAPIController extends Controller
{
    protected static $base_url = 'https://www.backmarket.fr/ws/';
    protected static $COUNTRY_CODE = 'en-gb';
    protected static $YOUR_ACCESS_TOKEN;
    protected static $YOUR_USER_AGENT;

    public function __construct() {
        self::$YOUR_ACCESS_TOKEN = "YmFlNDFiOWI5OTZiOGE0YjYyZGU3NjpCTVQtOGI3NjRmYThjMDhhOTYwMGIwYTFkYmUyYjA3NjEyNGY2M2I4NzZiNg==";
        self::$YOUR_USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36";
    }
    public function requestGet($end_point){
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
        $authorizationHeader = "Basic YmFlNDFiOWI5OTZiOGE0YjYyZGU3NjpCTVQtOGI3NjRmYThjMDhhOTYwMGIwYTFkYmUyYjA3NjEyNGY2M2I4NzZiNg==";

        // Make the GET request
        $response = Http::withHeaders([
            "Accept" => "application/json",
            "Authorization" => $authorizationHeader,
        ])->get($target_url);

        return json_decode($response);
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
        $authorizationHeader = "Basic YmFlNDFiOWI5OTZiOGE0YjYyZGU3NjpCTVQtOGI3NjRmYThjMDhhOTYwMGIwYTFkYmUyYjA3NjEyNGY2M2I4NzZiNg==";

        // Make the PATCH request
        $response = Http::withHeaders([
            'Content-Type' => 'application/json', // Set the Content-Type header to JSON
            'Authorization' => 'Bearer YOUR_ACCESS_TOKEN', // Add authorization token/header if required
        ])->patch($target_url, $patch_data);

        return json_decode($response);
    }
    public function apiGet($end_point) {
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

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, '60');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

        $get_result = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($get_result);
    }

    public function apiPost($end_point, $request = '', $content_type='application/json') {
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
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
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

    public function getAllCare($date_modification = false, $param = []) {


        $end_point = 'sav?';

        if ($date_modification == false) {
            $date_modification = date("Y-m-d-H-i", time() - 2 * 24 * 60 * 60);
        }

        $end_point .= "?last_modification_date=$date_modification";
        // $end_point .= "?last_message_date=$date_modification";

        if (count($param) > 0) {
            $end_point .= '&' . http_build_query($param);
        }

        $result = $this->requestGet($end_point);
        if(isset($result->results)){
            $result_array = $result->results;

            $result_next = $result;
            $i = 1;
            $page = 1;
            // dd($result);
            while (($result_next->next) != null) {
                if($result_next->results){

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
                    sleep(2);
                }
            }

            return $result_array;
        }
    }
    public function getAllOrders($page = 1, $param = []) {
        $end_point = 'orders';

        // if (!$date_modification) {
            $date_modification = date("Y-m-d+H:i:s", time() - 1 * 24 * 60 * 60);
            // $date_modification = "2024-04-21+00:00:00";
        // }

        $end_point .= "?date_modification=$date_modification";

        if (count($param) > 0) {
            $end_point .= '&' . http_build_query($param);
        }

        $result = $this->apiGet($end_point);

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
            if($result_next == null){
                dd($end_point_next);
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

    public function getNewOrders($param = []) {
        // $end_point_0 = 'orders?state=0';
        $end_point_1 = 'orders?state=1';

        if (count($param) > 0) {
            // $end_point_0 .= '&' . http_build_query($param);
            $end_point_1 .= '&' . http_build_query($param);
        }

        // $result0 = $this->apiGet($end_point_0);
        $result1 = $this->apiGet($end_point_1);

        // $res0_array = $result0->results;
        $res1_array = $result1->results;

        // $result0_next = $result0;
        $result1_next = $result1;


        $page1 = 1;

        while (($result1_next->next) != null) {
            if($result1_next->results){
                $page1++;
                $end_point_next1_tail = '&page=' . "$page1";
                $end_point_next1 = $end_point_1 . $end_point_next1_tail;
                $result1_next = $this->apiGet($end_point_next1);
                $result_next1_array = $result1_next->results;

                foreach ($result_next1_array as $key => $value) {
                    array_push($res1_array, $result_next1_array[$key]);
                }
            }
        }

        // return [
        //     // 'new_orders' => $res0_array,
        //     'shipped_orders' => $res1_array,
        // ];

        return $res1_array;
    }

// ... (previous methods remain unchanged)

    public function getBrands() {
        $end_point = 'brands';
        $result = $this->apiGet($end_point);
        return $result;
    }

    public function getBrand($brand_id) {
        $end_point = 'brands/' . $brand_id;
        $result = $this->apiGet($end_point);
        return $result;
    }

    public function getCategories() {
        $end_point = 'categories';
        $result = $this->apiGet($end_point);
        return $result;
    }

    public function getCategory($category_id) {
        $end_point = 'categories/' . $category_id;
        $result = $this->apiGet($end_point);
        return $result;
    }

    public function getModels() {
        $end_point = 'models';
        $result = $this->apiGet($end_point);
        return $result;
    }

    public function getModel($model_id) {
        $end_point = 'models/' . $model_id;
        $result = $this->apiGet($end_point);
        return $result;
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

    public function updateOneListing($listing_id, $request_JSON) {
        $end_point = 'listings/' . $listing_id;
        $response = $this->apiPost($end_point, $request_JSON);
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

    function getAllListings ($publication_state = null, $param = array()) {
        $end_point = 'listings';

        $end_point .= "?publication_state=$publication_state";

        if (count($param) > 0) {
          $end_point .= '&'.http_build_query($param);
        }

        // result of the first page
        $result = $this->apiGet($end_point);
        // print_r($result);

        // array results of the first page
        $result_array = $result->results;

        $result_next = $result;

        $page = 1;
        // judge whetehr there exists the next page
        while (($result_next->next) != null) {
        // for($i = 0; $i <= 3; $i++){
          $page++;
          // get the new end point
          $end_point_next_tail = '&page='."$page";
          $end_point_next = $end_point.$end_point_next_tail;
          // print_r($end_point_next);
          // the new page object
          $result_next = $this->apiGet($end_point_next);
          // the new page array
          $result_next_array = $result_next->results;
          // add all listings in current page to the $result_array
          foreach ($result_next_array as $key => $value) {
            array_push($result_array, $result_next_array[$key]);
          }
        }
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

