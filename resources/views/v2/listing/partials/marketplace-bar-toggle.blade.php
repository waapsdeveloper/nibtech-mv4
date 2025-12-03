{{-- Marketplace Bar Toggle Section Partial --}}
{{-- This is a JavaScript template function for generating marketplace toggle content --}}
<script>
    window.renderMarketplaceToggleContent = function(variationId, marketplaceId, marketplaceName, additionalData) {
        // additionalData can contain any extra information needed for the toggle view
        const data = additionalData || {};
        
        return `
            <div class="marketplace-toggle-content collapse" id="marketplace_toggle_${variationId}_${marketplaceId}">
                <div class="p-3 bg-light border-top">
                    <div class="row">
                        <div class="col-12">
                            <h6 class="fw-bold mb-3">Marketplace Details</h6>
                            <p class="text-muted small">This is a test view for marketplace <strong>${marketplaceName}</strong> (ID: ${marketplaceId}) of variation ${variationId}.</p>
                            <p class="text-muted small">Additional content can be added here based on requirements.</p>
                            ${data.customContent || ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    };
</script>

