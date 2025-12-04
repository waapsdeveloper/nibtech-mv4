{{-- Marketplace Bar Toggle Section Partial --}}
{{-- This is a JavaScript template function for generating marketplace toggle content --}}
<script>
    window.renderMarketplaceToggleContent = function(variationId, marketplaceId, marketplaceName, additionalData) {
        // additionalData can contain any extra information needed for the toggle view
        const data = additionalData || {};
        
        return `
            <div class="marketplace-toggle-content collapse" id="marketplace_toggle_${variationId}_${marketplaceId}">
                <div class="p-3 bg-light border-top">
                    ${data.tablesHtml || '<div class="text-muted">Tables will be loaded here</div>'}
                </div>
            </div>
        `;
    };
</script>

