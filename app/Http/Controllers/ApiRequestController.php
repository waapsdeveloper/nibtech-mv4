<?php

namespace App\Http\Controllers;

use App\Models\Api_request_model;
use Illuminate\Http\Request;

class ApiRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if($request->Serial != '' || $request->Imei != '' || $request->Imei2 != ''){
            $datas = $request->getContent();
            $data = json_encode($datas);
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
            // $datas = json_decode($datas);
            // $datas = json_decode($datas);
            // echo "Hello";
            unset($datas->OEMData);
            // dd($datas);
            $datas = json_encode($datas);
            // Create or update the resource
            $api_request = Api_request_model::firstOrNew([
                'request' => $datas,
            ]);
            $api_request->save();
            // Return response
            return response()->json([
                'status' => 'Success',
                'message' => 'Data received',
                'system_reference' => $api_request->id,
            ], 200);
        }else{
            // Return response
            return response()->json([
                'status' => 'Failed',
                'message' => 'Missing IMEI and Serial',
            ], 400);

        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function request_drfones()
    {
        //

        $drfones_url = 'http://3.138.251.239:8080/CloudLookup';
        $user_id = $_ENV['DRFONES_USER_ID'];

        $imei = request('imei');
        if (!$imei) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Missing IMEI',
            ], 400);
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $drfones_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query([
            'USERID' => $user_id,
            'IMEI' => $imei,
            ]),
            CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($httpCode !== 200) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Error sending data to DRFones',
            ], $httpCode);
        }
        $responseData = json_decode($response, true);


        if (!empty($responseData['Data']) && is_array($responseData['Data'])) {
            $data = is_array($responseData['Data'][0] ?? null) ? $responseData['Data'][0] : $responseData['Data'];
            $request = new Request($data);
            $this->store($request);
            return response()->json([
                'status' => 'Success',
                'message' => 'Data processed',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'status' => 'Failed',
                'message' => 'No valid data received from DRFones',
            ], 400);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
