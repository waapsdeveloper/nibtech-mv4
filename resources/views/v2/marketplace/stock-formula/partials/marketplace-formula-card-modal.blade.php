@php
    $isFirstMarketplace = $marketplaceStock['marketplace_id'] == 1;
@endphp

<div class="marketplace-formula-card-modal" id="marketplace_card_{{ $marketplaceStock['marketplace_id'] }}">
    <div class="d-flex justify-content-between align-items-center gap-3">
        <!-- Left Side: Marketplace Info -->
        <div class="d-flex align-items-center gap-3 flex-grow-1">
            <div>
                <strong class="d-block">
                    {{ $marketplaceStock['marketplace_name'] }}
                    @if(isset($isUsingDefault) && $isUsingDefault)
                        <span class="badge bg-secondary" style="font-size: 0.65rem;" title="Using default formula">Default</span>
                    @endif
                </strong>
                <small class="text-muted">
                    Stock: <span id="stock_{{ $marketplaceStock['marketplace_id'] }}">{{ $marketplaceStock['listed_stock'] }}</span>
                    @if(isset($marketplaceStock['min_threshold']) && $marketplaceStock['min_threshold'] !== null)
                        | Min: {{ $marketplaceStock['min_threshold'] }}
                    @endif
                    @if(isset($marketplaceStock['max_threshold']) && $marketplaceStock['max_threshold'] !== null)
                        | Max: {{ $marketplaceStock['max_threshold'] }}
                    @endif
                </small>
            </div>
            @if($marketplaceStock['has_formula'])
            @php $formula = $marketplaceStock['formula']; @endphp
            <div id="formula_display_{{ $marketplaceStock['marketplace_id'] }}">
                <span class="badge bg-info">
                    {{ $formula['value'] ?? '' }}{{ ($formula['type'] ?? 'percentage') == 'percentage' ? '%' : '=' }} 
                    ({{ ($formula['apply_to'] ?? 'pushed') == 'pushed' ? 'Pushed' : 'Total' }})
                </span>
            </div>
            @endif
        </div>
        
        <!-- Right Side: Formula Form -->
        <div class="flex-shrink-0">
            @if($isFirstMarketplace)
            <div class="text-muted small">
                <i class="fe fe-lock"></i> Locked
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
                           style="width: 80px;"
                           required>
                    <select class="form-control form-control-sm" name="type" id="formula_type_{{ $marketplaceStock['marketplace_id'] }}" style="width: 70px;">
                        <option value="percentage" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['type']) && $marketplaceStock['formula']['type'] == 'percentage') ? 'selected' : '' }}>%</option>
                        <option value="fixed" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['type']) && $marketplaceStock['formula']['type'] == 'fixed') ? 'selected' : '' }}>=</option>
                    </select>
                    <select class="form-control form-control-sm" name="apply_to" id="formula_apply_to_{{ $marketplaceStock['marketplace_id'] }}" style="width: 120px;">
                        <option value="pushed" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['apply_to']) && $marketplaceStock['formula']['apply_to'] == 'pushed') ? 'selected' : 'selected' }}>Pushed</option>
                        <option value="total" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['apply_to']) && $marketplaceStock['formula']['apply_to'] == 'total') ? 'selected' : '' }}>Total</option>
                    </select>
                    <input type="number" 
                           class="form-control form-control-sm" 
                           name="min_threshold" 
                           id="min_threshold_{{ $marketplaceStock['marketplace_id'] }}"
                           value="{{ $marketplaceStock['min_threshold'] ?? '' }}"
                           min="0"
                           placeholder="Min"
                           style="width: 70px;"
                           title="Min threshold">
                    <input type="number" 
                           class="form-control form-control-sm" 
                           name="max_threshold" 
                           id="max_threshold_{{ $marketplaceStock['marketplace_id'] }}"
                           value="{{ $marketplaceStock['max_threshold'] ?? '' }}"
                           min="0"
                           placeholder="Max"
                           style="width: 70px;"
                           title="Max threshold">
                    @if($marketplaceStock['has_formula'])
                    <button type="button" class="btn btn-sm btn-danger p-1" onclick="deleteFormula({{ $variationId }}, {{ $marketplaceStock['marketplace_id'] }})" style="width: 30px; height: 30px;" title="Delete Formula">
                        <i class="fe fe-trash"></i>
                    </button>
                    @endif
                </div>
            </form>
            @endif
        </div>
    </div>
</div>
