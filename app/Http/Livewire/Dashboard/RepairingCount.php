<?php

namespace App\Http\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Admin_model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RepairingCount extends Component
{
    public $startDate;
    public $endDate;
    public $readyToLoad = false;

    public function mount($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate ?: now()->toDateString();
        $this->endDate = $endDate ?: now()->toDateString();
    }

    public function loadRepairingCount()
    {
        $this->readyToLoad = true;
    }

    public function refreshRepairingCount()
    {
        if ($this->readyToLoad) {
            $this->emitSelf('$refresh');
        }
    }

    public function render()
    {
        $repairingCount = collect();

        if ($this->readyToLoad && $this->userCanViewRepairing()) {
            $repairingCount = $this->queryRepairingCount();
        }

        return view('livewire.dashboard.repairing-count', [
            'repairingCount' => $repairingCount,
        ]);
    }

    protected function userCanViewRepairing(): bool
    {
        $user = session('user');

        return $user && $user->hasPermission('dashboard_view_repairing');
    }

    protected function queryRepairingCount()
    {
        [$rangeStart, $rangeEnd] = $this->resolveDateRange();

        return Admin_model::query()
            ->where('role_id', 8)
            ->withCount(['stock_operations' => function ($query) use ($rangeStart, $rangeEnd) {
                $query->select(DB::raw('count(distinct stock_id)'))
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
            }])
            ->orderByDesc('stock_operations_count')
            ->get()
            ->filter(fn($admin) => $admin->stock_operations_count > 0) // Hide technicians without any activity in range
            ->values();
    }

    protected function resolveDateRange(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfDay();
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : now()->endOfDay();

        return [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ];
    }
}
