<div>
    <!-- Month Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <button wire:click="previousMonth" class="btn btn-outline-secondary btn-sm">&laquo;</button>
        <h5 class="mb-0">
            {{ \Carbon\Carbon::create($currentYear, $currentMonth)->format('F Y') }}
        </h5>
        <button wire:click="nextMonth" class="btn btn-outline-secondary btn-sm">&raquo;</button>
    </div>

    <!-- Days of the Week -->
    <div class="d-flex justify-content-between mb-2 fw-bold">
        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dayName)
            <div class="p-2 text-center border rounded position-relative wd-150">{{ $dayName }}</div>
        @endforeach
    </div>

    <!-- Calendar Grid -->
    @php
        $startDate = \Carbon\Carbon::create($currentYear, $currentMonth)->startOfMonth();
        $startDayOfWeek = $startDate->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
        $blankDays = $startDayOfWeek - 1;
        $rows = ceil((count($calendarDays) + $blankDays) / 7);
        $dayIndex = 0;
    @endphp

    @for ($i = 0; $i < $rows; $i++)
        <div class="d-flex justify-content-between mb-2">
            @for ($j = 0; $j < 7; $j++)
                @if ($i === 0 && $j < $blankDays)
                    <div
                        class="p-2 text-center border rounded position-relative wd-150"
                        style="min-height: 100px;"></div> <!-- Empty cell before first day -->
                @elseif ($dayIndex < count($calendarDays))
                    @php $day = $calendarDays[$dayIndex++]; @endphp
                    <div
                        class="p-2 text-center border rounded position-relative wd-150"
                        style="min-height: 100px;
                               display: flex;
                               flex-direction: column;
                               justify-content: center;
                               align-items: center;
                               background-color:
                                   {{ $day['status'] === 'Present' ? '#d4edda' :
                                      ($day['status'] === 'Absent' ? '#f8d7da' :
                                      ($day['status'] === 'Leave' ? '#fff3cd' : '#e2e3e5')) }};
                               color:
                                   {{ $day['status'] === 'Absent' ? '#721c24' :
                                      ($day['status'] === 'Leave' ? '#856404' : '#155724') }};"
                        title="@if($day['clock_in']) Clock In: {{ $day['clock_in'] }} @endif
                               @if($day['clock_out']) | Clock Out: {{ $day['clock_out'] }} @endif
                               @if($day['break_time']) | Break: {{ $day['break_time'] }} @endif
                               @if($day['leave_type']) | Leave: {{ ucfirst($day['leave_type']) }} ({{ $day['leave_reason'] }}) @endif"
                    >
                        <div class="fw-bold">
                            {{ \Carbon\Carbon::parse($day['date'])->format('d') }}
                        </div>
                        <div style="font-size: 0.8rem;">
                            {{ $day['status'] }}
                        </div>
                        @if($day['leave_type'])
                            <div class="mt-1">
                                @if($day['leave_type'] == 'sick')
                                    <span class="badge bg-warning text-dark">Sick</span>
                                @elseif($day['leave_type'] == 'annual')
                                    <span class="badge bg-success text-light">Annual</span>
                                @elseif($day['leave_type'] == 'casual')
                                    <span class="badge bg-info text-dark">Casual</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @else
                    <div
                        class="p-2 text-center border rounded position-relative wd-150"
                        style="min-height: 100px;"></div> <!-- Empty cell before first day -->
                @endif
            @endfor
        </div>
    @endfor
</div>
