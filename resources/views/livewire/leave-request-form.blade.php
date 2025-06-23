<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Request Leave</h5>
    </div>

    <div class="card-body">
        @if (session()->has('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form wire:submit.prevent="submit">
            <div class="mb-3">
                <label for="type" class="form-label">Leave Type</label>
                <select wire:model="type" id="type" class="form-control">
                    <option value="">-- Select Leave Type --</option>
                    <option value="annual">Annual</option>
                    <option value="sick">Sick</option>
                    <option value="half-day">Half Day</option>
                    <option value="unpaid">Unpaid</option>
                </select>
                @error('type') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="mb-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" wire:model="start_date" id="start_date" class="form-control">
                @error('start_date') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="mb-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" wire:model="end_date" id="end_date" class="form-control">
                @error('end_date') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="mb-3">
                <label for="reason" class="form-label">Reason</label>
                <textarea wire:model="reason" id="reason" class="form-control" rows="3" placeholder="Optional..."></textarea>
                @error('reason') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-success">Submit Leave Request</button>
        </form>
    </div>
</div>
