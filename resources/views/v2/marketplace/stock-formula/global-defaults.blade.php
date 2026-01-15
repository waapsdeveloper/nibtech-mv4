@extends('layouts.app')

@section('styles')
<style>
    .marketplace-card {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 1rem;
        margin-bottom: 1rem;
        transition: box-shadow 0.15s ease-in-out;
    }
    .marketplace-card:hover {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    .marketplace-card.has-default {
        border-color: #0d6efd;
        background-color: #f8f9fa;
    }
    .formula-badge {
        font-size: 0.875rem;
    }
</style>
@endsection

@section('content')
<!-- breadcrumb -->
<div class="breadcrumb-header justify-content-between">
    <div class="left-content">
        <span class="main-content-title mg-b-0 mg-b-lg-1">Global Default Formulas</span>
    </div>
    <div class="justify-content-center mt-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
            <li class="breadcrumb-item tx-15"><a href="{{url('v2/marketplace')}}">Marketplaces</a></li>
            <li class="breadcrumb-item active" aria-current="page">Global Default Formulas</li>
        </ol>
    </div>
</div>
<!-- /breadcrumb -->

<hr style="border-bottom: 1px solid #000">

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fe fe-settings me-2"></i>Global Default Stock Formulas
                </h5>
                <p class="text-muted mb-0 small">Set default formulas that apply to all variations when no specific formula is configured. These defaults are used as fallback when neither a variation-specific formula nor a per-variation default exists.</p>
            </div>
            <div class="card-body">
                <div id="globalDefaultsContainer">
                    @foreach($marketplaces as $marketplace)
                        @php
                            $default = $globalDefaults[$marketplace->id] ?? null;
                            $hasDefault = $default !== null;
                        @endphp
                        <div class="marketplace-card {{ $hasDefault ? 'has-default' : '' }}" id="marketplace_card_{{ $marketplace->id }}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-2">
                                        <strong>{{ $marketplace->name }}</strong>
                                        @if($hasDefault)
                                            <span class="badge bg-success ms-2 formula-badge">Active</span>
                                        @else
                                            <span class="badge bg-secondary ms-2 formula-badge">No Default</span>
                                        @endif
                                    </h6>
                                    
                                    @if($hasDefault)
                                        @php
                                            $formula = $default->formula;
                                        @endphp
                                        <div class="mb-2">
                                            <span class="badge bg-info formula-badge me-1">
                                                {{ $formula['value'] ?? '' }}{{ ($formula['type'] ?? 'percentage') == 'percentage' ? '%' : '=' }} 
                                                ({{ ($formula['apply_to'] ?? 'pushed') == 'pushed' ? 'Pushed' : 'Total' }})
                                            </span>
                                            @if($default->min_threshold !== null || $default->max_threshold !== null)
                                                <span class="badge bg-warning text-dark formula-badge me-1">
                                                    Threshold: {{ $default->min_threshold ?? '' }}~{{ $default->max_threshold ?? '' }}
                                                </span>
                                            @endif
                                            @if($default->min_stock_required !== null)
                                                <span class="badge bg-danger formula-badge">
                                                    Min Stock Req: {{ $default->min_stock_required }}
                                                </span>
                                            @endif
                                        </div>
                                        @if($default->notes)
                                            <p class="text-muted small mb-0"><em>{{ $default->notes }}</em></p>
                                        @endif
                                        @if($default->admin)
                                            <p class="text-muted small mb-0">
                                                <small>Last updated by: {{ trim(($default->admin->first_name ?? '') . ' ' . ($default->admin->last_name ?? '')) ?: 'Unknown' }} on {{ $default->updated_at->format('Y-m-d H:i') }}</small>
                                            </p>
                                        @endif
                                    @else
                                        <p class="text-muted small mb-0">No global default formula set for this marketplace.</p>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 ms-3">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editGlobalDefault({{ $marketplace->id }})">
                                        <i class="fe fe-edit me-1"></i>{{ $hasDefault ? 'Edit' : 'Set Default' }}
                                    </button>
                                    @if($hasDefault)
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteGlobalDefault({{ $marketplace->id }})">
                                            <i class="fe fe-trash me-1"></i>Delete
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Global Default Modal -->
<div class="modal fade" id="editGlobalDefaultModal" tabindex="-1" aria-labelledby="editGlobalDefaultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGlobalDefaultModalLabel">Set Global Default Formula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="globalDefaultForm">
                    <input type="hidden" id="globalDefaultMarketplaceId" name="marketplace_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Marketplace</label>
                        <input type="text" class="form-control" id="globalDefaultMarketplaceName" readonly>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Value <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="globalDefaultValue" name="value" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="globalDefaultType" name="type" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed (=)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Apply To <span class="text-danger">*</span></label>
                            <select class="form-control" id="globalDefaultApplyTo" name="apply_to" required>
                                <option value="pushed">Pushed</option>
                                <option value="total">Total</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Min Threshold</label>
                            <input type="number" class="form-control" id="globalDefaultMinThreshold" name="min_threshold" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Threshold</label>
                            <input type="number" class="form-control" id="globalDefaultMaxThreshold" name="max_threshold" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Min Stock Required</label>
                            <input type="number" class="form-control" id="globalDefaultMinStockRequired" name="min_stock_required" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="globalDefaultNotes" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveGlobalDefaultBtn" onclick="saveGlobalDefault()">
                    <span class="spinner-border spinner-border-sm d-none" id="saveGlobalDefaultSpinner"></span>
                    Save Default
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const globalDefaultsConfig = {
        urls: {
            save: "{{ url('v2/marketplace/stock-formula/global-default') }}",
            delete: "{{ url('v2/marketplace/stock-formula/global-default') }}",
            get: "{{ url('v2/marketplace/stock-formula/global-defaults/api') }}"
        },
        csrfToken: "{{ csrf_token() }}"
    };
    
    let currentMarketplaceId = null;
    
    function editGlobalDefault(marketplaceId) {
        currentMarketplaceId = marketplaceId;
        const marketplaceName = $('#marketplace_card_' + marketplaceId).find('strong').text();
        
        // Load existing default if available
        $.ajax({
            url: globalDefaultsConfig.urls.get,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                const defaultData = response.defaults[marketplaceId];
                
                $('#globalDefaultMarketplaceId').val(marketplaceId);
                $('#globalDefaultMarketplaceName').val(marketplaceName);
                
                if (defaultData && defaultData.has_default) {
                    // Populate form with existing data
                    const formula = defaultData.formula;
                    $('#globalDefaultValue').val(formula.value || '');
                    $('#globalDefaultType').val(formula.type || 'percentage');
                    $('#globalDefaultApplyTo').val(formula.apply_to || 'pushed');
                    $('#globalDefaultMinThreshold').val(defaultData.min_threshold || '');
                    $('#globalDefaultMaxThreshold').val(defaultData.max_threshold || '');
                    $('#globalDefaultMinStockRequired').val(defaultData.min_stock_required || '');
                } else {
                    // Reset form
                    $('#globalDefaultForm')[0].reset();
                    $('#globalDefaultMarketplaceId').val(marketplaceId);
                    $('#globalDefaultMarketplaceName').val(marketplaceName);
                }
                
                const modal = new bootstrap.Modal(document.getElementById('editGlobalDefaultModal'));
                modal.show();
            },
            error: function(xhr, status, error) {
                console.error('Error loading global default:', error);
                showAlert('Error loading global default data', 'danger');
            }
        });
    }
    
    function saveGlobalDefault() {
        const marketplaceId = $('#globalDefaultMarketplaceId').val();
        if (!marketplaceId) {
            showAlert('Marketplace ID is required', 'danger');
            return;
        }
        
        const formData = {
            value: $('#globalDefaultValue').val(),
            type: $('#globalDefaultType').val(),
            apply_to: $('#globalDefaultApplyTo').val(),
            min_threshold: $('#globalDefaultMinThreshold').val() || null,
            max_threshold: $('#globalDefaultMaxThreshold').val() || null,
            min_stock_required: $('#globalDefaultMinStockRequired').val() || null,
            notes: $('#globalDefaultNotes').val() || null,
            _token: globalDefaultsConfig.csrfToken
        };
        
        // Validate required fields
        if (!formData.value || !formData.type || !formData.apply_to) {
            showAlert('Please fill in all required fields', 'danger');
            return;
        }
        
        const btn = $('#saveGlobalDefaultBtn');
        const spinner = $('#saveGlobalDefaultSpinner');
        btn.prop('disabled', true);
        spinner.removeClass('d-none');
        
        $.ajax({
            url: globalDefaultsConfig.urls.save + '/' + marketplaceId,
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': globalDefaultsConfig.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.success) {
                    showAlert('Global default formula saved successfully', 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editGlobalDefaultModal'));
                    modal.hide();
                    // Reload page to show updated data
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showAlert(response.message || 'Error saving global default', 'danger');
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving global default:', error);
                let errorMsg = 'Error saving global default';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showAlert(errorMsg, 'danger');
                btn.prop('disabled', false);
                spinner.addClass('d-none');
            }
        });
    }
    
    function deleteGlobalDefault(marketplaceId) {
        if (!confirm('Are you sure you want to delete the global default formula for this marketplace?')) {
            return;
        }
        
        $.ajax({
            url: globalDefaultsConfig.urls.delete + '/' + marketplaceId,
            type: 'DELETE',
            data: {
                _token: globalDefaultsConfig.csrfToken
            },
            headers: {
                'X-CSRF-TOKEN': globalDefaultsConfig.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.success) {
                    showAlert('Global default formula deleted successfully', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showAlert(response.message || 'Error deleting global default', 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error deleting global default:', error);
                let errorMsg = 'Error deleting global default';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showAlert(errorMsg, 'danger');
            }
        });
    }
    
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#alert-container').html(alertHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Auto-hide alerts on close
    $(document).on('click', '.alert .btn-close', function() {
        $(this).closest('.alert').fadeOut(function() {
            $(this).remove();
        });
    });
</script>
@endsection
