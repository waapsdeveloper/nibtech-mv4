<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class LeaveHistory extends Component
{
    use WithPagination;

    public $viewMode = 'employee'; // 'employee' or 'manager'
    public $adminId;

    // public $leaveRequests;

    // Filters
    public $filterType = '';
    public $filterStatus = '';
    public $filterFrom = '';
    public $filterTo = '';

    // For editing modal
    public $showEditModal = false;
    public $editingLeaveId = null;
    public $editStartDate;
    public $editEndDate;
    public $editReason;
    public $editStatus;
    public $leaveTypes = ['annual', 'sick', 'casual', 'maternity', 'paternity'];

    protected $rules = [
        'editStartDate' => 'required|date',
        'editEndDate' => 'required|date|after_or_equal:editStartDate',
        'editReason' => 'nullable|string|max:1000',
        'editStatus' => 'required|in:pending,approved,rejected,cancelled',
    ];

    protected $listeners = ['refreshLeaves' => 'render'];

    public function mount($viewMode = 'employee', $adminId = null)
    {
        $this->viewMode = $viewMode;
        $this->adminId = $adminId ?? session('user_id');
    }

    public function updating($property)
    {
        if (in_array($property, ['filterType', 'filterStatus', 'filterFrom', 'filterTo'])) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $adminId = $this->adminId;

        $query = LeaveRequest::with('admin');
        if ($this->viewMode === 'employee' || !request('all')) {
            $query->where('admin_id', $adminId);
        }

        if ($this->filterType) {
            $query->where('leave_type', $this->filterType);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterFrom) {
            $query->whereDate('start_date', '>=', $this->filterFrom);
        }

        if ($this->filterTo) {
            $query->whereDate('end_date', '<=', $this->filterTo);
        }

        $leaveRequests = $query->orderBy('created_at', 'desc')->paginate(10);

        // $this->leaveRequests = $leaveRequests;

        return view('livewire.leave-history', [
            'leaveRequests' => $leaveRequests
        ]);
    }

    public function edit($leaveId)
    {
        $leave = LeaveRequest::findOrFail($leaveId);

        $this->editingLeaveId = $leave->id;
        $this->editStartDate = $leave->start_date;
        $this->editEndDate = $leave->end_date;
        $this->editReason = $leave->reason;
        $this->editStatus = $leave->status;

        $this->showEditModal = true;
    }

    public function updateLeave()
    {
        $this->validate();

        $leave = LeaveRequest::findOrFail($this->editingLeaveId);

        $leave->update([
            'start_date' => $this->editStartDate,
            'end_date' => $this->editEndDate,
            'reason' => $this->editReason,
            'status' => $this->editStatus,
        ]);

        $this->showEditModal = false;
        $this->editingLeaveId = null;

        $this->emit('refreshLeaves'); // refresh list
        session()->flash('message', 'Leave updated successfully.');
    }

    public function approve($id)
    {
        LeaveRequest::where('id', $id)->update(['status' => 'approved']);
        $this->emit('refreshLeaves');
    }

    public function reject($id)
    {
        LeaveRequest::where('id', $id)->update(['status' => 'rejected']);
        $this->emit('refreshLeaves');
    }

    public function cancel($id)
    {
        LeaveRequest::where('id', $id)->update(['status' => 'cancelled']);
        $this->emit('refreshLeaves');
    }
}
