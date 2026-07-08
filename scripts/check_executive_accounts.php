<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$emails = [
  'rimba.kusumadilaga@pmp.local',
  'teguh.patmuryanto@pmp.local',
  'dic.rimba.kusumadilaga@pmp.local',
  'dic.teguh.patmuryanto@pmp.local',
];

foreach ($emails as $email) {
  $user = User::where('email', $email)->first();

  if (!$user) {
    echo "NOT_FOUND: {$email}\n";
    continue;
  }

  $role = $user->getRoleNames()->first() ?? '-';
  $division = $user->division_name ?? '-';

  echo "FOUND: {$email} | id={$user->id} | role={$role} | division={$division}\n";
}
