<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

Role::firstOrCreate(['name' => 'executive']);

$allDepartmentIds = Department::query()
  ->pluck('id')
  ->map(fn($id) => (int) $id)
  ->values()
  ->all();

$defaultPassword = env('DEFAULT_DIVISION_ACCOUNT_PASSWORD', 'password');

$accounts = [
  [
    'name' => 'Rimba Kusumadilaga',
    'approval_display_name' => 'Rimba Kusumadilaga',
    'division_name' => 'Executive',
    'email' => 'rimba.kusumadilaga@pmp.local',
    'nrp' => 'EXEC-001',
  ],
  [
    'name' => 'Teguh Patmuryanto',
    'approval_display_name' => 'Teguh Patmuryanto',
    'division_name' => 'Executive',
    'email' => 'teguh.patmuryanto@pmp.local',
    'nrp' => 'EXEC-002',
  ],
];

foreach ($accounts as $account) {
  $user = User::updateOrCreate(
    ['email' => $account['email']],
    [
      'name' => $account['name'],
      'approval_display_name' => $account['approval_display_name'],
      'division_name' => $account['division_name'],
      'nrp' => $account['nrp'],
      'department_id' => null,
      'accessible_department_ids' => $allDepartmentIds,
      'status' => true,
      'password' => Hash::make($defaultPassword),
      'email_verified_at' => now(),
    ]
  );

  $user->syncRoles(['division_head', 'executive']);

  echo sprintf(
    "OK: %s | roles=division_head,executive | departments=%d\n",
    $user->email,
    count($allDepartmentIds)
  );
}
