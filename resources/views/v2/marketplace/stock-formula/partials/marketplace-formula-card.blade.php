@php
    $isFirstMarketplace = $marketplaceStock['marketplace_id'] == 1;
@endphp

<div class="formula-card" id="marketplace_card_{{ $marketplaceStock['marketplace_id'] }}">
    <div class="d-flex justify-content-between align-items-center gap-3">
        <!-- Left Side: Marketplace Info -->
        <div class="d-flex align-items-center gap-3">
            <div>
                <h6 class="mb-0">{{ $marketplaceStock['marketplace_name'] }}</h6>
                <small class="text-muted">
                    Stock: <span id="stock_{{ $marketplaceStock['marketplace_id'] }}">{{ $marketplaceStock['listed_stock'] }}</span>
                </small>
                <!-- Reset Stock Form -->
                <div class="mt-1">
                    <form class="d-inline-flex align-items-center gap-1 reset-stock-form" 
                          id="reset_stock_form_{{ $marketplaceStock['marketplace_id'] }}" 
                          data-variation-id="{{ $variationId }}" 
                          data-marketplace-id="{{ $marketplaceStock['marketplace_id'] }}"
                          onsubmit="return false;">
                        <input type="number" 
                               class="form-control form-control-sm" 
                               name="stock"
                               id="reset_stock_value_{{ $marketplaceStock['marketplace_id'] }}"
                               value="{{ $marketplaceStock['listed_stock'] }}"
                               min="0"
                               placeholder="Set stock"
                               style="width: 80px; height: 24px; font-size: 0.75rem;"
                               required>
                        <button type="button" class="btn btn-sm btn-secondary reset-stock-btn" style="height: 24px; padding: 0 8px; font-size: 0.75rem;" title="Reset stock to exact value" data-form-id="reset_stock_form_{{ $marketplaceStock['marketplace_id'] }}">
                            <i class="fe fe-refresh-cw"></i>
                        </button>
                    </form>
                </div>
            </div>
            @if($marketplaceStock['has_formula'])
            @php $formula = $marketplaceStock['formula']; @endphp
            <div id="formula_display_{{ $marketplaceStock['marketplace_id'] }}">
                <span class="formula-badge bg-info text-white">
                    {{ $formula['value'] ?? '' }}{{ ($formula['type'] ?? 'percentage') == 'percentage' ? '%' : '=' }} 
                    ({{ ($formula['apply_to'] ?? 'pushed') == 'pushed' ? 'Pushed' : 'Total' }})
                </span>
            </div>
            @endif
        </div>
        
        <!-- Right Side: Formula Form -->
        <div class="flex-shrink-0">
            @if($isFirstMarketplace)
            <div class="text-muted small d-flex align-items-center gap-2">
                <i class="fe fe-lock"></i>
                <span>Formula locked - remaining stock goes here</span>
            </div>
            @else
            <form class="formula-inline-form" id="formula_form_{{ $marketplaceStock['marketplace_id'] }}" data-variation-id="{{ $variationId }}" data-marketplace-id="{{ $marketplaceStock['marketplace_id'] }}">
                <div class="d-flex align-items-center gap-2">
                    <input type="number" 
                           class="form-control form-control-sm" 
                           name="value" 
                           id="formula_value_{{ $marketplaceStock['marketplace_id'] }}"
                           value="{{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['value'])) ? $marketplaceStock['formula']['value'] : '' }}"
                           step="0.01"
                           min="0"
                           placeholder="Value"
                           style="width: 100px;"
                           required>
                    <select class="form-control form-control-sm" name="type" id="formula_type_{{ $marketplaceStock['marketplace_id'] }}" style="width: 80px;">
                        <option value="percentage" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['type']) && $marketplaceStock['formula']['type'] == 'percentage') ? 'selected' : '' }}>%</option>
                        <option value="fixed" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['type']) && $marketplaceStock['formula']['type'] == 'fixed') ? 'selected' : '' }}>=</option>
                    </select>
                    <select class="form-control form-control-sm" name="apply_to" id="formula_apply_to_{{ $marketplaceStock['marketplace_id'] }}" style="width: 150px;">
                        <option value="pushed" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['apply_to']) && $marketplaceStock['formula']['apply_to'] == 'pushed') ? 'selected' : 'selected' }}>Pushed Value</option>
                        <option value="total" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['apply_to']) && $marketplaceStock['formula']['apply_to'] == 'total') ? 'selected' : '' }}>Total Stock</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fe fe-save"></i> Save
                    </button>
                    @if($marketplaceStock['has_formula'])
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteFormula({{ $variationId }}, {{ $marketplaceStock['marketplace_id'] }})">
                        <i class="fe fe-trash"></i>
                    </button>
                    @endif
                </div>
            </form>
            @endif
        </div>
    </div>
</div>

