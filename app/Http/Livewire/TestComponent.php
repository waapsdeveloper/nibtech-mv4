<?php

namespace App\Http\Livewire;

use Livewire\Component;

class TestComponent extends Component
{
    public $message = "Hello from Livewire!";

    public function toggleMessage()
    {
        $this->message = $this->message === "Hello from Livewire!"
                         ? "Message toggled!"
                         : "Hello from Livewire!";
        $this->emit('toggle-message');
    }

    public function render()
    {
        return view('livewire.test-component');
    }

}
