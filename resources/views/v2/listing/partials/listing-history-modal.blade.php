{{-- Listing History Modal --}}
<div class="modal fade" id="listingHistoryModal" tabindex="-1" aria-labelledby="listingHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down" style="max-width: 95vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="listingHistoryModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x" class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th width="12%">Date</th>
                                <th width="10%">Field</th>
                                <th width="13%">Old Value</th>
                                <th width="13%">New Value</th>
                                <th width="8%">Change Type</th>
                                <th width="11%">Changed By</th>
                                <th width="18%">Reason</th>
                                <th width="15%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="listingHistoryTable">
                            <tr>
                                <td colspan="8" class="text-center text-muted">Loading history...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
{{-- /Listing History Modal --}}

