<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;

$roles = [
  'executive',
  'dic_approver',
  'hcd_div_head',
  'hcd_dept_head',
  'pic_recruitment',
];

foreach ($roles as $roleName) {
  Role::firstOrCreate(['name' => $roleName]);
}

$assignments = [
  'rimba.kusumadilaga@pmp.local' => ['division_head', 'executive'],
  'teguh.patmuryanto@pmp.local' => ['division_head', 'executive'],
  'dic.ms.engineering.scm@pmp.local' => ['division_head', 'dic_approver'],
  'division.fa.hcga@pmp.local' => ['division_head', 'hcd_div_head'],
  'head-hcgaesrit@airsys.com' => ['kepala departemen', 'hcd_dept_head'],
  'hc2@pmp.com' => ['team_hc_2', 'pic_recruitment'],
];

foreach ($assignments as $email => $requiredRoles) {
  $user = User::where('email', $email)->first();

  if (!$user) {
    echo "SKIP: {$email} (user not found)\n";
    continue;
  }

  $existingRoles = $user->getRoleNames()->toArray();
  $mergedRoles = array_values(array_unique(array_merge($existingRoles, $requiredRoles)));

  $user->syncRoles($mergedRoles);

  echo "OK: {$email} | roles=" . implode(',', $mergedRoles) . "\n";
}
