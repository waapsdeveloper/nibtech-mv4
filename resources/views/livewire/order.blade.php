{{-- @extends('layouts.app') --}}

    @section('styles')
        <style>
            .rows{
                border: 1px solid #016a5949;
            }
            .columns{
                background-color:#016a5949;
                padding-top:5px
            }
            .childs{
                padding-top:5px
            }
            .form-floating>.form-control,
            .form-floating>.form-control-plaintext {
            padding: 0rem 0.75rem;
            }

            .form-floating>.form-control,
            .form-floating>.form-control-plaintext,
            .form-floating>.form-select {
            height: calc(2.5rem + 2px);
            line-height: 1;
            }

            .form-floating>label {
            padding: 0.5rem 0.75rem;
            }
        </style>
    @endsection
<br>
    @section('content')

<div class="toast-container position-fixed top-0 end-0 p-5" style="z-index: 1000">

    @if (session('copy'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Function to copy text to clipboard
                function copyToClipboard(text) {
                    var tempInput = document.createElement('textarea');
                    tempInput.value = text;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                }

                // Check if there is a copy message in the session
                var copiedText = "{{ session('copy') }}";
                if (copiedText) {
                    // Copy the IMEI number to the clipboard
                    copyToClipboard(copiedText);

                    // Show success toast
                    var toastContainer = document.querySelector('.toast-container');
                    var toastBody = document.querySelector('.toast-body');
                    toastBody.innerText = "Message copied to clipboard: \n" + copiedText;
                    var toast = new bootstrap.Toast(document.querySelector('.toast'));
                    toast.show();
                }
            });
        </script>
        @php
        session()->forget('copy');
        @endphp
    @endif
</div>


        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Orders</span> --}}
                <a href="{{url('refresh_order')}}" target="_blank" class="mg-b-0 mg-b-lg-1 btn btn-primary">Recheck All</a>
                <a href="{{url('check_new')}}" class="mg-b-0 mg-b-lg-1 btn btn-primary">Check for New</a>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Orders</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Search</h4></center>
            </div>
        </div>
        <br>
        <form action="" method="GET" id="search">
            <div class="row">
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="order_id_input" name="order_id" placeholder="Enter ID" value="@isset($_GET['order_id']){{$_GET['order_id']}}@endisset">
                        <label for="order_id_input">Order Number</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input class="form-control" id="start_date_input" name="start_date" id="datetimepicker" type="date" value="@isset($_GET['start_date']){{$_GET['start_date']}}@endisset">
                        <label for="start_date_input">{{ __('locale.Start Date') }}</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input class="form-control" id="start_time_input" name="start_time" id="timetimepicker" type="time" value="@isset($_GET['start_time']){{$_GET['start_time']}}@endisset">
                        <label for="start_time_input">Time</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input class="form-control" id="end_date_input" name="end_date" id="datetimepicker" type="date" value="@isset($_GET['end_date']){{$_GET['end_date']}}@endisset">
                        <label for="end_date_input">{{ __('locale.End Date') }}</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input class="form-control" id="end_time_input" name="end_time" id="timetimepicker" type="time" value="@isset($_GET['end_time']){{$_GET['end_time']}}@endisset">
                        <label for="end_time_input">Time</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control focused" id="sku_input" name="sku" placeholder="Enter SKU" value="@isset($_GET['sku']){{$_GET['sku']}}@endisset" autofocus>
                        <label for="sku_input" class="">SKU</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    {{-- <div class="form-floating"> --}}
                        <select id="status_input" name="status" class="form-control form-select" data-bs-placeholder="Select Status">
                            <option value="">Status</option>
                            @foreach ($order_statuses as $id => $status)
                                <option value="{{$id}}" @if(isset($_GET['status']) && $id == $_GET['status']) {{'selected'}}@endif>{{$status}}</option>
                            @endforeach
                        </select>
                        {{-- <label for="status_input">Status</label>
                    </div> --}}
                </div>
                <button class="btn btn-primary" type="submit">{{ __('locale.Search') }}</button>
                <a href="{{url('order')}}?per_page=10" class="btn btn-default">Reset</a>
            </div>
                <br>
            <div class="row">
                <div class="col-lg-2 col-xl-2 col-md-3 col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset">
                        <label for="">IMEI</label>
                    </div>
                </div>
                <div class="col-lg-2 col-xl-2 col-md-3 col-sm-6">
                    {{-- <div class="form-floating"> --}}
                        <select id="adm_input" name="adm" class="form-control form-select" data-bs-placeholder="Select Processed By">
                            <option value="">Processed by</option>
                            <option value="0">None</option>
                            @foreach ($admins as $id => $adm)
                                <option value="{{$id}}" @if(isset($_GET['adm']) && $id == $_GET['adm']) {{'selected'}}@endif>{{$adm }}</option>
                            @endforeach
                        </select>
                        {{-- <label for="adm_input">Processed By</label> --}}
                    {{-- </div> --}}
                </div>
                <div class="col-lg-2 col-xl-2 col-md-3 col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="tracking_number" placeholder="Enter Tracking Number" value="@isset($_GET['tracking_number']){{$_GET['tracking_number']}}@endisset">
                        <label for="">Tracking Number</label>
                    </div>
                </div>

                <div class="col-lg-2 col-xl-2 col-md-3 col-sm-6">
                    <select name="with_stock" class="form-control form-select" data-bs-placeholder="Select With Stock">
                        <option value="">With & Without Stock</option>
                        <option value="1" @if(isset($_GET['with_stock']) && $_GET['with_stock'] == 1) {{'selected'}}@endif>With Stock</option>
                        <option value="2" @if(isset($_GET['with_stock']) && $_GET['with_stock'] == 2) {{'selected'}}@endif>Without Stock</option>
                    </select>
                </div>

                <div class="col-lg-2 col-xl-2 col-md-3 col-sm-6">
                    <select name="exclude_topup[]" class="form-control form-select select2" multiple data-bs-placeholder="Exclude Topups">
                        <option value="">Exclude Topups</option>
                        @foreach ($topups as $id => $name)
                            <option value="{{ $id }}" @if (isset($_GET['exclude_topup']) && in_array($id, $_GET['exclude_topup'])) {{ 'selected' }} @endif>
                                {{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-xl-2 col-md-3 col-sm-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="invoice" name="invoice" value="1" @if (request('invoice') == "1") {{'checked'}} @endif>
                        <label class="form-check-label" for="invoice">Invoice Mode</label>
                    </div>
                </div>
                <input type="hidden" name="page" value="{{ Request::get('page') }}">
                @if (Request::get('care') == 1)
                    <input type="hidden" name="care" value="{{ Request::get('care') }}">
                @endif
                @if (Request::get('missing'))
                    <input type="hidden" name="missing" value="{{ Request::get('missing') }}">
                @endif
                @if (Request::get('transaction'))
                    <input type="hidden" name="transaction" value="{{ Request::get('transaction') }}">
                @endif
            </div>

        </form>
        <div class="d-flex justify-content-between">
            <div class="">
                <a href="{{url('order')}}" class="btn btn-link">All Order</a>
                <a href="{{url('order')}}?status=2" class="btn btn-link">Pending Order ({{ $pending_orders_count }})</a>
                <a href="{{url('order')}}?care=1" class="btn btn-link">Conversation</a>
                <a href="{{url('order')}}?missing=refund" class="btn btn-link">Missing Refund</a>
                <a href="{{url('order')}}?missing=reimburse" class="btn btn-link">Missing Reimburse</a>
                <a href="{{url('order')}}?missing=purchase" class="btn btn-link">Missing Purchase</a>
                @if ($missing_charge_count > 0)
                    <a href="{{url('order')}}?missing=charge" class="btn btn-link">Missing Charge ({{ $missing_charge_count }})</a>

                @endif
                <a href="{{url('order')}}?missing=scan" class="btn btn-link">Missing Scan</a>
                @if ($missing_processed_at_count > 0)
                    <a href="{{url('order')}}?missing=processed_at" class="btn btn-link">Missing Invoiced At ({{ $missing_processed_at_count }})</a>

                @endif
                <a href="{{url('order')}}?transaction=1" class="btn btn-link">Transaction</a>
            </div>
            <div class="d-flex">

                <input type="text" class="form-control pd-x-20" name="last_order" placeholder="Last Order (Optional)" value="" form="picklist" style="width: 170px;">
                <button class="btn btn-sm btn-secondary pd-x-20 " type="submit" form="picklist" name="order" value="1">Order List</button>
                @if (session('user')->hasPermission('order_picklist'))
                <button class="btn btn-sm btn-secondary pd-x-20 " type="submit" form="picklist" name="picklist" value="1">Pick List</button>

                @endif
                <button class="btn btn-sm btn-secondary pd-x-20 " type="submit" form="picklist" name="ordersheet" value="1">Order Sheet</button>
                @if (session('user')->hasPermission('send_bulk_invoice'))
                <button class="btn btn-sm btn-primary pd-x-20 " type="submit" form="search" name="bulk_invoice" value="1">Send Bulk Invoice Email</button>
                @endif
            </div>
        </div>
        <form id="picklist" method="POST" target="_blank" action="{{url('export_order')}}">
            @csrf
            <input type="hidden" name="start_date" value="{{ Request::get('start_date') }}">
            <input type="hidden" name="start_time" value="{{ Request::get('start_time') }}">
            <input type="hidden" name="end_date" value="{{ Request::get('end_date') }}">
            <input type="hidden" name="end_time" value="{{ Request::get('end_time') }}">
            <input type="hidden" name="status" value="{{ Request::get('status') }}">
            <input type="hidden" name="adm" value="{{ Request::get('adm') }}">
            <input type="hidden" name="order_id" value="{{ Request::get('order_id') }}">
            <input type="hidden" name="sku" value="{{ Request::get('sku') }}">
            <input type="hidden" name="imei" value="{{ Request::get('imei') }}">
            <input type="hidden" name="tracking_number" value="{{ Request::get('tracking_number') }}">
            <input type="hidden" name="page" value="{{ Request::get('page') }}">
            <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
            @if (Request::get('exclude_topup'))
                @foreach (Request::get('exclude_topup') as $topup)
                    <input type="hidden" name="exclude_topup[]" value="{{ $topup }}">
                @endforeach

            @endif
            @if (Request::get('care') == 1)
                <input type="hidden" name="care" value="{{ Request::get('care') }}">
            @endif
            @if (Request::get('missing'))
                <input type="hidden" name="missing" value="{{ Request::get('missing') }}">
            @endif
            @if (Request::get('with_stock'))
                <input type="hidden" name="with_stock" value="{{ Request::get('with_stock') }}">
            @endif
            @if (Request::get('transaction'))
                <input type="hidden" name="transaction" value="{{ Request::get('transaction') }}">
            @endif

        </form>
        <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Orders</h4></center>
            </div>
        </div>
        <br>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
                <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            <br>
            @php
            session()->forget('success');
            @endphp
            @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
                <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            <br>
            @php
            $error = session('error');
            session()->forget('error');
            @endphp
            <script>
                alert("{{$error}}");
            </script>
        @endif
        <script>
            function checkAll() {
                var checkboxes = document.querySelectorAll('input[type="checkbox"]');
                var checkAllCheckbox = document.getElementById('checkAll');

                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = checkAllCheckbox.checked;
                });
            }

            // document.addEventListener('DOMContentLoaded', function() {
            //         var input = document.getElementById('sku_input');
            //         input.focus();
            //         input.select();
            //     });
        </script>
        <livewire:order.orders-table
            :filters="request()->all()"
            :testers="$testers"
            :storages="$storages"
            :colors="$colors"
            :grades="$grades"
            :admins="$admins"
            :currencies="$currencies"
            :order-statuses="$order_statuses"
        />
        <div class="modal" id="tracking_model">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h5 class="modal-title mg-b-5">Update Order Tracking</h5>
                        <hr>
                        <form action="{{ url('order/tracking') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="tracking[id]" type="text" id="order_reference" disabled>
                            </div>
                            <div class="form-group">
                                <label for="">New Tracking Number</label>
                                <input class="form-control" placeholder="input New Tracking Number" id="tracking_number" name="tracking[number]" type="text" min="16" max="17" required>
                            </div>
                            <div class="form-group">
                                <label for="">Reason</label>
                                <textarea class="form-control" name="tracking[reason]">Address changed from</textarea>
                            </div>
                            <input type="hidden" id="order_id" name="tracking[order_id]" value="">

                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal" id="correction_model">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h5 class="modal-title mg-b-5">Update Order <strong id="override"></strong></h5>
                        <hr>
                        <form action="{{ url('order/correction') }}" method="POST" onsubmit="if ($('#correction_imei').val() == ''){ if (confirm('Remove IMEI from Order')){return true;}else{event.stopPropagation(); event.preventDefault();};};">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="correction[id]" type="text" id="order_reference" disabled>
                            </div>
                            <div class="form-group">
                                <label for="">Tester</label>
                                <input class="form-control" placeholder="input Tester Initial" name="correction[tester]" type="text" list="tester_list">
                            </div>
                            <div class="form-group">
                                <label for="">IMEI / Serial Number</label>
                                <input class="form-control" placeholder="input IMEI / Serial Number" id="correction_imei" name="correction[imei]" type="text">
                            </div>
                            <div class="form-group">
                                <label for="">Reason</label>
                                <textarea class="form-control" name="correction[reason]">Wrong Dispatch</textarea>
                            </div>
                            <input type="hidden" id="item_id" name="correction[item_id]" value="">

                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal" id="replacement_model">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h3 class="modal-title mg-b-5">Update Order</h3>
                        <hr>
                        @php
                            if(session('user')->role_id == 4){
                                $replacement_url = url('order/replacement');
                            }else {
                                $replacement_url = url('order/replacement/1');
                            }
                        @endphp
                        <form action="{{ $replacement_url }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="replacement[id]" type="text" id="order_reference" readonly>
                            </div>
                            <h4>Replace</h4>
                            <div class="form-group bs_hide">
                                <label for="">Move to</label>
                                <select name="replacement[grade]" id="move_grade" class="form-control form-select" required>
                                    <option value="">Move to</option>
                                    @foreach ($grades as $id=>$grade)
                                        <option value="{{ $id }}">{{ $grade }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group bs_hide">
                                <label for="">Reason</label>
                                <textarea class="form-control" name="replacement[reason]"></textarea>
                            </div>
                            <h4>With</h4>
                            <div class="form-group">
                                <label for="">Tester</label>
                                <input class="form-control" placeholder="input Tester Initial" name="replacement[tester]" list="tester_list" type="text">
                            </div>
                            <div class="form-group">
                                <label for="">IMEI / Serial Number</label>
                                <input class="form-control" placeholder="input IMEI / Serial Number" name="replacement[imei]" type="text" required>
                            </div>
                            <input type="hidden" id="item_id" name="replacement[item_id]" value="">

                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        {{-- @if (session('user_id') == 1)
            @dd($orders)

        @endif --}}
    @endsection

    @section('scripts')

    <script>

        function moveToNextInput(currentInput, prefix, moveUp = false) {
            const inputs = document.querySelectorAll(`input[id^="${prefix}"]`);
            const currentIndex = Array.from(inputs).indexOf(currentInput);
            if (currentIndex !== -1) {
                if (moveUp && currentIndex > 0) {
                    inputs[currentIndex - 1].focus();
                } else if (!moveUp && currentIndex < inputs.length - 1) {
                    inputs[currentIndex + 1].focus();
                }
            }
        }

        function fallbackCopyToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.top = '-1000px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            try {
                document.execCommand('copy');
            } catch (error) {
                console.error('Clipboard fallback error', error);
            }
            document.body.removeChild(textarea);
        }

        async function copyTextContent(text) {
            if (!text) {
                return;
            }

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    fallbackCopyToClipboard(text);
                }
                const preview = text.length > 200 ? text.slice(0, 200) + '...' : text;
                alert('IMEI numbers copied to clipboard:\n' + preview);
            } catch (error) {
                console.error('Clipboard copy failed', error);
                fallbackCopyToClipboard(text);
                alert('IMEI numbers copied to clipboard.');
            }
        }

        function bindCopyHandlers() {
            document.querySelectorAll('.copy-imei-trigger').forEach((element) => {
                if (element.dataset.copyBound === '1') {
                    return;
                }
                element.dataset.copyBound = '1';
                element.addEventListener('click', () => {
                    const payload = element.getAttribute('data-copy-text') || element.textContent.trim();
                    copyTextContent(payload);
                });
            });
        }

        function bindRefreshTrigger() {
            const trigger = document.querySelector('.orders-refresh-trigger:not([data-triggered])');
            if (!trigger) {
                return;
            }

            trigger.dataset.triggered = '1';
            const refreshUrl = trigger.getAttribute('data-refresh-url');
            if (refreshUrl) {
                window.location.href = refreshUrl;
            }
        }

        function bindOpenAllImei() {
            const link = document.getElementById('open_all_imei');
            if (!link || link.dataset.bound === '1') {
                return;
            }

            link.dataset.bound = '1';
            link.addEventListener('click', (event) => {
                event.preventDefault();

                const imeiListRaw = link.getAttribute('data-imei-list');
                const imeiBase = link.getAttribute('data-imei-base');

                if (!imeiListRaw || !imeiBase) {
                    return;
                }

                let imeiList = [];
                try {
                    imeiList = JSON.parse(imeiListRaw) || [];
                } catch (error) {
                    console.error('Unable to parse IMEI list', error);
                }

                imeiList.filter(Boolean).forEach((imei) => {
                    const target = `${imeiBase}?imei=${encodeURIComponent(imei)}`;
                    window.open(target, '_blank');
                });
            });
        }

        function focusRelevantInput(detail = {}) {
            const { invoiceMode, packingMode, lastTesterId, lastImeiId } = detail;
            const focusById = (id) => {
                if (!id) {
                    return false;
                }
                const el = document.getElementById(id);
                if (!el) {
                    return false;
                }
                el.focus();
                if (typeof el.select === 'function') {
                    el.select();
                }
                return true;
            };

            if (invoiceMode && focusById(lastTesterId)) {
                return;
            }

            if (invoiceMode && !lastTesterId) {
                const prevLink = document.querySelector('[rel="prev"]');
                if (prevLink) {
                    prevLink.click();
                    return;
                }
            }

            if (packingMode && focusById(lastImeiId)) {
                return;
            }

            if (packingMode && !lastImeiId) {
                const prevLink = document.querySelector('[rel="prev"]');
                if (prevLink) {
                    prevLink.click();
                    return;
                }
            }

            const skuInput = document.getElementById('sku_input');
            if (skuInput && skuInput.value === '') {
                skuInput.focus();
            }
        }

        function initializeOrdersInteractions(detail = {}) {
            bindCopyHandlers();
            bindRefreshTrigger();
            bindOpenAllImei();
            focusRelevantInput(detail);
        }

        document.addEventListener('DOMContentLoaded', () => {
            initializeOrdersInteractions();
        });

        window.addEventListener('orders-table-updated', (event) => {
            initializeOrdersInteractions(event.detail || {});
        });

        $('#tracking_model').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var reference = button.data('bs-reference') // Extract info from data-* attributesv
            var order = button.data('bs-order') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var modal = $(this)
            modal.find('.modal-body #order_reference').val(reference)
            modal.find('.modal-body #order_id').val(order)
            })
        $('#correction_model').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var reference = button.data('bs-reference') // Extract info from data-* attributesv
            var item = button.data('bs-item') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var override = button.data('bs-override') // Extract info from data-* attributes
            var modal = $(this)
            if(override){
                modal.find('.modal-title #override').text('(Override)')
                // change form action
                modal.find('form').attr('action', "{{ url('order/correction/true') }}")
            }
            modal.find('.modal-body #order_reference').val(reference)
            modal.find('.modal-body #item_id').val(item)
            })
        $('#replacement_model').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var reference = button.data('bs-reference') // Extract info from data-* attributesv
            var retun = button.data('bs-return') // Extract info from data-* attributesv
            var item = button.data('bs-item') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var modal = $(this)
            if(retun == 1){
                modal.find('.modal-body .bs_hide').addClass('d-none')
                modal.find('.modal-body #move_grade').removeAttr('required')
            }

            modal.find('.modal-body #order_reference').val(reference)
            modal.find('.modal-body #item_id').val(item)
            })

                $('.select2').select2({
                    placeholder: "Exclude Topups",
                });

        // function get_customer_previous_orders(customer_id, order_id){
        //     let url = "{{ url('order/get_b2c_orders_by_customer_json') }}/".concat(customer_id).concat('/').concat(order_id);
        //     $.ajax({
        //         url: url,
        //         type: 'GET',
        //         success: function(data){
        //             console.log(data)
        //             return data;
        //         }
        //     })
        // }
    </script>


    @endsection
