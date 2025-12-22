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
    </style>
    @endsection
<br>
    @section('content')
        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">Marketplaces</span>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                                Marketplaces
                        </li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
        <br>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <button type="button" class="btn btn-primary" id="sync-all-btn" onclick="syncAllMarketplaces()">
                    <i class="mdi mdi-sync"></i> Sync All Marketplaces
                </button>
                <span id="sync-all-status" class="ms-2"></span>
            </div>
            <div>
                <a href="{{url('v2/marketplace/add')}}" class="btn btn-success"><i class="mdi mdi-plus"></i> Add Marketplace</a>
            </div>
        </div>
        
        <!-- Sync All Progress -->
        <div id="sync-all-progress" class="alert alert-info" style="display: none;">
            <h6><i class="mdi mdi-sync mdi-spin"></i> Syncing All Marketplaces...</h6>
            <div id="sync-all-logs" class="mt-2" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;">
                <div class="text-muted">Starting sync...</div>
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
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Marketplaces</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>{{ __('locale.S.No') }}</b></small></th>
                                        <th><small><b>Name</b></small></th>
                                        <th><small><b>Description</b></small></th>
                                        <th><small><b>Status</b></small></th>
                                        <th><small><b>API Key</b></small></th>
                                        <th><small><b>API Secret</b></small></th>
                                        <th><small><b>API URL</b></small></th>
                                        <th><small><b>Last Synced</b></small></th>
                                        <th><small><b>Actions</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                    @endphp
                                    @foreach ($marketplaces as $marketplace)
                                    @php
                                        $i++;
                                    @endphp
                                        <tr>
                                            <td title="{{$marketplace->id}}">{{$i}}</td>
                                            <td>{{$marketplace->name}}</td>
                                            <td>{{$marketplace->description ?? '-'}}</td>
                                            <td>
                                                @if(isset($marketplace->status) && $marketplace->status == 1)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($marketplace->api_key)
                                                    <div class="input-group">
                                                        <input type="password" 
                                                               class="form-control form-control-sm api-key-field" 
                                                               id="api_key_{{$marketplace->id}}" 
                                                               value="{{$marketplace->api_key}}" 
                                                               readonly 
                                                               style="font-size: 0.75rem;">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-secondary toggle-api-key" 
                                                                data-target="api_key_{{$marketplace->id}}"
                                                                title="Show/Hide">
                                                            <i class="fe fe-eye"></i>
                                                        </button>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($marketplace->api_secret)
                                                    <div class="input-group">
                                                        <input type="password" 
                                                               class="form-control form-control-sm api-secret-field" 
                                                               id="api_secret_{{$marketplace->id}}" 
                                                               value="{{$marketplace->api_secret}}" 
                                                               readonly 
                                                               style="font-size: 0.75rem;">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-secondary toggle-api-secret" 
                                                                data-target="api_secret_{{$marketplace->id}}"
                                                                title="Show/Hide">
                                                            <i class="fe fe-eye"></i>
                                                        </button>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($marketplace->api_url)
                                                    <small>{{$marketplace->api_url}}</small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div id="last-sync-{{$marketplace->id}}" class="text-muted small">
                                                    <span class="sync-status-indicator" data-marketplace-id="{{$marketplace->id}}">
                                                        <i class="mdi mdi-loading mdi-spin" style="display: none;"></i>
                                                        <span class="sync-time">Loading...</span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <center>
                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical tx-18"></i></a>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item sync-marketplace-btn" 
                                                           href="javascript:void(0);"
                                                           data-marketplace-id="{{$marketplace->id}}"
                                                           data-marketplace-name="{{$marketplace->name}}"
                                                           onclick="syncMarketplace({{$marketplace->id}}, '{{$marketplace->name}}')">
                                                            <i class="mdi mdi-sync"></i> Sync Stock
                                                        </a>
                                                        <a class="dropdown-item" href="{{url('v2/marketplace/edit')}}/{{$marketplace->id}}">
                                                            <i class="mdi mdi-pencil"></i> Edit
                                                        </a>
                                                    </div>
                                                </center>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <br>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @php
            session()->forget('success');
        @endphp
    @endsection

    @section('scripts')

                <!--Internal Sparkline js -->
                <script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

                <!-- Internal Piety js -->
                <script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

                <!-- Internal Chart js -->
                <script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

                <script>
                    // Toggle API Key visibility
                    document.addEventListener('DOMContentLoaded', function() {
                        // Handle API Key toggle
                        document.querySelectorAll('.toggle-api-key').forEach(function(button) {
                            button.addEventListener('click', function() {
                                const targetId = this.getAttribute('data-target');
                                const input = document.getElementById(targetId);
                                const icon = this.querySelector('i');
                                
                                if (input.type === 'password') {
                                    input.type = 'text';
                                    icon.classList.remove('fe-eye');
                                    icon.classList.add('fe-eye-off');
                                } else {
                                    input.type = 'password';
                                    icon.classList.remove('fe-eye-off');
                                    icon.classList.add('fe-eye');
                                }
                            });
                        });

                        // Handle API Secret toggle
                        document.querySelectorAll('.toggle-api-secret').forEach(function(button) {
                            button.addEventListener('click', function() {
                                const targetId = this.getAttribute('data-target');
                                const input = document.getElementById(targetId);
                                const icon = this.querySelector('i');
                                
                                if (input.type === 'password') {
                                    input.type = 'text';
                                    icon.classList.remove('fe-eye');
                                    icon.classList.add('fe-eye-off');
                                } else {
                                    input.type = 'password';
                                    icon.classList.remove('fe-eye-off');
                                    icon.classList.add('fe-eye');
                                }
                            });
                        });
                        
                        // Load sync status for all marketplaces
                        loadSyncStatuses();
                    });
                    
                    // Load sync status for all marketplaces
                    function loadSyncStatuses() {
                        @foreach ($marketplaces as $marketplace)
                            loadSyncStatus({{$marketplace->id}});
                        @endforeach
                    }
                    
                    // Load sync status for a specific marketplace
                    function loadSyncStatus(marketplaceId) {
                        fetch(`/v2/marketplace/sync-status/${marketplaceId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.stats) {
                                    const statusElement = document.querySelector(`#last-sync-${marketplaceId} .sync-time`);
                                    const stats = data.stats;
                                    
                                    if (stats.last_sync_time) {
                                        const lastSync = new Date(stats.last_sync_time);
                                        const hoursAgo = Math.floor((new Date() - lastSync) / (1000 * 60 * 60));
                                        
                                        if (hoursAgo < 1) {
                                            statusElement.textContent = 'Just now';
                                            statusElement.className = 'sync-time text-success';
                                        } else if (hoursAgo < 6) {
                                            statusElement.textContent = `${hoursAgo} hour(s) ago`;
                                            statusElement.className = 'sync-time text-info';
                                        } else {
                                            statusElement.textContent = `${hoursAgo} hour(s) ago`;
                                            statusElement.className = 'sync-time text-warning';
                                        }
                                    } else {
                                        statusElement.textContent = 'Never synced';
                                        statusElement.className = 'sync-time text-danger';
                                    }
                                    
                                    // Show stats
                                    if (stats.needs_sync > 0) {
                                        statusElement.textContent += ` (${stats.needs_sync} need sync)`;
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error loading sync status:', error);
                                document.querySelector(`#last-sync-${marketplaceId} .sync-time`).textContent = 'Error loading';
                            });
                    }
                    
                    // Sync a single marketplace
                    function syncMarketplace(marketplaceId, marketplaceName) {
                        const btn = document.querySelector(`.sync-marketplace-btn[data-marketplace-id="${marketplaceId}"]`);
                        const statusElement = document.querySelector(`#last-sync-${marketplaceId} .sync-status-indicator`);
                        const icon = statusElement.querySelector('i');
                        const timeElement = statusElement.querySelector('.sync-time');
                        
                        // Close dropdown menu
                        const dropdown = btn.closest('.dropdown-menu');
                        if (dropdown) {
                            const bsDropdown = bootstrap.Dropdown.getInstance(btn.closest('[data-bs-toggle="dropdown"]'));
                            if (bsDropdown) {
                                bsDropdown.hide();
                            }
                        }
                        
                        // Disable button and show loading
                        btn.style.pointerEvents = 'none';
                        btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Syncing...';
                        icon.style.display = 'inline-block';
                        timeElement.textContent = 'Syncing...';
                        timeElement.className = 'sync-time text-info';
                        
                        // Add log entry
                        addLog(`Starting sync for ${marketplaceName}...`, 'info');
                        
                        fetch(`/v2/marketplace/sync/${marketplaceId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            icon.style.display = 'none';
                            
                            if (data.success) {
                                if (data.queued) {
                                    // Job is queued and running in background
                                    btn.innerHTML = '<i class="mdi mdi-sync"></i> Sync Stock';
                                    timeElement.textContent = 'Syncing...';
                                    timeElement.className = 'sync-time text-info';
                                    
                                    addLog(`✓ ${marketplaceName} sync started in background`, 'success');
                                    if (data.job_id) {
                                        addLog(`Job ID: ${data.job_id}`, 'info');
                                    }
                                    addLog('Sync will continue even if you close this page. Check logs for progress.', 'info');
                                    
                                    // Poll for status updates
                                    pollSyncStatus(marketplaceId, 0);
                                } else {
                                    // Synchronous completion (fallback)
                                    btn.innerHTML = '<i class="mdi mdi-sync"></i> Sync Stock';
                                    timeElement.textContent = 'Just now';
                                    timeElement.className = 'sync-time text-success';
                                    
                                    let successMsg = `✓ ${marketplaceName} sync completed successfully`;
                                    if (data.stats) {
                                        successMsg += ` (${data.stats.synced_records || 0}/${data.stats.total_records || 0} records synced)`;
                                    }
                                    
                                    if (data.output && data.output.trim()) {
                                        const outputLines = data.output.trim().split('\n');
                                        outputLines.forEach(line => {
                                            if (line.trim()) {
                                                addLog(line.trim(), 'info');
                                            }
                                        });
                                    }
                                    
                                    addLog(successMsg, 'success');
                                    
                                    setTimeout(() => loadSyncStatus(marketplaceId), 1000);
                                }
                            } else {
                                btn.innerHTML = '<i class="mdi mdi-sync"></i> Sync Stock';
                                timeElement.textContent = 'Sync failed';
                                timeElement.className = 'sync-time text-danger';
                                
                                if (data.output && data.output.trim()) {
                                    const outputLines = data.output.trim().split('\n');
                                    outputLines.forEach(line => {
                                        if (line.trim()) {
                                            addLog(line.trim(), 'error');
                                        }
                                    });
                                }
                                
                                let errorMsg = `✗ ${marketplaceName} sync failed: ${data.message || 'Unknown error'}`;
                                addLog(errorMsg, 'error');
                            }
                            
                            btn.style.pointerEvents = 'auto';
                        })
                        .catch(error => {
                            icon.style.display = 'none';
                            btn.innerHTML = '<i class="mdi mdi-sync"></i> Sync Stock';
                            btn.style.pointerEvents = 'auto';
                            timeElement.textContent = 'Error';
                            timeElement.className = 'sync-time text-danger';
                            addLog(`✗ Error syncing ${marketplaceName}: ${error.message}`, 'error');
                            console.error('Error:', error);
                        });
                    }
                    
                    // Sync all marketplaces
                    function syncAllMarketplaces() {
                        const btn = document.getElementById('sync-all-btn');
                        const progressDiv = document.getElementById('sync-all-progress');
                        const logsDiv = document.getElementById('sync-all-logs');
                        const statusSpan = document.getElementById('sync-all-status');
                        
                        // Disable button and show progress
                        btn.disabled = true;
                        btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Syncing...';
                        progressDiv.style.display = 'block';
                        logsDiv.innerHTML = '<div class="text-muted">Starting sync for all marketplaces...</div>';
                        statusSpan.textContent = '';
                        statusSpan.className = '';
                        
                        addLog('Starting sync for all marketplaces...', 'info');
                        
                        fetch('/v2/marketplace/sync-all', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (data.queued) {
                                    // Jobs are queued and running in background
                                    btn.innerHTML = '<i class="mdi mdi-sync"></i> Sync All Marketplaces';
                                    statusSpan.textContent = '⏳ All syncs started in background';
                                    statusSpan.className = 'text-info';
                                    
                                    addLog('✓ Sync jobs started for all marketplaces', 'success');
                                    if (data.job_ids && data.job_ids.length > 0) {
                                        data.job_ids.forEach(jobInfo => {
                                            addLog(`  - ${jobInfo.marketplace_name}: Job ID ${jobInfo.job_id || 'N/A'}`, 'info');
                                        });
                                    }
                                    addLog('All syncs will continue in background. Check logs for progress.', 'info');
                                    
                                    // Poll for status updates
                                    setTimeout(() => {
                                        loadSyncStatuses();
                                        // Keep polling every 5 seconds
                                        const pollInterval = setInterval(() => {
                                            loadSyncStatuses();
                                        }, 5000);
                                        
                                        // Stop polling after 5 minutes
                                        setTimeout(() => clearInterval(pollInterval), 300000);
                                    }, 2000);
                                } else {
                                    // Synchronous completion (fallback)
                                    btn.innerHTML = '<i class="mdi mdi-sync"></i> Sync All Marketplaces';
                                    statusSpan.textContent = '✓ All marketplaces synced';
                                    statusSpan.className = 'text-success';
                                    
                                    if (data.results) {
                                        let successCount = 0;
                                        let failCount = 0;
                                        
                                        data.results.forEach(result => {
                                            if (result.success) {
                                                successCount++;
                                                let msg = `✓ ${result.marketplace_name} synced successfully`;
                                                if (result.output) {
                                                    addLog(result.output, 'info');
                                                }
                                                addLog(msg, 'success');
                                            } else {
                                                failCount++;
                                                let msg = `✗ ${result.marketplace_name} sync failed: ${result.error || 'Unknown error'}`;
                                                if (result.output) {
                                                    addLog(result.output, 'error');
                                                }
                                                addLog(msg, 'error');
                                            }
                                        });
                                        
                                        addLog(`Summary: ${successCount} succeeded, ${failCount} failed`, successCount > 0 ? 'success' : 'error');
                                    }
                                    
                                    addLog('All marketplaces sync completed!', 'success');
                                    
                                    setTimeout(() => loadSyncStatuses(), 2000);
                                }
                            } else {
                                btn.innerHTML = '<i class="mdi mdi-sync"></i> Sync All Marketplaces';
                                statusSpan.textContent = '✗ Sync failed';
                                statusSpan.className = 'text-danger';
                                
                                let errorMsg = `✗ Sync failed: ${data.message || 'Unknown error'}`;
                                if (data.output) {
                                    addLog(data.output, 'error');
                                }
                                addLog(errorMsg, 'error');
                            }
                            
                            btn.disabled = false;
                            
                            // Hide progress after 15 seconds (longer for background jobs)
                            setTimeout(() => {
                                progressDiv.style.display = 'none';
                            }, 15000);
                        })
                        .catch(error => {
                            btn.innerHTML = '<i class="mdi mdi-sync"></i> Sync All Marketplaces';
                            btn.disabled = false;
                            statusSpan.textContent = '✗ Error';
                            statusSpan.className = 'text-danger';
                            addLog(`✗ Error: ${error.message}`, 'error');
                            console.error('Error:', error);
                            
                            setTimeout(() => {
                                progressDiv.style.display = 'none';
                            }, 5000);
                        });
                    }
                    
                    // Add log entry
                    function addLog(message, type = 'info') {
                        const logsDiv = document.getElementById('sync-all-logs');
                        const logEntry = document.createElement('div');
                        logEntry.className = `mb-1 ${type === 'success' ? 'text-success' : type === 'error' ? 'text-danger' : 'text-info'}`;
                        logEntry.innerHTML = `<small>${new Date().toLocaleTimeString()} - ${message}</small>`;
                        logsDiv.appendChild(logEntry);
                        logsDiv.scrollTop = logsDiv.scrollHeight;
                    }
                    
                    // Poll sync status for a specific marketplace
                    function pollSyncStatus(marketplaceId, attempt = 0) {
                        if (attempt > 20) return; // Stop after 20 attempts (about 1 minute)
                        
                        setTimeout(() => {
                            loadSyncStatus(marketplaceId);
                            
                            const statusElement = document.querySelector(`#last-sync-${marketplaceId} .sync-time`);
                            if (statusElement && !statusElement.textContent.includes('Syncing...')) {
                                // Status updated, stop polling
                                return;
                            }
                            
                            // Continue polling
                            pollSyncStatus(marketplaceId, attempt + 1);
                        }, 3000); // Poll every 3 seconds
                    }
                </script>

    @endsection

