{{-- Variation History Modal --}}
<div class="modal fade" id="variationHistoryModal" tabindex="-1" aria-labelledby="variationHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="variation_name"></h5>
                <h5 class="modal-title" id="variationHistoryModalLabel"> &nbsp; History</h5>
                <button type="button" class="btn-close " data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x" class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Topup Ref</th>
                            <th>Pending Orders</th>
                            <th>Qty Before</th>
                            <th>Qty Added</th>
                            <th>Qty After</th>
                            <th>Admin</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="variationHistoryTable">
                        <!-- Data will be populated here via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{-- /Variation History Modal --}}

