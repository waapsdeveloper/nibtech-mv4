<div>
    <h3 class="mb-4">Payroll Summary</h3>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card p-3">
                <h5>Total Worked Hours</h5>
                <p class="fs-4">{{ $totalWorkedHours }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <h5>Estimated Pay</h5>
                <p class="fs-4">${{ number_format($estimatedPay, 2) }}</p>
            </div>
        </div>
    </div>

    {{-- <h4>Attendance Records</h4>
    @livewire('components.data-table', [
        'data' => $attendance,
        'columns' => [
            ['label' => 'Date', 'field' => 'date', 'format' => fn($v) => \Carbon\Carbon::parse($v)->format('d M Y')],
            ['label' => 'Status', 'field' => 'status', 'format' => fn($v) => ucfirst($v)],
            ['label' => 'Check-in Time', 'field' => 'check_in_time', 'format' => fn($v) => \Carbon\Carbon::parse($v)->format('H:i')],
            ['label' => 'Check-out Time', 'field' => 'check_out_time', 'format' => fn($v) => \Carbon\Carbon::parse($v)->format('H:i')],
        ]
    ]) --}}

    <h4 class="mt-4">Leave Requests</h4>
    <livewire:leave-request-form />
    <livewire:leave-history />

    <h4 class="mt-4">Calendar View</h4>
    <livewire:attendance-calendar />

    {{-- Uncomment if you want to display calendar days in a different format --}}
    {{-- <div class="d-flex flex-wrap">
        @foreach($calendarDays as $day)
            <div class="border m-1 p-2 text-center" style="width: 80px; font-size: 0.8rem;
                background-color:
                    {{ $day['status'] === 'Present' ? '#d4edda' : ($day['status'] === 'Absent' ? '#f8d7da' : '#fff3cd') }};
                color:
                    {{ $day['status'] === 'Absent' ? '#721c24' : '#155724' }};
                border-radius: 4px;">
                <div><strong>{{ \Carbon\Carbon::parse($day['date'])->format('d') }}</strong></div>
                <div>{{ $day['status'] }}</div>
            </div>
        @endforeach
    </div> --}}
</div>
