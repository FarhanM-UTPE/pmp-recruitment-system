<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

$requiredRoles = [
  'division_head',
  'executive',
  'dic_approver',
];

foreach ($requiredRoles as $roleName) {
  Role::firstOrCreate(['name' => $roleName]);
}

$allDepartmentIds = Department::query()
  ->pluck('id')
  ->map(fn($id) => (int) $id)
  ->values()
  ->all();

$defaultPassword = env('DEFAULT_DIVISION_ACCOUNT_PASSWORD', 'password');
$email = 'dic.ms.engineering.scm@pmp.local';

$user = User::where('email', $email)->first();

if (!$user) {
  $user = new User();
  $user->email = $email;
  $user->password = Hash::make($defaultPassword);
  $user->nrp = 'EXEC-003';
}

$user->name = 'Isnaryanto Wibowo';
$user->approval_display_name = 'Isnaryanto Wibowo';
$user->division_name = 'Marketing, Sales, Engineering & SCM';
$user->department_id = null;
$user->accessible_department_ids = $allDepartmentIds;
$user->status = true;
$user->email_verified_at = now();
$user->save();

$existingRoles = $user->getRoleNames()->toArray();
$mergedRoles = array_values(array_unique(array_merge($existingRoles, $requiredRoles)));
$user->syncRoles($mergedRoles);

echo sprintf(
  "OK: %s | id=%d | roles=%s | division=%s | departments=%d\n",
  $user->email,
  $user->id,
  implode(',', $mergedRoles),
  (string) $user->division_name,
  count($allDepartmentIds)
);
