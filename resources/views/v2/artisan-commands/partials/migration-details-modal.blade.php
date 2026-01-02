{{-- Migration Details Modal --}}
<div class="modal fade" id="migrationDetailsModal" tabindex="-1" aria-labelledby="migrationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="migrationDetailsModalLabel">
                    <i class="fe fe-info me-2"></i>Migration Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="migrationDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="runSingleMigrationBtn" style="display: none;" onclick="runSingleMigration()">
                    <i class="fe fe-play me-1"></i>Run This Migration
                </button>
            </div>
        </div>
    </div>
</div>

