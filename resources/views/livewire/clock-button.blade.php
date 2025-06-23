<div class="{{ $class ?? 'text-center' }}">
    @if(!$hasClockedIn)
        <button wire:click="clockIn" class="btn btn-success">Clock In</button>
    @elseif(!$hasClockedOut)
        @if(!$onBreak)
            <button wire:click="startBreak" class="btn btn-info"
                title="@if ($totalBreakTime > '00:00:00')
                    You have already taken a break today.
                    Total Break Time: {{ $totalBreakTime }}
                @else
                    Click to start your break.
                @endif"
            >Start Break</button>
            &nbsp;
            <button wire:click="clockOut" class="btn btn-warning">Clock Out</button>
        @else
            <button wire:click="endBreak" class="btn btn-success"
                title="@if ($totalBreakTime > '00:00:00')
                    You have already taken a break today.
                    Total Break Time: {{ $totalBreakTime }}
                @else
                    Click to end your break.
                @endif
            ">End Break</button>
        @endif

    @else
        <div class="alert alert-info mt-3">
            Attendance completed for today.
        </div>
    @endif
</div>
