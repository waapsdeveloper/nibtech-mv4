<?php

namespace App\Http\Controllers;

use App\Models\testing_model;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class TestingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        return "Hadas";
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

        // Define validation rules
        $rules = [
            'reference_id' => 'required|string|max:50',
            'name' => 'required|string|max:50',
            'imei' => 'required|numeric',
            'serial_number' => 'nullable|string|max:20',
            'color' => 'required|string|max:20',
            'storage' => 'required|string|max:20',
            'battery_health' => 'required|string|max:5',
            'vendor_grade' => 'nullable|string|max:20',
            'grade' => 'nullable|string|max:20',
            'fault' => 'nullable|string|max:50',
            'tester' => 'required|string|max:10',
            'lot' => 'required|string|max:20',
            'status' => [
                'required',
                'numeric',
                Rule::in([1, 2]), // Assuming status can be one of these values
            ],
        ];

        // Validate the request
        $validatedData = $request->validate($rules);

        // Create or update the resource
        $testing = testing_model::firstOrNew(['reference_id' => $request->reference_id]);
        $testing->fill($validatedData);
        $testing->save();

        // Return response
        return response()->json([
            'message' => 'Data received',
            'system_reference' => $testing->id,
        ], 200);

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
    public function update(Request $request, $id)
    {
        //
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
