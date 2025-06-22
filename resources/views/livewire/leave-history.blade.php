<div>
    <div class="mb-3">
        <h5>Leave Requests</h5>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <select wire:model="filterType" class="form-select">
                <option value="">-- Filter by Leave Type --</option>
                @foreach($leaveTypes as $type)
                    <option>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select wire:model="filterStatus" class="form-select">
                <option value="">-- Filter by Status --</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" wire:model="filterFrom" class="form-control" placeholder="From Date" />
        </div>
        <div class="col-md-3">
            <input type="date" wire:model="filterTo" class="form-control" placeholder="To Date" />
        </div>
    </div>

    @if(session()->has('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    {{-- @if($leaveRequests) --}}
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>No</th>
                <th>Leave Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>Reason</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($leaveRequests as $leave)
                <tr>
                    <td>{{ $loop->iteration + ($leaveRequests->currentPage() - 1) * $leaveRequests->perPage() }}</td>
                    <td>{{ $leave->leave_type ?? '-' }}</td>
                    <td>{{ $leave->start_date }}</td>
                    <td>{{ $leave->end_date }}</td>
                    <td>
                        <span class="badge bg-{{ $leave->status == 'approved' ? 'success' : ($leave->status == 'rejected' ? 'danger' : 'secondary') }}">
                            {{ ucfirst($leave->status) }}
                        </span>
                    </td>
                    <td>{{ $leave->reason }}</td>
                    <td>{{ $leave->created_at->format('d M Y') }}</td>
                    <td>{{ $leave->updated_at->format('d M Y') }}</td>

                    {{-- Actions based on view mode --}}
                    @if($viewMode === 'manager')
                        <td>
                            <button wire:click="approve({{ $leave->id }})" class="btn btn-sm btn-success">Approve</button>
                            <button wire:click="reject({{ $leave->id }})" class="btn btn-sm btn-danger">Reject</button>
                            <button wire:click="edit({{ $leave->id }})" class="btn btn-sm btn-warning">Update</button>
                        </td>
                    @else
                        <td>
                            @if($leave->status === 'pending')
                                <button wire:click="cancel({{ $leave->id }})" class="btn btn-sm btn-danger">Cancel</button>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $viewMode === 'manager' ? 6 : 5 }}" class="text-center">No leave requests found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{ $leaveRequests->links() }}
    {{-- @endif --}}


    <!-- Edit Modal -->
    @if($showEditModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Edit Leave Request</h5>
                        <button type="button" class="btn-close" wire:click="$set('showEditModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="updateLeave">
                            <div class="mb-3">
                                <label>Start Date</label>
                                <input type="date" wire:model="editStartDate" class="form-control" />
                                @error('editStartDate') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="mb-3">
                                <label>End Date</label>
                                <input type="date" wire:model="editEndDate" class="form-control" />
                                @error('editEndDate') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="mb-3">
                                <label>Reason</label>
                                <textarea wire:model="editReason" class="form-control" rows="3"></textarea>
                                @error('editReason') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="mb-3">
                                <label>Status</label>
                                <select wire:model="editStatus" class="form-select">
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                @error('editStatus') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">Update Leave</button>
                            <button type="button" class="btn btn-secondary" wire:click="$set('showEditModal', false)">Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
