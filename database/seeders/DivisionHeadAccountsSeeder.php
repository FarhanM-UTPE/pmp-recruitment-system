<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DivisionHeadAccountsSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $defaultPassword = env('DEFAULT_DIVISION_ACCOUNT_PASSWORD', 'password');

    $accounts = [
      [
        'name' => 'Marketing & Sales Division Head',
        'division_name' => 'Marketing & Sales',
        'email' => 'division.marketing.sales@pmp.local',
        'nrp' => 'DIV-001',
        'accessible_department_ids' => [12, 13],
      ],
      [
        'name' => 'Engineering & SCM Division Head',
        'division_name' => 'Engineering & SCM',
        'email' => 'division.engineering.scm@pmp.local',
        'nrp' => 'DIV-002',
        'accessible_department_ids' => [14, 15, 16, 17],
      ],
      [
        'name' => 'Batam Operation Division Head',
        'division_name' => 'Batam Operation',
        'email' => 'division.batam.operation@pmp.local',
        'nrp' => 'DIV-003',
        'accessible_department_ids' => [18, 19],
      ],
      [
        'name' => 'Banjarmasin Operation Division Head',
        'division_name' => 'Banjarmasin Operation',
        'email' => 'division.banjarmasin.operation@pmp.local',
        'nrp' => 'DIV-004',
        'accessible_department_ids' => [20, 21, 22, 23],
      ],
      [
        'name' => 'FA & HCGA Division',
        'division_name' => 'FA & HCGA',
        'email' => 'division.fa.hcga@pmp.local',
        'nrp' => 'DIV-005',
        'accessible_department_ids' => [24, 25],
      ],
      [
        'name' => 'Marketing, Sales, Engineering & SCM DIC',
        'division_name' => 'Marketing, Sales, Engineering & SCM',
        'email' => 'dic.ms.engineering.scm@pmp.local',
        'nrp' => 'DIV-006',
        'accessible_department_ids' => [12, 13, 14, 15, 16, 17],
      ],
      [
        'name' => 'Operation DIC',
        'division_name' => 'Operation',
        'email' => 'dic.operation@pmp.local',
        'nrp' => 'DIV-007',
        'accessible_department_ids' => [18, 19, 20, 21, 22, 23],
      ],
      [
        'name' => 'FA & HCGA DIC',
        'division_name' => 'FA & HCGA',
        'email' => 'dic.fa.hcga@pmp.local',
        'nrp' => 'DIV-008',
        'accessible_department_ids' => [24, 25],
      ],
    ];

    foreach ($accounts as $account) {
      $user = User::updateOrCreate(
        ['email' => $account['email']],
        [
          'name' => $account['name'],
          'division_name' => $account['division_name'],
          'nrp' => $account['nrp'],
          'department_id' => null,
          'accessible_department_ids' => $account['accessible_department_ids'],
          'status' => true,
          'password' => Hash::make($defaultPassword),
        ]
      );

      $user->syncRoles(['division_head']);
    }

    $this->command->info('Division head accounts seeded successfully (8 accounts).');
  }
}
