# Route Permission System Analysis

## Overview
The application uses a **route name-based permission system** where each route name automatically requires a matching permission.

## How It Works

### 1. **Route Definition**
```php
// routes/web.php
Route::get('team', Team::class)->name('view_team');
```
- Route name: `view_team`
- Permission name required: `view_team` (must match exactly)

### 2. **Global Middleware Protection**
**File:** `app/Http/Middleware/AuthorizeMiddleware.php`

Applied globally to all web routes via `app/Http/Kernel.php`:
```php
'web' => [
    // ... other middleware
    \App\Http\Middleware\AuthorizeMiddleware::class,
    // ...
]
```

**Middleware Logic:**
1. Gets current route name: `Route::currentRouteName()`
2. Checks if user has permission: `$user->hasPermission($currentRoute)`
3. If no permission → Returns 403 error
4. If has permission → Allows access

### 3. **Permission Check Method**
**File:** `app/Models/Admin_model.php` → `hasPermission($permission)`

```php
public function hasPermission($permission)
{
    // Super Admin (user_id = 1) always has access
    if (session('user_id') == 1) {
        return true;
    }
    
    // Check if user's role has the permission
    if ($this->role->permissions->contains('name', $permission)) {
        return true;
    }
    
    // Super Admin role (even if empty permissions) has access
    if ($this->role->permissions->isEmpty() && $this->role->name === 'Super Admin') {
        return true;
    }
    
    // Auto-create permission if it doesn't exist
    $per = Permission_model::firstOrNew(['name'=>$permission]);
    if ($per->id == null) {
        $per->name = $permission;
        $per->save();
    }
    
    return false;
}
```

### 4. **Database Structure**

#### Tables:
- **`permission`**: Stores permission names
  - `id` (primary key)
  - `name` (permission name, e.g., 'view_team')

- **`role`**: Stores roles
  - `id` (primary key)
  - `name` (role name, e.g., 'Super Admin', 'Manager')

- **`role_permission`**: Pivot table linking roles to permissions
  - `id` (primary key)
  - `role_id` (foreign key to role)
  - `permission_id` (foreign key to permission)

- **`admin`**: Stores users/admins
  - `id` (primary key)
  - `role_id` (foreign key to role)
  - `status` (0 = blocked, 1 = active)

#### Relationships:
```
Role → hasMany → Role_permission → belongsTo → Permission
Admin → belongsTo → Role → hasMany → Permissions
```

### 5. **Permission Flow**

```
User accesses /team
    ↓
AuthorizeMiddleware runs
    ↓
Gets route name: 'view_team'
    ↓
Calls: $user->hasPermission('view_team')
    ↓
Checks: $user->role->permissions->contains('name', 'view_team')
    ↓
If found → Allow access
If not found → 403 Forbidden
```

## Key Points

### ✅ **Route Name = Permission Name**
- Route: `Route::get('team')->name('view_team')`
- Permission name must be: `view_team`
- **They must match exactly!**

### ✅ **Auto-Creation of Permissions**
- If a permission doesn't exist in the database, it's automatically created
- This happens on first access attempt
- But user still gets 403 until permission is assigned to their role

### ✅ **Super Admin Bypass**
- User ID 1 always has access (bypasses all checks)
- Super Admin role also has access even if no permissions assigned

### ✅ **Role-Based Access**
- Permissions are assigned to **roles**, not individual users
- Users get permissions through their `role_id`
- To give a user access, assign the permission to their role

## Example: Making `/team` Route Work

### Step 1: Route Already Defined
```php
Route::get('team', Team::class)->name('view_team');
```

### Step 2: Permission Must Exist
- Permission name: `view_team`
- Can be auto-created on first access attempt

### Step 3: Assign Permission to Role
In the Team page (`/team`):
1. Select a role from dropdown
2. Check the `view_team` permission checkbox
3. This creates a record in `role_permission` table:
   ```sql
   INSERT INTO role_permission (role_id, permission_id) 
   VALUES (role_id, permission_id_for_view_team);
   ```

### Step 4: User Must Have That Role
- User's `role_id` must match the role that has the permission
- User gets access automatically through their role

## Team Page Permission Management

**File:** `resources/views/livewire/team.blade.php`

The team page has a "Role - Permissions" section where you can:
1. Select a role
2. See all permissions for that role
3. Toggle permissions on/off
4. This updates the `role_permission` table

**API Endpoints:**
- `GET /get_permissions/{roleId}` - Get permissions for a role
- `POST /toggle_role_permission/{roleId}/{permissionId}/{isChecked}` - Toggle permission for role

## Summary

**For every route to work:**
1. ✅ Route must have a name (e.g., `->name('view_team')`)
2. ✅ Permission with same name must exist (auto-created if missing)
3. ✅ Permission must be assigned to user's role via `role_permission` table
4. ✅ User's `role_id` must match the role with the permission

**The system is automatic:**
- Middleware checks every route automatically
- Permission is auto-created if missing
- Access is granted/denied based on role permissions



