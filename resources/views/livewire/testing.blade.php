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
                    <form method='get' action='{{url("request_drfones")}}' class="d-flex gap-2">
                        <input type='text' name='imei' class="form-control" placeholder='Enter IMEI' style="width: 200px;">
                        <button type='submit' class="btn btn-primary">Search</button>
                    </form>

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

                {{-- Bulk Actions Bar --}}
                <div class="bulk-actions">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <input type="checkbox" id="selectAll" class="form-check-input me-2">
                            <label for="selectAll" class="form-check-label me-3">Select All</label>
                            <span class="badge bg-info" id="selectedCount">0 selected</span>
                        </div>
                        <div class="btn-group">
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
                                <th>Actions</th>
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
                                        @if(!empty($datas->Imei) || !empty($datas->Imei2))
                                            <small>IMEI1: {{$datas->Imei ?? 'N/A'}}</small><br>
                                            <small>IMEI2: {{$datas->Imei2 ?? 'N/A'}}</small>
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
                                        <div class="btn-group-vertical btn-group-sm">
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

        <!-- Pusher Scripts -->
        <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

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

            // ==================== PUSHER REAL-TIME UPDATES ====================
            
            // Enable pusher logging - don't include this in production
            Pusher.logToConsole = true;

            var pusher = new Pusher('{{env("PUSHER_APP_KEY")}}', {
                cluster: '{{env("PUSHER_APP_CLUSTER")}}',
                encrypted: true
            });

            // Subscribe to testing channel
            var channel = pusher.subscribe('testing-channel');
            
            // Listen for new test request event
            channel.bind('new-test-request', function(data) {
                console.log('New test request received:', data);
                
                // Insert new row dynamically instead of reloading
                insertNewRow(data);
                
                // Show notification
                showNotification('New Test Request', `New ${data.ModelName || 'device'} added - Serial: ${data.Serial}`, 'success');
            });

            // Listen for test update event
            channel.bind('test-updated', function(data) {
                console.log('Test updated:', data);
                
                // Update the specific row in the table
                updateTableRow(data.request_id, data);
                
                showNotification('Test Updated', `Request #${data.request_id} has been updated`, 'info');
            });

            // Listen for test deleted event
            channel.bind('test-deleted', function(data) {
                console.log('Test deleted:', data);
                
                // Remove row from table
                const row = document.querySelector(`tr[data-request-id="${data.request_id}"]`);
                if (row) {
                    row.style.transition = 'all 0.3s';
                    row.style.backgroundColor = '#ff000020';
                    setTimeout(() => {
                        row.remove();
                        updateSelectedCount();
                    }, 300);
                }
                
                showNotification('Test Deleted', `Request #${data.request_id} has been deleted`, 'warning');
            });

            // Listen for bulk action events
            channel.bind('bulk-action-completed', function(data) {
                console.log('Bulk action completed:', data);
                
                showNotification('Bulk Action Completed', `${data.action} completed for ${data.count} items`, 'success');
                
                setTimeout(() => {
                    location.reload();
                }, 2000);
            });

            // Function to update a table row
            function updateTableRow(requestId, data) {
                const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                if (row) {
                    // Highlight the updated row
                    row.style.transition = 'all 0.3s';
                    row.style.backgroundColor = '#28a74520';
                    
                    setTimeout(() => {
                        row.style.backgroundColor = '';
                    }, 2000);
                }
            }

            // Function to show notification
            function showNotification(title, message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'} alert-dismissible fade show position-fixed`;
                notification.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
                notification.innerHTML = `
                    <strong>${title}</strong><br>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(notification);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    notification.remove();
                }, 5000);
                
                // Play notification sound (optional)
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjKJ0fPTgjMGHm7A7+OZUQ8LR6Hf8rtsIgU7k9j0y3kpBSR3yO/ekD8KFF6z6OupVRQKRZ7f8r1tIgU6kdXyzn0uBSF1xe/glEILFWK56+ylWRQLSpzi87TiJQUyhM/z1IIzBRxqvu7inFIOC0qg4PK8bSIEOI/T88l+MAUkd8nu4JJBChoLYrvo7KVaFQpIm93xwHInBTGDzvLTgjMGHm3A7eSaUQ8KRaDe8bxuIwU5kdTzyn0uBSF0xO/glEIMFWK56+ylWRUKSJvd8cJyKAUxg87y04IyBh5twO3kmVIPCkWg3vK8byMFOZDU88p9LgUhdc/v4JRCDBB3xu7kl1UOC0me3vG/cSYEMYPO8tODMwUebcDt5JlSDwtGod7yvG8jBTiP0/PJfi8FI3bF7uGUQg0UYbrr7KZaFApHnN7xwHImBTGDzvLTgjIFHm2/7uSaUg8KRaDe8rxvIwU4j9LzyHwuBSJ0w+7glEIOFGG66+ymWhUKR5vd8cByJwUygM7y04IzBR5swO3kmVIPCkWf3vG8byMFOI/T88h+LgUidMPu4ZVCDRRhuezsp1oVCkea3fHAcicFMn/O8tOCMgYebL/t5JpRDwtGn97xvG4jBTiP0vLIfi8FI3TD7uGVQg4Ud8bt5JdVDgtJnN3xwHEnBDJ/zvLSgjMGHW2/7eSaUg4KRZ7d8bxuIwU3j9LyyH4vBSJ0w+7hlkIOFHbG7eSXVQ4LSJvd8L9xJgUyf87y0oIzBh1tv+3kmFIPCkSe3fG8biMFOI7S8sd+LwUidMPu4ZZCDhR2xu3kl1UOC0ma3fC/cScFMn/O8tKCMwYdbb/t5JhSEApEnt3xvG4jBTeO0vLHfi8FInTC7+GWQg4UdsbtuZdVDgtJmt3wv3EmBTJ/zvLSgjMGHWy/7eSYUg8LRJ7d8LxuIwU3jtLyx34vBSJ0w+7hlkIQEHfE7bmXVA4LSZrd8L5xJwUyf87y0oIzBh1svuzkmVIPCkSe3fC8biQFN47S8sZ+LwUidbru4ZVCDxR3xezrlFUOCkma3fC+cScGMoDO8dKCMwcdab/t5JlSEApDndzwu28kBTaO0vHGfi8GInW67+GVQg8UdsXt65JWDQtJmt3wvnEnBTJ/zvHSgjMGHWq/7OSaUg8LRJzd8LtvJAQ2jtHxx34wBSJ1uu/hlUIQFHbF7euSVw0LSZrd8L5wJwYygM7x0YIzBh1qv+vkmVIQCkOc3fG7cCQGNo3R8cZ+MAUidLrt4ZZCDxR1xe3qklcOC0ia3fC+cCcGMoDO8tGCMwYdarzt5JlSEApDm93xu3EkBjaMz/HGfjAFInW67uGWQg4TdsXt6pJYDgtJmt3wvnAnBjKAzvHRgjMGHWq87eSZUxAKQ5vc8bpxJAU2jM/xxn4vBSN1uu7hmEIOE3XF7eqSWA4LSJrd8L1wJwYxgM7x0YIzBx1pu+vkmVMQC0Kb2/G6cSQFNozP8cV9LwUidbru4ZhCDhN1xe3qklgOC0ia3PC9cCcGMX/O8tKCNAYdabvr45hSEAtCm9rxu3EkBTWLz/HFfS8FInW67+CWQg4TdcXu6pJYDgtImg==');
                    audio.play().catch(e => console.log('Audio play failed:', e));
                } catch(e) {
                    console.log('Audio not supported');
                }
            }

            // Connection status monitoring
            pusher.connection.bind('connected', function() {
                console.log('✅ Connected to Pusher');
                showConnectionStatus('connected');
            });

            pusher.connection.bind('disconnected', function() {
                console.log('❌ Disconnected from Pusher');
                showConnectionStatus('disconnected');
            });

            pusher.connection.bind('error', function(err) {
                console.error('Pusher connection error:', err);
                showConnectionStatus('error');
            });

            function showConnectionStatus(status) {
                // Remove existing status indicator
                const existingStatus = document.getElementById('pusher-status');
                if (existingStatus) existingStatus.remove();

                // Create status indicator
                const statusDiv = document.createElement('div');
                statusDiv.id = 'pusher-status';
                statusDiv.style.cssText = 'position: fixed; top: 10px; right: 10px; z-index: 9999; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold;';
                
                if (status === 'connected') {
                    statusDiv.style.backgroundColor = '#28a745';
                    statusDiv.style.color = 'white';
                    statusDiv.innerHTML = '🟢 Live';
                    // Hide after 3 seconds
                    setTimeout(() => statusDiv.remove(), 3000);
                } else if (status === 'disconnected') {
                    statusDiv.style.backgroundColor = '#dc3545';
                    statusDiv.style.color = 'white';
                    statusDiv.innerHTML = '🔴 Disconnected';
                } else {
                    statusDiv.style.backgroundColor = '#ffc107';
                    statusDiv.style.color = 'black';
                    statusDiv.innerHTML = '⚠️ Connection Error';
                }
                
                document.body.appendChild(statusDiv);
            }

            // Function to dynamically insert a new row
            function insertNewRow(data) {
                const tbody = document.querySelector('table tbody');
                if (!tbody) return;

                // Check for duplicates
                const existingRow = document.querySelector(`tr[data-request-id="${data.id}"]`);
                if (existingRow) {
                    console.log('Row already exists, skipping...');
                    return;
                }

                // Build battery health HTML
                let batteryHtml = '<span class="text-muted">N/A</span>';
                if (data.Batteryhealth) {
                    const healthClass = data.Batteryhealth >= 85 ? 'bg-success' : (data.Batteryhealth >= 70 ? 'bg-warning' : 'bg-danger');
                    batteryHtml = `
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ${healthClass}" style="width: ${data.Batteryhealth}%">
                                ${data.Batteryhealth}%
                            </div>
                        </div>
                        <small class="text-muted">Cycles: ${data.Cyclecount || 'N/A'}</small>
                    `;
                }

                // Build grade badge
                const gradeClass = data.Grade === 'RMA' ? 'bg-danger' : (data.Grade === 'REPAIR' ? 'bg-warning' : 'bg-success');
                
                // Build status badge
                let statusHtml = '<span class="badge badge-pass">All Pass</span>';
                if (data.Fail) {
                    const failCount = data.Fail.split(',').length;
                    statusHtml = `<span class="badge badge-fail" title="${data.Fail}">${failCount} Failed</span>`;
                }
                if (data.Comments) {
                    statusHtml += `<br><small class="text-muted">${data.Comments.substring(0, 30)}</small>`;
                }

                // Build IMEI cell
                let imeiHtml = '';
                if (data.Imei || data.Imei2) {
                    imeiHtml = `
                        <small>IMEI1: ${data.Imei || 'N/A'}</small><br>
                        <small>IMEI2: ${data.Imei2 || 'N/A'}</small>
                    `;
                } else {
                    imeiHtml = `
                        <span class="badge bg-danger">Missing IMEI</span>
                        <button type="button" class="btn btn-sm btn-warning mt-1" data-bs-toggle="modal" data-bs-target="#addImeiModal${data.id}">
                            Add IMEI
                        </button>
                    `;
                }

                // Build BatchID badge
                const batchId = data.BatchID || 'N/A';
                let batchClass = 'bg-secondary';
                if (batchId.toLowerCase().includes('eg')) batchClass = 'bg-primary';
                else if (batchId.toLowerCase().includes('yk')) batchClass = 'bg-info';

                // Create the row
                const newRow = document.createElement('tr');
                newRow.setAttribute('data-request-id', data.id);
                newRow.style.backgroundColor = '#28a74530';
                newRow.innerHTML = `
                    <td>
                        <input type="checkbox" class="form-check-input request-checkbox" value="${data.id}">
                    </td>
                    <td>
                        <strong>${data.ModelName || 'N/A'}</strong><br>
                        <small class="text-muted">${data.ModelNo || 'N/A'}</small>
                    </td>
                    <td>
                        <code>${data.Serial || 'N/A'}</code>
                    </td>
                    <td>${imeiHtml}</td>
                    <td>
                        ${data.Color || 'N/A'}<br>
                        <strong>${data.Memory || 'N/A'}</strong>
                    </td>
                    <td>${batteryHtml}</td>
                    <td>
                        <span class="badge ${gradeClass}">
                            ${data.Grade || 'N/A'}
                        </span>
                    </td>
                    <td>${statusHtml}</td>
                    <td>
                        ${data.TesterName || 'N/A'}<br>
                        <small class="text-muted">${data.PCName || 'N/A'}</small>
                    </td>
                    <td>
                        <span class="badge ${batchClass}">
                            ${batchId}
                        </span>
                    </td>
                    <td>
                        <div class="btn-group-vertical btn-group-sm">
                            <a href="{{url('testing/send_to_eg')}}/${data.id}" class="btn btn-primary btn-sm">
                                <i class="fe fe-send"></i> EG
                            </a>
                            <a href="{{url('testing/send_to_yk')}}/${data.id}" class="btn btn-info btn-sm">
                                <i class="fe fe-send"></i> YK
                            </a>
                            <a href="{{url('testing/delete_request')}}/${data.id}" class="btn btn-danger btn-sm" onclick="return confirm('Delete this request?')">
                                <i class="fe fe-trash"></i> Delete
                            </a>
                        </div>
                    </td>
                `;

                // Insert at the top of tbody
                tbody.insertBefore(newRow, tbody.firstChild);

                // Add event listener to the new checkbox
                const checkbox = newRow.querySelector('.request-checkbox');
                if (checkbox) {
                    checkbox.addEventListener('change', updateSelectedCount);
                }

                // Fade out the highlight after 3 seconds
                setTimeout(() => {
                    newRow.style.transition = 'background-color 1s';
                    newRow.style.backgroundColor = '';
                }, 3000);

                // Create modal if IMEI is missing
                if (!data.Imei && !data.Imei2 && data.Serial) {
                    createImeiModal(data.id, data.Serial);
                }
            }

            // Function to create IMEI modal dynamically
            function createImeiModal(requestId, serial) {
                const modalHtml = `
                    <div class="modal fade" id="addImeiModal${requestId}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="post" action="{{url('testing/add_imei')}}/${requestId}">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add IMEI for Serial: ${serial}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="serial" value="${serial}">
                                        <div class="mb-3">
                                            <label class="form-label">IMEI Number</label>
                                            <input type="text" name="imei" class="form-control" placeholder="Enter IMEI" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Add IMEI</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }

            // Test button for manual push notification (for development)
            console.log('📡 Pusher initialized. Listening for real-time updates...');
            console.log('Channel: testing-channel');
            console.log('Events: new-test-request, test-updated, test-deleted, bulk-action-completed');
        </script>

    @endsection
