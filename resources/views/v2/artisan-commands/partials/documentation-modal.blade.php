{{-- Documentation Modal --}}
<div class="modal fade" id="documentationModal" tabindex="-1" aria-labelledby="documentationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentationModalLabel">
                    <i class="fe fe-file-text me-2"></i>Documentation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="documentationContent" class="markdown-content" style="max-height: 85vh; overflow-y: auto;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

