<?php

namespace App\Http\Livewire;

use App\Models\Country_model;
use Livewire\Component;
use App\Models\Grade_model;
use App\Models\Order_model;
use App\Models\Role_model;


class Grade extends Component
{
    public function render()
    {


        $recipientEmail = 'wethesd@gmail.com';
        $subject = 'Database Backup';
        $body = 'Here are the database backup files.';

        $email = app(GoogleController::class)->sendEmail($recipientEmail, $subject, $body);

        $data['grades'] = Grade_model::all();

        // foreach($data['grades'] as $grade){
        //     if($grade->orders->count() == 0){
        //         $grade->delete();
        //         $grade->forceDelete();
        //     }
        // }
        return view('livewire.grade')->with($data);
    }
    public function add_grade()
    {

        $data['countries'] = Country_model::all();
        return view('livewire.add-grade')->with($data);
    }

    public function insert_grade()
    {


        Grade_model::insert(['name'=>request('name')]);
        session()->put('success',"Grade has been added successfully");
        return redirect('grade');
    }

    public function edit_grade($id)
    {

        $data['grade'] = Grade_model::where('id',$id)->first();

        // dd($orders);

        return view('livewire.edit-grade')->with($data);
    }
    public function update_grade($id)
    {

        Grade_model::where('id',$id)->update(['name'=>request('name')]);
        session()->put('success',"Grade has been updated successfully");
        return redirect('grade');
    }
}
