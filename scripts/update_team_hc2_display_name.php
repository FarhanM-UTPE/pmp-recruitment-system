<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::role('team_hc_2')->first();

if (!$user) {
  $user = User::where('name', 'Team HC 2')->first();
}

if (!$user) {
  echo "NOT_FOUND: team_hc_2 user\n";
  exit(1);
}

$user->update([
  'name' => 'Team HC 2',
  'approval_display_name' => 'Nanda Abdi Firdausi',
]);

echo "UPDATED: id={$user->id} | name={$user->name} | approval_display_name={$user->approval_display_name}\n";
