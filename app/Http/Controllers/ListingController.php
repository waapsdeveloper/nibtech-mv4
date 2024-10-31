<?php

namespace App\Http\Controllers;

use App\Models\Color_model;
use App\Models\Grade_model;
use App\Models\Storage_model;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    //
    public function index()
    {

        $data['bm'] = new BackMarketAPIController();
        $data['storages'] = Storage_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['grades'] = Grade_model::where('id',"<",6)->pluck('name','id')->toArray();



        return view('listings')->with($data);
    }
}
