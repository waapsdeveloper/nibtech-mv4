<?php

namespace App\Http\Livewire;

use Livewire\Component;

class Logout extends Component
{
    public function mount()
    {
        session()->flush();
        return redirect()->route('login');
    }
    public function render()
    {
        session()->flush();
        return view('livewire.signin')->layout('layouts.custom-app');
    }
}
