@php
    // This partial handles the marketplace stock editing interface
    // Expected variables: $variationId, $marketplaceIdInt, $currentStock, $process_id (optional)
@endphp

<!-- Marketplace Stock Editor Component -->
<div class="marketplace-stock-container d-flex align-items-center gap-1" id="marketplace_stock_{{ $variationId }}_{{ $marketplaceIdInt }}">
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustMarketplaceStock({{ $variationId }}, {{ $marketplaceIdInt }}, -1)" style="width: 28px; height: 28px; padding: 0; line-height: 1;">-</button>
    <input type="number" class="form-control form-control-sm text-center" id="stock_input_{{ $variationId }}_{{ $marketplaceIdInt }}" value="{{ $currentStock }}" style="width: 60px; height: 28px;" min="0" onchange="checkStockChange({{ $variationId }}, {{ $marketplaceIdInt }})" oninput="checkStockChange({{ $variationId }}, {{ $marketplaceIdInt }})">
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustMarketplaceStock({{ $variationId }}, {{ $marketplaceIdInt }}, 1)" style="width: 28px; height: 28px; padding: 0; line-height: 1;">+</button>
    <button type="button" class="btn btn-sm btn-primary" id="save_stock_{{ $variationId }}_{{ $marketplaceIdInt }}" onclick="saveMarketplaceStock({{ $variationId }}, {{ $marketplaceIdInt }})" style="height: 28px; width: 28px; padding: 0; line-height: 1; display: none;" title="Save">
        <i class="fas fa-check" style="font-size: 0.75rem;"></i>
    </button>
    <button type="button" class="btn btn-sm btn-secondary" id="cancel_stock_{{ $variationId }}_{{ $marketplaceIdInt }}" onclick="cancelEditMarketplaceStock({{ $variationId }}, {{ $marketplaceIdInt }})" style="height: 28px; width: 28px; padding: 0; line-height: 1; display: none;" title="Cancel">
        <i class="fas fa-times" style="font-size: 0.75rem;"></i>
    </button>
    <span class="stock-value d-none" id="stock_display_{{ $variationId }}_{{ $marketplaceIdInt }}">{{ $currentStock }}</span>
</div>
<span class="text-success small d-none" id="success_marketplace_{{ $variationId }}_{{ $marketplaceIdInt }}"></span>

@once
<script>
    // Marketplace stock editor JavaScript - defined once for all instances
    // Store original stock values
    if (typeof window.originalStockValues === 'undefined') {
        window.originalStockValues = {};
    }
    
    // Check if stock value has changed and show/hide action buttons
    window.checkStockChange = function(variationId, marketplaceId) {
        const input = $('#stock_input_' + variationId + '_' + marketplaceId);
        const currentValue = parseInt(input.val()) || 0;
        const originalValue = window.originalStockValues[variationId + '_' + marketplaceId] !== undefined 
            ? window.originalStockValues[variationId + '_' + marketplaceId] 
            : parseInt($('#stock_display_' + variationId + '_' + marketplaceId).text()) || 0;
        
        const saveBtn = $('#save_stock_' + variationId + '_' + marketplaceId);
        const cancelBtn = $('#cancel_stock_' + variationId + '_' + marketplaceId);
        
        if (currentValue !== originalValue) {
            // Value changed - show buttons
            saveBtn.show();
            cancelBtn.show();
        } else {
            // Value same as original - hide buttons
            saveBtn.hide();
            cancelBtn.hide();
        }
    };
    
    // Initialize original values on page load
    $(document).ready(function() {
        $('[id^="stock_input_"]').each(function() {
            const id = $(this).attr('id');
            const matches = id.match(/stock_input_(\d+)_(\d+)/);
            if (matches) {
                const variationId = matches[1];
                const marketplaceId = matches[2];
                const originalValue = parseInt($(this).val()) || 0;
                window.originalStockValues[variationId + '_' + marketplaceId] = originalValue;
            }
        });
    });
    
    // Define functions only once
    if (typeof window.adjustMarketplaceStock === 'undefined') {
    window.adjustMarketplaceStock = function(variationId, marketplaceId, change) {
        const input = $('#stock_input_' + variationId + '_' + marketplaceId);
        let currentValue = parseInt(input.val()) || 0;
        currentValue += change;
        if (currentValue < 0) currentValue = 0;
        input.val(currentValue);
        // Trigger change check to show/hide buttons
        window.checkStockChange(variationId, marketplaceId);
    };
    }
    
    if (typeof window.saveMarketplaceStock === 'undefined') {
    window.saveMarketplaceStock = function(variationId, marketplaceId) {
        const input = $('#stock_input_' + variationId + '_' + marketplaceId);
        const newValue = parseInt(input.val()) || 0;
        const originalValue = window.originalStockValues[variationId + '_' + marketplaceId] !== undefined 
            ? window.originalStockValues[variationId + '_' + marketplaceId] 
            : parseInt($('#stock_display_' + variationId + '_' + marketplaceId).text()) || 0;
        const stockChange = newValue - originalValue;
        
        if (stockChange === 0) {
            window.cancelEditMarketplaceStock(variationId, marketplaceId);
            return;
        }
        
        const actionUrl = "{{ url('listing/add_quantity_marketplace') }}/" + variationId + "/" + marketplaceId;
        const successSpan = $('#success_marketplace_' + variationId + '_' + marketplaceId);
        const saveBtn = $('#save_stock_' + variationId + '_' + marketplaceId);
        const cancelBtn = $('#cancel_stock_' + variationId + '_' + marketplaceId);
        
        saveBtn.prop('disabled', true);
        successSpan.text('Saving...').removeClass('d-none');
        
        const ajaxData = {
            _token: "{{ csrf_token() }}",
            stock: stockChange,
            marketplace_id: marketplaceId
        };
        
        @if(isset($process_id) && $process_id)
        ajaxData.process_id = {{ $process_id }};
        @endif
        
        $.ajax({
            type: "POST",
            url: actionUrl,
            data: ajaxData,
            dataType: 'json',
            success: function(response) {
                const marketplaceStock = response.marketplace_stock || newValue;
                const totalStock = response.total_stock || 0;
                
                // Update input and display with new stock value
                input.val(marketplaceStock);
                $('#stock_display_' + variationId + '_' + marketplaceId).text(marketplaceStock);
                $('#total_stock_' + variationId).val(totalStock);
                
                // Update original value
                window.originalStockValues[variationId + '_' + marketplaceId] = marketplaceStock;
                
                // Hide action buttons
                saveBtn.hide().prop('disabled', false);
                cancelBtn.hide();
                
                // Show success message
                successSpan.text("Stock updated to " + marketplaceStock).removeClass('d-none');
                setTimeout(function() {
                    successSpan.addClass('d-none');
                }, 3000);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                let errorMsg = "Error: " + textStatus;
                if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                    errorMsg = jqXHR.responseJSON.error;
                } else if (jqXHR.responseText) {
                    errorMsg = jqXHR.responseText;
                }
                alert(errorMsg);
                successSpan.addClass('d-none');
                saveBtn.prop('disabled', false);
                
                // Restore original value on error
                const originalValue = window.originalStockValues[variationId + '_' + marketplaceId];
                if (originalValue !== undefined) {
                    input.val(originalValue);
                    window.checkStockChange(variationId, marketplaceId);
                }
            }
        });
    };
    }
    
    if (typeof window.cancelEditMarketplaceStock === 'undefined') {
    window.cancelEditMarketplaceStock = function(variationId, marketplaceId) {
        const input = $('#stock_input_' + variationId + '_' + marketplaceId);
        const saveBtn = $('#save_stock_' + variationId + '_' + marketplaceId);
        const cancelBtn = $('#cancel_stock_' + variationId + '_' + marketplaceId);
        
        // Restore original value to input
        const originalValue = window.originalStockValues[variationId + '_' + marketplaceId] !== undefined 
            ? window.originalStockValues[variationId + '_' + marketplaceId] 
            : parseInt($('#stock_display_' + variationId + '_' + marketplaceId).text()) || 0;
        
        input.val(originalValue);
        
        // Hide action buttons
        saveBtn.hide();
        cancelBtn.hide();
    };
    }
    
    // Handle Enter key on marketplace stock input (attach once)
    if (typeof window.marketplaceStockEditorInitialized === 'undefined') {
        window.marketplaceStockEditorInitialized = true;
        $(document).on('keypress', '[id^="stock_input_"]', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                const inputId = $(this).attr('id');
                const matches = inputId.match(/stock_input_(\d+)_(\d+)/);
                if (!matches) return;
                
                const variationId = matches[1];
                const marketplaceId = matches[2];
                const saveBtn = $('#save_stock_' + variationId + '_' + marketplaceId);
                // Only save if save button is visible (value has changed)
                if (saveBtn.is(':visible')) {
                    window.saveMarketplaceStock(variationId, marketplaceId);
                }
            }
        });
    }
</script>
@endonce

