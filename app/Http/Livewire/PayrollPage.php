<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Admin_model;
use Carbon\Carbon;

class PayrollPage extends Component
{

    public $viewMode = 'employee'; // 'employee' or 'manager'
    public $adminId;
    public $attendance = [];
    public $leaveRequests = [];
    public $totalWorkedHours = '00:00:00';
    public $estimatedPay = 0;
    public function mount($adminId = null, $viewMode = 'employee')
    {
        $this->viewMode = $viewMode;
        $this->adminId = $adminId ?? session('user_id');
        $this->loadData();
    }

    public function loadData()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $this->attendance = Attendance::where('admin_id', $this->adminId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->with('dailyBreaks')
            ->get();

        $this->leaveRequests = LeaveRequest::where('admin_id', $this->adminId)
            ->whereBetween('start_date', [$startOfMonth, $endOfMonth])
            ->get();

        $this->calculateWorkAndPay();
    }

    public function calculateWorkAndPay()
    {
        $totalSeconds = 0;
        $admin = Admin_model::find($this->adminId);

        foreach ($this->attendance as $record) {
            if ($record->clock_in && $record->clock_out) {
                $workStart = Carbon::parse($record->clock_in);
                $workEnd = Carbon::parse($record->clock_out);
                $workedSeconds = $workEnd->diffInSeconds($workStart);

                // Subtract breaks time
                $breakSeconds = $record->dailyBreaks->sum(function ($break) {
                    if ($break->break_end) {
                        return Carbon::parse($break->break_end)->diffInSeconds(Carbon::parse($break->break_start));
                    }
                    return 0;
                });

                $totalSeconds += ($workedSeconds - $breakSeconds);
            }
        }

        $this->totalWorkedHours = gmdate('H:i:s', $totalSeconds);

        // Calculate estimated pay (example assumes hourly salary type)
        $rate = $admin->salary_type === 'hourly' ? $admin->salary_amount : 0;

        $this->estimatedPay = round($rate * ($totalSeconds / 3600), 2);
    }

    public function render()
    {
        return view('livewire.payroll-page');
    }
}
