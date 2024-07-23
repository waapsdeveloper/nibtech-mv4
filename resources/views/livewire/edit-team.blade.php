@extends('layouts.app')

    @section('styles')

		<!--- Internal Select2 css-->
		<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet">

    @endsection

    @section('content')

					<!-- breadcrumb -->
					<div class="breadcrumb-header justify-content-between">
						<div class="left-content">
						  <span class="main-content-title mg-b-0 mg-b-lg-1">EDIT MEMBER</span>
						</div>
						<div class="justify-content-center mt-2">
							<ol class="breadcrumb">
								<li class="breadcrumb-item tx-15"><a href="{{url('team')}}">Team</a></li>
								<li class="breadcrumb-item active" aria-current="page">Edit Member</li>
							</ol>
						</div>
					</div>
					<!-- /breadcrumb -->
                    <hr style="border-bottom: 1px solid #000">
					<!-- row -->
                    <div class="row">
                        <div class="col-lg-9 col-md-9">
                            <form action="{{url('update-member')}}/{{$member->id}}" method="POST">
                                @csrf
                                <div class="card">
                                    <div class="card-body">
                                        <div class="main-content-label mg-b-5">
                                           <img src="{{asset('assets/img/brand/favicon1.png')}}" height="50" width="50" alt="">
                                        </div>
                                        <p class="mg-b-20">Edit a member of your team.</p>
                                        <div class="pd-30 pd-sm-20">
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">Parent</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">

                                                    <select class="form-select" name="parent">
                                                        @foreach ($parents as $parent)
                                                            <option value="{{ $parent->id }}" @if($parent->id == $member->parent_id) selected @endif>{{ $parent->first_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">Role</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">

                                                    <select class="form-select" name="role">
                                                        @foreach ($roles as $role)
                                                            <option value="{{ $role->id }}" @if($role->id == $member->role_id) selected @endif>{{ $role->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">Username</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter member's username" name="username" value="{{$member->username}}" type="text">
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">First name</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter member's firstname" name="fname" value="{{$member->first_name}}" type="text">
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">Last name</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter member's lastname" name="lname" value="{{$member->last_name}}" type="text">
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">Email</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter member's email" name="email" value="{{$member->email}}" type="email">
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">Password</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter password for member" name="password" type="password">
                                                </div>
                                            </div>
                                            <button class="btn btn-primary pd-x-30 mg-r-5 mg-t-5 float-end" >Update</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title mb-1">Role - Permissions</h4>
                                    <p>Here, you can chage permission for selected role</p>
                                </div>
                                <div class="card-body">
                                    <div>
                                        <label for="role">Select Role:</label>
                                        <select id="role" class="form-select" onchange="fetchPermissions()">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}" @if($role->id == $member->role_id) selected @endif>{{ $role->name }}</option>
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
                                        function togglePermission(roleId, permissionId, isChecked) {
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
                                                // console.log(data); // You can handle the response as per your requirement
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
                                                        var isChecked = data.includes('{{ $permission->name }}') ? 'checked' : '';
                                                        permissionsDiv.innerHTML += `
                                                            <div class="form-check form-switch ms-4">
                                                                <input type="checkbox" value="{{ $permission->id }}" name="permission[]" class="form-check-input" ${isChecked}
                                                                    onclick="togglePermission(${roleId}, {{ $permission->id }}, this.checked)">
                                                                <label class="form-check-label" for="permission">{{ $permission->name }}</label>
                                                            </div>`;
                                                    @endforeach
                                                });
                                        }
                                    </script>

                                </div>
                            </div>
                        </div>
                    </div>
					<!-- /row -->

                    @endsection
    @section('scripts')

		<!-- Form-layouts js -->
		<script src="{{asset('assets/js/form-layouts.js')}}"></script>

		<!--Internal  Select2 js -->
		<script src="{{asset('assets/plugins/select2/js/select2.min.js')}}"></script>

    @endsection
