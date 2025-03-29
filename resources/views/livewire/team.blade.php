@extends('layouts.app')

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
    </style>
    @endsection
<br>
    @section('content')
        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">{{ __('locale.Team') }}</span>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                                {{ __('locale.Team') }}
                        </li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center>
                    <h4>
                        Admin Team
                    </h4>
                </center>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-12" style="text-align: right">
                <a href="{{url('add-member')}}" class="btn btn-success float-right"><i class="mdi mdi-plus"></i> {{ __('locale.Add Member') }}</a>
            </div>
        </div>
        <br>
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
                <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
        @endif
        <br>
        <div class="row">
            <div @if (session('user')->hasPermission('change_role_permissions'))
                class="col-xl-9"
            @else
                class="col-xl-12"
            @endif
            >
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">{{ __('locale.Team') }} {{ __('locale.Of') }} {{session('our_id')}}</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>{{ __('locale.S.No') }}</b></small></th>
                                        <th><small><b>{{ __('locale.First Name') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Last Name') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Email') }}</b></small></th>
                                        <th><small><b>Username</b></small></th>
                                        <th colspan="2"><center><small><b>{{ __('locale.Action') }}</b></small></center></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = $admin_team->firstItem()-1;
                                    @endphp
                                    @foreach ($admin_team as $item)
                                    @php
                                        $i++;
                                    @endphp
                                        <tr>
                                            <td>{{$i}}</td>
                                            <td>{{$item->first_name}}</td>
                                            <td>{{$item->last_name}}</td>
                                            <td>{{$item->email}}</td>
                                            <td>{{$item->username}}</td>
                                            <td><center><a href="edit-member/{{$item->id}}" class="text text-success w-100 vh-100">{{ __('locale.Edit') }}</a></center></td>
                                            <td>
                                                <center>
                                                    @if ($item->status == 1)
                                                    <a href="update-status/{{$item->id}}" class="text text-success w-100 vh-100" title="Click to Deactivate">{{ __('locale.Active') }}</a>
                                                    @else
                                                    <a href="update-status/{{$item->id}}" class="text text-success w-100 vh-100" title="Click to Activate">{{ __('locale.Inactive') }}</a>
                                                    @endif
                                                </center>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <br>
                            {{$admin_team->onEachSide(1)->links()}} {{ __('locale.From') }} {{$admin_team->firstItem()}} {{ __('locale.To') }} {{$admin_team->lastItem()}} {{ __('locale.Out Of') }} {{$admin_team->total()}}
                        </div>
                    </div>
                </div>
            </div>
            @if (session('user')->hasPermission('change_role_permissions'))
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-1">Role - Permissions</h4>
                        <p>Here, you can chage permission for selected Role</p>
                    </div>
                    <div class="card-body">
                        <div>
                            <label for="role">Select Role:</label>
                            <select id="role" class="form-select" onchange="fetchPermissions()">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @if($role->id > session('user')->role_id) selected @endif>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <br>
                        <div id="permissions">
                            <!-- Permissions will be displayed here -->
                        </div>
                        <script>

                            document.addEventListener('DOMContentLoaded', function() {
                                fetchPermissions()
                            })
                            // function togglePermission(roleId, permissionId, isChecked) {
                            //     // Send AJAX request to server to create or delete role permission
                            //     fetch(`{{ url('toggle_role_permission') }}/${roleId}/${permissionId}/${isChecked}`, { method: 'POST' })
                            //         // .then(response => response.json())
                            //         .then(data => {
                            //             // Update UI based on server response
                            //             console.log(data); // You can handle the response as per your requirement
                            //         })
                            //         .catch(error => {
                            //             console.error('Error:', error);
                            //         });
                            // }
                            function togglePermission(roleId,permissionId, isChecked) {
                            // Get the CSRF token from the meta tag in your HTML
                            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                            // Send AJAX request to server to create or delete role permission
                            fetch(`{{ url('toggle_role_permission') }}/${roleId}/${permissionId}/${isChecked}`, {
                                method: 'POST',
                                headers: {
                                    // 'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken // Include the CSRF token in the headers
                                }
                            })
                                // .then(response => response.json()) // Parse response as JSON
                                .then(data => {
                                    // Update UI based on server response
                                    console.log(data); // You can handle the response as per your requirement
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                });
                        }


                            function fetchPermissions() {
                                var roleId = document.getElementById('role').value;
                                fetch(`{{ url('get_permissions') }}/${roleId}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        var permissionsDiv = document.getElementById('permissions');
                                        permissionsDiv.innerHTML = '';
                                        @foreach ($permissions as $permission)
                                            var isChecked = data.permissions.includes('{{ $permission->name }}') ? 'checked' : '';
                                            permissionsDiv.innerHTML += `
                                                <div class="form-check form-switch ms-4">
                                                    <input type="checkbox" value="{{ $permission->id }}" name="permission[]" class="form-check-input" ${isChecked} ${isDisabled} onclick="togglePermission(${roleId},{{ $permission->id }}, this.checked)">
                                                    <label class="form-check-label" for="permission">{{ $permission->name }}</label>
                                                </div>`;
                                        @endforeach
                                    });
                            }
                        </script>

                    </div>
                </div>
            </div>
            @endif
        </div>
        @php
            session()->forget('success');
        @endphp
    @endsection

    @section('scripts')

		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

    @endsection
