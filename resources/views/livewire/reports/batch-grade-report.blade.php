    <div class="card">
        <div class="card-header mb-0 d-flex justify-content-between">
            <div class="mb-0">
                <h4 class="card-title mb-0">Batch Grade Reports</h4>
            </div>
        </div>
        <div class="card-body mt-0 w-100 overflow-scroll">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th><small><b>No</b></small></th>
                        <th><small><b>Batch</b></small></th>
                        <th><small><b>Reference</b></small></th>
                        <th><small><b>Vendor</b></small></th>
                        <th><small><b>Total</b></small></th>
                        @foreach ($grades as $id=>$grade)
                            <th title="{{ $grade }}"><small><b>{{ $grade }}</b></small></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php
                        $i = 0;
                    @endphp
                    @foreach ($batch_grade_reports->groupBy('order_id') as $orderReports)
                        @php
                            $order = $orderReports->first();
                            $total = $orderReports->sum('quantity');
                        @endphp
                        <tr>
                            <td>{{ $i += 1 }}</td>
                            <td>{{ $order->reference_id }}</td>
                            <td>{{ $order->reference }}</td>
                            <td>{{ $order->vendor }}</td>
                            <td>

                                <div class="btn-group p-1" role="group">
                                    {{-- <button type="button" class="btn-sm btn-link dropdown-toggle" id="batch_report" data-bs-toggle="dropdown" aria-expanded="false">

                                    </button> --}}
                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">{{ $total }}</a>
                                    <ul class="dropdown-menu">
                                        <li class="dropdown-item"><a href="{{ url('report/export_batch')}}/{{$order->order_id}}?type=1" onclick="if (confirm('Download Batch Grade Report?')){return true;}else{event.stopPropagation(); event.preventDefault();};"> Current Grade Report </a></li>
                                        <li class="dropdown-item"><a href="{{ url('report/export_batch')}}/{{$order->order_id}}?type=2" onclick="if (confirm('Download Batch Initial Grade Report?')){return true;}else{event.stopPropagation(); event.preventDefault();};"> Initial Report <small>May not contain all devices</small> </a></li>
                                    </ul>
                                </div>
                            </td>
                            @foreach ($grades as $g_id => $grade)
                                @php
                                    $gradeReport = $orderReports->firstWhere('grade', $g_id);
                                @endphp
                                <td title="{{ $grade }}">{{ $gradeReport ? ($gradeReport->quantity." (".amount_formatter($gradeReport->quantity/$total * 100,1) .'%)' ) : '-' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
