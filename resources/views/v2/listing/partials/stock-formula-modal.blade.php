{{-- Stock Formula Modal --}}
<div class="modal fade" id="stockFormulaModal" tabindex="-1" aria-labelledby="stockFormulaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="stockFormulaModalLabel">
                    <i class="fe fe-percent me-2"></i><span id="stockFormulaModalTitle">Stock Formula</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x" class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body py-2" id="stockFormulaModalBody">
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading stock formulas...</p>
                </div>
            </div>
            <div class="modal-footer py-2" id="stockFormulaModalFooter" style="display: none;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveAllFormulasBtn">Save</button>
            </div>
        </div>
    </div>
</div>
{{-- /Stock Formula Modal --}}
