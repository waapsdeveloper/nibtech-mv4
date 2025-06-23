<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use Carbon\Carbon;

class AttendanceCalendar extends Component
{
    public $adminId;
    public $viewMode = 'employee'; // 'employee' or 'manager'

    public $currentMonth;
    public $currentYear;
    public $calendarDays = [];


    public function mount($adminId = null, $viewMode = 'employee')
    {
        $this->adminId = $adminId ?? session('user_id');
        $this->viewMode = $viewMode;
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;

        $this->generateCalendar();
    }

    public function previousMonth()
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth)->subMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->generateCalendar();
    }

    public function nextMonth()
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth)->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->generateCalendar();
    }

    public function generateCalendar()
    {
        $adminId = $this->adminId;

        $startOfMonth = Carbon::create($this->currentYear, $this->currentMonth)->startOfMonth();
        $endOfMonth = Carbon::create($this->currentYear, $this->currentMonth)->endOfMonth();

        $daysInMonth = $startOfMonth->daysInMonth;
        $calendar = [];

        $attendances = Attendance::where('admin_id', $adminId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->with('dailyBreaks')
            ->get()
            ->keyBy('date');

        $leaves = LeaveRequest::where('admin_id', $adminId)
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                  ->orWhere(function ($q2) use ($startOfMonth, $endOfMonth) {
                      $q2->where('start_date', '<', $startOfMonth)
                          ->where('end_date', '>', $endOfMonth);
                  });
            })
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->get();

        $today = Carbon::now()->toDateString();

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($this->currentYear, $this->currentMonth, $day)->toDateString();

            $attendance = $attendances[$date] ?? null;

            $status = 'Absent';
            if ($date > $today) {
                $status = ''; // Future dates are not considered present or absent
            }
            $clockIn = null;
            $clockOut = null;
            $breakTime = null;

            if ($attendance) {
                $status = 'Present';
                $clockIn = $attendance->clock_in;
                $clockOut = $attendance->clock_out;

                $totalSeconds = $attendance->dailyBreaks->sum(function ($break) {
                    $start = Carbon::parse($break->break_start);
                    $end = $break->break_end ? Carbon::parse($break->break_end) : now();
                    return $end->diffInSeconds($start);
                });

                $breakTime = gmdate('H:i:s', $totalSeconds);
            }

            $leaveType = null;
            $leaveReason = null;
            foreach ($leaves as $leave) {
                if (Carbon::parse($leave->start_date)->lte($date) && Carbon::parse($leave->end_date)->gte($date)) {
                    $status = 'Leave';
                    $leaveType = $leave->leave_type;
                    $leaveReason = $leave->reason;
                    break;
                }
            }

            $calendar[] = [
                'date' => $date,
                'status' => $status,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'break_time' => $breakTime,
                'leave_type' => $leaveType,
                'leave_reason' => $leaveReason,
            ];
        }

        $this->calendarDays = $calendar;
    }

    public function render()
    {
        return view('livewire.attendance-calendar');
    }
}
