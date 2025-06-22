<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Session;

class LeaveRequestForm extends Component
{
    public $start_date, $end_date, $type = 'Annual', $reason;

    protected $rules = [
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'type' => 'required',
        'reason' => 'nullable|string|max:500'
    ];

    public function submit()
    {
        $this->validate();

        LeaveRequest::create([
            'admin_id' => session('user_id'),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'leave_type' => $this->type,
            'reason' => $this->reason,
            'status' => 'Pending'
        ]);

        session()->flash('success', 'Leave request submitted!');
        $this->reset(['start_date', 'end_date', 'type', 'reason']);
        $this->emit('refreshLeaves'); // Emit event to refresh parent component
    }

    public function render()
    {
        return view('livewire.leave-request-form');
    }
}
