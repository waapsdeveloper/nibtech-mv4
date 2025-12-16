<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkModal" tabindex="-1" aria-labelledby="bulkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkModalLabel">Bulk Update Target Prices</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x" class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Model</th>
                                <th>Target Price</th>
                                <th>Target Percentage</th>
                            </tr>
                        </thead>
                        <tbody id="bulkUpdateTable">
                            <tr>
                                <td colspan="3" class="text-center text-muted">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    Loading variations...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Bulk Update Modal -->

