<?php
// app/Http/Livewire/ClockButton.php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Attendance;
use App\Models\DailyBreak;
use Carbon\Carbon;

class ClockButton extends Component
{
    public $class;
    public $hasClockedIn = false;
    public $hasClockedOut = false;
    public $onBreak = false;
    public $totalBreakTime = '00:00:00';

    public function mount($class)
    {
        $this->class = $class;
        $this->refreshStatus();
    }

    public function refreshStatus()
    {
        $today = now()->toDateString();
        $adminId = session('user_id');

        $attendance = Attendance::where('admin_id', $adminId)->where('date', $today)->first();

        $this->hasClockedIn = $attendance && $attendance->clock_in !== null;
        $this->hasClockedOut = $attendance && $attendance->clock_out !== null;

        if ($attendance) {
            $this->onBreak = $attendance->dailyBreaks()->whereNull('break_end')->exists();

            $totalSeconds = $attendance->dailyBreaks->sum(function ($break) {
                $start = Carbon::parse($break->break_start);
                $end = $break->break_end ? Carbon::parse($break->break_end) : now();
                return $end->diffInSeconds($start);
            });

            $this->totalBreakTime = gmdate('H:i:s', $totalSeconds);
        } else {
            $this->onBreak = false;
            $this->totalBreakTime = '00:00:00';
        }
    }

    public function clockIn()
    {
        $addendance = Attendance::where('admin_id', session('user_id'))
            ->where('date', now()->toDateString())
            ->first();
        if ($addendance && $addendance->clock_in) {
            $this->endBreak();
        }else {

            Attendance::firstOrCreate(
                ['admin_id' => session('user_id'), 'date' => now()->toDateString()],
                ['clock_in' => now()->format('H:i:s')]
            );
        }
        $this->refreshStatus();
    }

    public function clockOut()
    {
        $attendance = Attendance::where('admin_id', session('user_id'))
            ->where('date', now()->toDateString())
            ->first();

        if ($attendance && !$attendance->clock_out) {
            $attendance->update(['clock_out' => now()->format('H:i:s')]);
        }

        // $this->refreshStatus();
        // ?logout

        return redirect()->to('logout');
    }

    public function startBreak()
    {
        $attendance = Attendance::where('admin_id', session('user_id'))->latest()->first();

        if ($attendance) {
            $attendance->dailyBreaks()->create(['break_start' => now()]);
        }

        // $this->refreshStatus();
        // Load Logout class to handle logout
        return redirect()->to('logout');

    }

    public function endBreak()
    {
        $attendance = Attendance::where('admin_id', session('user_id'))->latest()->first();

        if ($attendance) {
            $break = $attendance->dailyBreaks()->whereNull('break_end')->first();
            if ($break) {
                $break->update(['break_end' => now()]);
            }
        }

        $this->refreshStatus();
    }

    public function render()
    {
        return view('livewire.clock-button');
    }
}
