@extends('layouts.app')

    @section('styles')
        <style>
            .rows{
                border: 1px solid #016a5949;
            }
            .columns{
                background-color:#016a5949;
                padding-top:5px
            }
            .childs{
                padding-top:5px
            }
            .badge-pass {
                background-color: #28a745;
                color: white;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 0.75rem;
            }
            .badge-fail {
                background-color: #dc3545;
                color: white;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 0.75rem;
            }
            .test-results {
                max-width: 300px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .bulk-actions {
                position: sticky;
                top: 0;
                background: white;
                z-index: 100;
                padding: 15px;
                border-bottom: 2px solid #dee2e6;
                margin-bottom: 20px;
            }
            .filter-section {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            .filter-input {
                font-size: 0.875rem;
            }
        </style>
    @endsection
<br>
    @section('content')



        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Testing</span> --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Testing</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Testing Data</h4></center>
            </div>
        </div>
        <br>

        @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
            <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
            <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
        </div>
        <br>
            @php
                session()->forget('success');
            @endphp
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
                <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            <script>
                alert("{{session('error')}}");
            </script>
            <br>
            @php
                session()->forget('error');
            @endphp
        @endif
        <div class="card">
            <div class="card-header pb-0">
                <div class='d-flex justify-content-between align-items-center'>
                    <div class="d-flex gap-2">
                        <form method='get' action='{{url("request_drfones")}}' class="d-flex gap-2">
                            <input type='text' name='imei' class="form-control" placeholder='Enter IMEI' style="width: 200px;">
                            <button type='submit' class="btn btn-primary">Search</button>
                        </form>

                        <form method='post' action='{{url("testing/push_all")}}' class="d-flex gap-2">
                            @csrf
                            <button type='submit' class="btn btn-warning" onclick="return confirm('Push all testing data to appropriate systems?')">
                                <i class="fe fe-upload me-1"></i>Push All
                            </button>
                        </form>
                    </div>

                    <form method='post' action='{{url("testing/upload_excel")}}' enctype='multipart/form-data' class="d-flex gap-2">
                        @csrf
                        <input type='file' name='sheet' class="form-control" style="width: 250px;">
                        <button type='submit' class="btn btn-success">Upload Excel</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                @php
                    $serials = [];
                    $validRequests = [];
                @endphp

                {{-- Filter Section --}}
                <div class="filter-section">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Model Name</label>
                            <input type="text" id="filterModel" class="form-control form-control-sm filter-input" placeholder="Search model...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Serial Number</label>
                            <input type="text" id="filterSerial" class="form-control form-control-sm filter-input" placeholder="Search serial...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">IMEI</label>
                            <input type="text" id="filterImei" class="form-control form-control-sm filter-input" placeholder="Search IMEI...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Grade</label>
                            <select id="filterGrade" class="form-select form-select-sm filter-input">
                                <option value="">All Grades</option>
                                <option value="RMA">RMA</option>
                                <option value="REPAIR">REPAIR</option>
                                <option value="PASS">PASS</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Tester Name</label>
                            <input type="text" id="filterTester" class="form-control form-control-sm filter-input" placeholder="Search tester...">
                        </div>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Batch ID</label>
                            <input type="text" id="filterBatch" class="form-control form-control-sm filter-input" placeholder="Search batch...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Battery Health</label>
                            <select id="filterBattery" class="form-select form-select-sm filter-input">
                                <option value="">All</option>
                                <option value="good">Good (≥85%)</option>
                                <option value="fair">Fair (70-84%)</option>
                                <option value="poor">Poor (<70%)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Test Status</label>
                            <select id="filterStatus" class="form-select form-select-sm filter-input">
                                <option value="">All</option>
                                <option value="pass">All Pass</option>
                                <option value="fail">Has Failures</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Date From</label>
                            <input type="date" id="filterDateFrom" class="form-control form-control-sm filter-input">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Date To</label>
                            <input type="date" id="filterDateTo" class="form-control form-control-sm filter-input">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary btn-sm w-100" onclick="clearFilters()">
                                <i class="fe fe-x me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Bulk Actions Bar --}}
                <div class="bulk-actions">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <input type="checkbox" id="selectAll" class="form-check-input me-2">
                            <label for="selectAll" class="form-check-label me-3">Select All</label>
                            <span class="badge bg-info" id="selectedCount">0 selected</span>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-warning" onclick="bulkAction('push_selected')">
                                <i class="fe fe-upload me-1"></i>Push Selected
                            </button>
                            <button type="button" class="btn btn-primary" onclick="bulkAction('send_to_eg')">
                                <i class="fe fe-send me-1"></i>Send to EG
                            </button>
                            <button type="button" class="btn btn-info" onclick="bulkAction('send_to_yk')">
                                <i class="fe fe-send me-1"></i>Send to YK
                            </button>
                            <button type="button" class="btn btn-danger" onclick="bulkAction('delete')">
                                <i class="fe fe-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th width="30"><input type="checkbox" id="selectAllTable" class="form-check-input"></th>
                                <th>Model</th>
                                <th>Serial</th>
                                <th>IMEI</th>
                                <th>Color/Memory</th>
                                <th>Battery Health</th>
                                <th>Grade</th>
                                <th>Status</th>
                                <th>Tester</th>
                                <th>Batch ID</th>
                                <th>Date/Time</th>
                                <th></th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $request)
                                @php
                                    $result = json_decode($request->request);
                                    $datas = $result;
                                    if(is_string($result)){
                                        continue;
                                    }

                                    $isDuplicate = in_array($datas->Serial ?? '', $serials);
                                    if($isDuplicate){
                                        continue;
                                    }

                                    if(!empty($datas->Serial)){
                                        $serials[] = $datas->Serial;
                                    }

                                    // If IMEI is missing, try to find it from other records with the same serial
                                    $imeiUpdated = false;
                                    if((empty($datas->Imei) || empty($datas->Imei2)) && !empty($datas->Serial)){
                                        foreach($requests as $otherRequest){
                                            $otherResult = json_decode($otherRequest->request);
                                            if(is_string($otherResult)) continue;

                                            if(($otherResult->Serial ?? '') === $datas->Serial){
                                                if(empty($datas->Imei) && !empty($otherResult->Imei)){
                                                    $datas->Imei = $otherResult->Imei;
                                                    $imeiUpdated = true;
                                                }
                                                if(empty($datas->Imei2) && !empty($otherResult->Imei2)){
                                                    $datas->Imei2 = $otherResult->Imei2;
                                                    $imeiUpdated = true;
                                                }
                                                // Break if both IMEIs are found
                                                if(!empty($datas->Imei) && !empty($datas->Imei2)){
                                                    break;
                                                }
                                            }
                                        }

                                        // Update the database if IMEI was found
                                        if($imeiUpdated){
                                            $request->request = json_encode($datas);
                                            $request->save();
                                        }
                                    }

                                    $validRequests[] = $request;
                                @endphp
                                <tr class="@if($isDuplicate) table-warning @endif" data-request-id="{{$request->id}}">
                                    <td>
                                        <input type="checkbox" class="form-check-input request-checkbox" value="{{$request->id}}">
                                    </td>
                                    <td>
                                        <strong>{{$datas->ModelName ?? 'N/A'}}</strong><br>
                                        <small class="text-muted">{{$datas->ModelNo ?? 'N/A'}}</small>
                                    </td>
                                    <td>
                                        <code>{{$datas->Serial ?? 'N/A'}}</code>
                                        @if($isDuplicate)
                                            <br><span class="badge bg-warning text-dark">Duplicate</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $imeiFound = !empty($datas->Imei) || !empty($datas->Imei2);
                                            $imei1 = $datas->Imei ?? 'N/A';
                                            $imei2 = $datas->Imei2 ?? 'N/A';
                                        @endphp
                                        @if($imeiFound)
                                            <small>IMEI1: {{$imei1}}</small><br>
                                            <small>IMEI2: {{$imei2}}</small>
                                        @else
                                            <span class="badge bg-danger">Missing IMEI</span>
                                            <button type="button" class="btn btn-sm btn-warning mt-1" data-bs-toggle="modal" data-bs-target="#addImeiModal{{$request->id}}">
                                                Add IMEI
                                            </button>
                                        @endif
                                    </td>
                                    <td>
                                        {{$datas->Color ?? 'N/A'}}<br>
                                        <strong>{{$datas->Memory ?? 'N/A'}}</strong>
                                    </td>
                                    <td>
                                        @if(!empty($datas->Batteryhealth))
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar @if($datas->Batteryhealth >= 85) bg-success @elseif($datas->Batteryhealth >= 70) bg-warning @else bg-danger @endif"
                                                     style="width: {{$datas->Batteryhealth}}%">
                                                    {{$datas->Batteryhealth}}%
                                                </div>
                                            </div>
                                            <small class="text-muted">Cycles: {{$datas->Cyclecount ?? 'N/A'}}</small>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge
                                            @if($datas->Grade == 'RMA') bg-danger
                                            @elseif($datas->Grade == 'REPAIR') bg-warning
                                            @else bg-success
                                            @endif">
                                            {{$datas->Grade ?? 'N/A'}}
                                        </span>
                                    </td>
                                    <td>
                                        @if(!empty($datas->Fail))
                                            <span class="badge badge-fail" title="{{$datas->Fail}}">
                                                {{ count(explode(',', $datas->Fail)) }} Failed
                                            </span>
                                        @else
                                            <span class="badge badge-pass">All Pass</span>
                                        @endif
                                        @if(!empty($datas->Comments))
                                            <br><small class="text-muted">{{Str::limit($datas->Comments, 30)}}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{$datas->TesterName ?? 'N/A'}}<br>
                                        <small class="text-muted">{{$datas->PCName ?? 'N/A'}}</small>
                                    </td>
                                    <td>
                                        <span class="badge
                                            @if(str_contains(strtolower($datas->BatchID ?? ''), 'eg')) bg-primary
                                            @elseif(str_contains(strtolower($datas->BatchID ?? ''), 'yk')) bg-info
                                            @else bg-secondary
                                            @endif">
                                            {{$datas->BatchID ?? 'N/A'}}
                                        </span>
                                    </td>
                                    <td>
                                        @if(!empty($datas->Time))
                                            @php
                                                $timestamp = \Carbon\Carbon::parse($datas->Time);
                                            @endphp
                                            <small><strong>{{$timestamp->format('M d, Y')}}</strong></small><br>
                                            <small class="text-muted">{{$timestamp->format('h:i A')}}</small>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group-vertical btn-group-sm">
                                            <a href='{{url("testing/push_one")."/".$request->id}}' class='btn btn-warning btn-sm' onclick="return confirm('Push this request?')">
                                                <i class="fe fe-upload"></i> Push
                                            </a>
                                            <a href='{{url("testing/send_to_eg")."/".$request->id}}' class='btn btn-primary btn-sm'>
                                                <i class="fe fe-send"></i> EG
                                            </a>
                                            <a href='{{url("testing/send_to_yk")."/".$request->id}}' class='btn btn-info btn-sm'>
                                                <i class="fe fe-send"></i> YK
                                            </a>
                                            <a href='{{url("testing/delete_request")."/".$request->id}}' class='btn btn-danger btn-sm' onclick="return confirm('Delete this request?')">
                                                <i class="fe fe-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Modal for Adding IMEI --}}
                                @if(empty($datas->Imei) && empty($datas->Imei2) && !empty($datas->Serial))
                                <div class="modal fade" id="addImeiModal{{$request->id}}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method='post' action='{{url("testing/add_imei")."/".$request->id}}'>
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Add IMEI for Serial: {{$datas->Serial}}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type='hidden' name='serial' value='{{$datas->Serial}}'>
                                                    <div class="mb-3">
                                                        <label class="form-label">IMEI Number</label>
                                                        <input type='text' name='imei' class="form-control" placeholder='Enter IMEI' required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type='submit' class="btn btn-primary">Add IMEI</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>



    @endsection

    @section('scripts')

		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

        <script>
            // Select All functionality
            document.getElementById('selectAll').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.request-checkbox');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
                updateSelectedCount();
            });

            document.getElementById('selectAllTable').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.request-checkbox');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
                document.getElementById('selectAll').checked = this.checked;
                updateSelectedCount();
            });

            // Update count when individual checkboxes change
            document.querySelectorAll('.request-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            function updateSelectedCount() {
                const selected = document.querySelectorAll('.request-checkbox:checked').length;
                const total = document.querySelectorAll('.request-checkbox').length;
                document.getElementById('selectedCount').textContent = selected + ' selected';

                // Update select all checkbox state
                const selectAllCheckbox = document.getElementById('selectAll');
                const selectAllTableCheckbox = document.getElementById('selectAllTable');

            // ==================== FILTER FUNCTIONALITY ====================

            function applyFilters() {
                const filters = {
                    model: document.getElementById('filterModel').value.toLowerCase(),
                    serial: document.getElementById('filterSerial').value.toLowerCase(),
                    imei: document.getElementById('filterImei').value.toLowerCase(),
                    grade: document.getElementById('filterGrade').value.toLowerCase(),
                    tester: document.getElementById('filterTester').value.toLowerCase(),
                    batch: document.getElementById('filterBatch').value.toLowerCase(),
                    battery: document.getElementById('filterBattery').value,
                    status: document.getElementById('filterStatus').value,
                    dateFrom: document.getElementById('filterDateFrom').value,
                    dateTo: document.getElementById('filterDateTo').value
                };

                const rows = document.querySelectorAll('tbody tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length === 0) return;

                    const rowData = {
                        model: cells[1]?.textContent.toLowerCase() || '',
                        serial: cells[2]?.textContent.toLowerCase() || '',
                        imei: cells[3]?.textContent.toLowerCase() || '',
                        grade: cells[6]?.textContent.toLowerCase().trim() || '',
                        tester: cells[8]?.textContent.toLowerCase() || '',
                        batch: cells[9]?.textContent.toLowerCase() || '',
                        batteryHealth: cells[5]?.querySelector('.progress-bar')?.textContent.trim() || '',
                        hasFail: cells[7]?.textContent.includes('Failed') || false,
                        date: cells[10]?.textContent.trim() || ''
                    };

                    let show = true;

                    // Apply text filters
                    if (filters.model && !rowData.model.includes(filters.model)) show = false;
                    if (filters.serial && !rowData.serial.includes(filters.serial)) show = false;
                    if (filters.imei && !rowData.imei.includes(filters.imei)) show = false;
                    if (filters.grade && !rowData.grade.includes(filters.grade)) show = false;
                    if (filters.tester && !rowData.tester.includes(filters.tester)) show = false;
                    if (filters.batch && !rowData.batch.includes(filters.batch)) show = false;

                    // Battery health filter
                    if (filters.battery) {
                        const batteryValue = parseInt(rowData.batteryHealth.replace('%', ''));
                        if (!isNaN(batteryValue)) {
                            if (filters.battery === 'good' && batteryValue < 85) show = false;
                            if (filters.battery === 'fair' && (batteryValue < 70 || batteryValue >= 85)) show = false;
                            if (filters.battery === 'poor' && batteryValue >= 70) show = false;
                        }
                    }

                    // Test status filter
                    if (filters.status) {
                        if (filters.status === 'pass' && rowData.hasFail) show = false;
                        if (filters.status === 'fail' && !rowData.hasFail) show = false;
                    }

                    // Date range filter
                    if (filters.dateFrom || filters.dateTo) {
                        const dateMatch = rowData.date.match(/(\w{3}\s\d{1,2},\s\d{4})/);
                        if (dateMatch) {
                            const rowDate = new Date(dateMatch[0]);
                            if (filters.dateFrom && rowDate < new Date(filters.dateFrom)) show = false;
                            if (filters.dateTo && rowDate > new Date(filters.dateTo)) show = false;
                        }
                    }

                    row.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });

                // Update selected count after filtering
                updateSelectedCount();

                // Show filter results count
                const totalRows = rows.length;
                console.log(`Showing ${visibleCount} of ${totalRows} records`);
            }

            function clearFilters() {
                document.getElementById('filterModel').value = '';
                document.getElementById('filterSerial').value = '';
                document.getElementById('filterImei').value = '';
                document.getElementById('filterGrade').value = '';
                document.getElementById('filterTester').value = '';
                document.getElementById('filterBatch').value = '';
                document.getElementById('filterBattery').value = '';
                document.getElementById('filterStatus').value = '';
                document.getElementById('filterDateFrom').value = '';
                document.getElementById('filterDateTo').value = '';
                applyFilters();
            }

            // Add event listeners to all filter inputs
            document.querySelectorAll('.filter-input').forEach(input => {
                input.addEventListener('input', applyFilters);
                input.addEventListener('change', applyFilters);
            });
                if (selected === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllTableCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                    selectAllTableCheckbox.indeterminate = false;
                } else if (selected === total) {
                    selectAllCheckbox.checked = true;
                    selectAllTableCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                    selectAllTableCheckbox.indeterminate = false;
                } else {
                    selectAllCheckbox.indeterminate = true;
                    selectAllTableCheckbox.indeterminate = true;
                }
            }

            function bulkAction(action) {
                const selected = Array.from(document.querySelectorAll('.request-checkbox:checked')).map(cb => cb.value);

                if (selected.length === 0) {
                    alert('Please select at least one request');
                    return;
                }

                let confirmMessage = '';
                let url = '';

                switch(action) {
                    case 'push_selected':
                        confirmMessage = `Push ${selected.length} request(s) to appropriate systems?`;
                        url = '{{url("testing/bulk_push")}}';
                        break;
                    case 'send_to_eg':
                        confirmMessage = `Send ${selected.length} request(s) to EG?`;
                        url = '{{url("testing/bulk_send_to_eg")}}';
                        break;
                    case 'send_to_yk':
                        confirmMessage = `Send ${selected.length} request(s) to YK?`;
                        url = '{{url("testing/bulk_send_to_yk")}}';
                        break;
                    case 'delete':
                        confirmMessage = `Delete ${selected.length} request(s)? This action cannot be undone.`;
                        url = '{{url("testing/bulk_delete")}}';
                        break;
                }

                if (confirm(confirmMessage)) {
                    // Create form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url;

                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = '_token';
                    csrfToken.value = '{{ csrf_token() }}';
                    form.appendChild(csrfToken);

                    const idsInput = document.createElement('input');
                    idsInput.type = 'hidden';
                    idsInput.name = 'request_ids';
                    idsInput.value = JSON.stringify(selected);
                    form.appendChild(idsInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            }

            // Initialize count on page load
            updateSelectedCount();
        </script>

    @endsection
