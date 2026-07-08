<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$email = 'superadmin@gmail.com';
$defaultPassword = env('DEFAULT_DIVISION_ACCOUNT_PASSWORD', 'password');

$allDepartmentIds = Department::query()
  ->pluck('id')
  ->map(fn($id) => (int) $id)
  ->values()
  ->all();

$user = User::where('email', $email)->first();

if (!$user) {
  $user = new User();
  $user->email = $email;
  $user->password = Hash::make($defaultPassword);
  $user->nrp = 'TEST-SUPER-001';
}

$user->name = 'Super Admin Testing';
$user->approval_display_name = 'Super Admin Testing';
$user->division_name = 'Testing';
$user->department_id = null;
$user->accessible_department_ids = $allDepartmentIds;
$user->status = true;
$user->email_verified_at = now();
$user->save();

$allRoleNames = Role::query()
  ->pluck('name')
  ->filter(fn($name) => is_string($name) && trim($name) !== '' && strtolower($name) !== 'admin')
  ->values()
  ->all();

$user->syncRoles($allRoleNames);

$allPermissionNames = Permission::query()
  ->pluck('name')
  ->filter(fn($name) => is_string($name) && trim($name) !== '')
  ->values()
  ->all();

$user->syncPermissions($allPermissionNames);

echo sprintf(
  "OK: %s | id=%d | roles=%d | permissions=%d | departments=%d\n",
  $user->email,
  $user->id,
  count($allRoleNames),
  count($allPermissionNames),
  count($allDepartmentIds)
);
