<?php

namespace App\Http\Livewire;

use App\Models\Daily_closing_model;
use Livewire\Component;

class DailyClosing extends Component
{
    public function render()
    {
        $data['title_page'] = "Daily Closing";
        session()->put('page_title', $data['title_page']);
        $data['daily_closings'] = Daily_closing_model::orderByDesc('created_at')->paginate(25);

        return view('livewire.daily-closing')->with($data);
    }
}
