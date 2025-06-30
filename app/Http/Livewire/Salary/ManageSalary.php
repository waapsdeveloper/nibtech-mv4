<?php

namespace App\Http\Livewire\Salary;

use Livewire\Component;
use App\Models\Admin_model;
use App\Models\Attendance;
use App\Models\Advance;
use App\Models\Salary;
use Carbon\Carbon;

class ManageSalary extends Component
{
    public $month, $salaries = [];

    public function mount()
    {
        $this->month = now()->format('Y-m');
        $this->calculateSalaries();
    }

    public function updatedMonth()
    {
        $this->calculateSalaries();
    }

    public function calculateSalaries()
    {
        $start = Carbon::parse($this->month)->startOfMonth();
        $end = Carbon::parse($this->month)->endOfMonth();

        $admins = Admin_model::all();
        $this->salaries = [];

        foreach ($admins as $admin) {
            $attendanceCount = Attendance::where('admin_id', $admin->id)
                ->whereBetween('date', [$start, $end])
                ->where('status', 'Present')
                ->count();

            $totalDays = $start->diffInDaysFiltered(fn($date) => !$date->isWeekend(), $end) + 1;
            $advances = Advance::where('admin_id', $admin->id)->whereBetween('advance_date', [$start, $end])->sum('amount');

            $base = $admin->salary ?? 0;
            $perDay = $base / $totalDays;
            $net = ($perDay * $attendanceCount) - $advances;

            $this->salaries[] = [
                'admin' => $admin->first_name,
                'base' => $base,
                'present' => $attendanceCount,
                'advances' => $advances,
                'net' => round($net, 2),
            ];
        }
    }
    public function render()
    {
        return view('livewire.salary.manage-salary');
    }
}
