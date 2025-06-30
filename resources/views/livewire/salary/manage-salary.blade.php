<div>
    <div class="mb-3">
        <label>Select Month</label>
        <input type="month" wire:model="month" class="form-control" />
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Base Salary</th>
                <th>Present Days</th>
                <th>Advances</th>
                <th>Net Salary</th>
            </tr>
        </thead>
        <tbody>
            @foreach($salaries as $salary)
                <tr>
                    <td>{{ $salary['admin'] }}</td>
                    <td>£{{ number_format($salary['base'], 2) }}</td>
                    <td>{{ $salary['present'] }}</td>
                    <td>£{{ number_format($salary['advances'], 2) }}</td>
                    <td><strong>£{{ number_format($salary['net'], 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
