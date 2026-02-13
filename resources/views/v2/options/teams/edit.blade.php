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
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{url('v2/options/teams')}}">Team</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Member</li>
            </ol>
        </div>
    </div>
    <!-- /breadcrumb -->
    <hr style="border-bottom: 1px solid #000">
    <!-- row -->
    <form action="{{url('v2/options/teams/update-member')}}/{{$member->id}}" method="POST">
        @csrf
        <div class="card">
            <div class="card-body">
                <div class="main-content-label mg-b-5">
                   <img src="{{asset('assets/img/brand').'/'.env('APP_ICON')}}" height="50" width="50" alt="">
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
                            <input class="form-control" placeholder="Enter password for member (leave blank to keep current)" name="password" type="password">
                        </div>
                    </div>
                    <div class="row row-xs align-items-center mg-b-20">
                        <div class="col-md-4">
                            <label class="form-label mg-b-0">Customer Restriction</label>
                        </div>
                        <div class="col-md-8 mg-t-5 mg-md-t-0">
                            <select class="form-select select2" multiple name="customer_restriction[]">
                                @foreach ($vendors as $customer)
                                    <option value="{{ $customer->id }}" @if(in_array($customer->id, $customer_restrictions)) selected @endif>{{ $customer->company }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if (session('user')->hasPermission('change_permission') || session('user_id') == 1)
                    <div class="row row-xs align-items-center mg-b-20">
                        <div class="col-md-4">
                            <label class="form-label mg-b-0">Dynamic IP Access</label>
                        </div>
                        <div class="col-md-8 mg-t-5 mg-md-t-0">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="allow_unknown_ip" onchange="toggleAllowUnknownIP({{ $member->id }}, this.checked)">
                                <label class="form-check-label" for="allow_unknown_ip">
                                    Allow access from any IP address
                                </label>
                            </div>
                            <small class="text-muted">When enabled, this user can access the system from any IP address without restrictions.</small>
                        </div>
                    </div>
                    @endif

                    <button class="btn btn-primary pd-x-30 mg-r-5 mg-t-5 float-end" >Update</button>
                </div>
            </div>
        </div>
    </form>

    @if (session('user')->hasPermission('change_permission') || session('user_id') == 1)
    <!-- User Permissions Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-1">User Permissions</h4>
                    <p class="mb-0">Manage individual permissions for {{ $member->first_name }} {{ $member->last_name }}.
                    <small class="text-muted">Note: Permissions from role are shown but cannot be changed here. Use Role Permissions to modify role-based permissions.</small></p>
                </div>
                <div class="card-body">
                    <div id="user-permissions">
                        <!-- Permissions will be displayed here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
                </div>
            </div>
        </div>
    </form>
    <!-- /row -->
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: 'Select Customer Restriction',
                allowClear: true
            });

            // Check if user has allow_unknown_ip permission on page load
            checkAllowUnknownIP({{ $member->id }});

            // Load user permissions
            fetchUserPermissions({{ $member->id }});
        });

        function checkAllowUnknownIP(userId) {
            fetch(`{{ url('v2/options/teams/check-allow-unknown-ip') }}/${userId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('allow_unknown_ip').checked = data.has_permission;
                })
                .catch(error => {
                    console.error('Error checking permission:', error);
                });
        }

        function toggleAllowUnknownIP(userId, isChecked) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(`{{ url('v2/options/teams/toggle-allow-unknown-ip') }}/${userId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    is_checked: isChecked
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show mt-2';
                    alertDiv.innerHTML = `
                        <span>${data.message}</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.card-body').insertBefore(alertDiv, document.querySelector('.card-body').firstChild);

                    // Auto-remove after 3 seconds
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 3000);
                } else {
                    alert('Error: ' + (data.error || 'Failed to update permission'));
                    // Revert checkbox
                    document.getElementById('allow_unknown_ip').checked = !isChecked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating permission. Please try again.');
                // Revert checkbox
                document.getElementById('allow_unknown_ip').checked = !isChecked;
            });
        }

        function fetchUserPermissions(userId) {
            fetch(`{{ url('v2/options/teams/get-user-permissions') }}/${userId}`)
                .then(response => response.json())
                .then(data => {
                    var permissionsDiv = document.getElementById('user-permissions');
                    permissionsDiv.innerHTML = '';

                    @foreach ($permissions as $permission)
                        var hasRolePermission = data.role_permissions.includes('{{ $permission->name }}');
                        var hasUserPermission = data.user_permissions.includes('{{ $permission->name }}');
                        var canAssign = data.current_admin_permissions.includes('{{ $permission->name }}') || {{ session('user_id') == 1 ? 'true' : 'false' }};

                        var isChecked = hasUserPermission ? 'checked' : '';
                        var isDisabled = hasRolePermission ? 'disabled' : '';
                        var roleBadge = hasRolePermission ? '<span class="badge bg-info ms-2">From Role</span>' : '';
                        var disabledTitle = hasRolePermission ? ' title="This permission comes from the user\'s role. Change it in Role Permissions."' : '';

                        if (canAssign || hasRolePermission) {
                            permissionsDiv.innerHTML += `
                                <div class="form-check form-switch ms-4 mb-2">
                                    <input type="checkbox" value="{{ $permission->id }}"
                                           class="form-check-input" ${isChecked} ${isDisabled}
                                           ${disabledTitle}
                                           onchange="toggleUserPermission(${userId}, {{ $permission->id }}, this.checked)">
                                    <label class="form-check-label" for="permission">
                                        {{ $permission->name }}${roleBadge}
                                    </label>
                                </div>`;
                        }
                    @endforeach
                })
                .catch(error => {
                    console.error('Error fetching permissions:', error);
                    document.getElementById('user-permissions').innerHTML =
                        '<div class="alert alert-danger">Error loading permissions. Please refresh the page.</div>';
                });
        }

        function toggleUserPermission(userId, permissionId, isChecked) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(`{{ url('v2/options/teams/toggle-user-permission') }}/${userId}/${permissionId}/${isChecked}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show mt-2';
                    alertDiv.innerHTML = `
                        <span>Permission updated successfully</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    const cardBody = document.querySelector('.card-body');
                    if (cardBody) {
                        cardBody.insertBefore(alertDiv, cardBody.firstChild);
                        setTimeout(() => {
                            alertDiv.remove();
                        }, 3000);
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to update permission'));
                    // Reload permissions to revert checkbox
                    fetchUserPermissions(userId);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating permission. Please try again.');
                // Reload permissions to revert checkbox
                fetchUserPermissions(userId);
            });
        }
    </script>
    <!-- Form-layouts js -->
    <script src="{{asset('assets/js/form-layouts.js')}}"></script>

    <!--Internal  Select2 js -->
    <script src="{{asset('assets/plugins/select2/js/select2.min.js')}}"></script>
@endsection

