<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\MPPSubmission;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Support\Facades\DB;

$deptHeadEmails = [
  'head-sales-marketing-ship-building@airsys.com',
  'head-sales-ship-repair-product-support@airsys.com',
  'head-procurement-subcont@airsys.com',
  'head-warehouse-invetory-management@airsys.com',
  'head-engineering@airsys.com',
  'head-peqc-ship-building@airsys.com',
  'head-production-control-ship-building@airsys.com',
  'head-production-ship-building@airsys.com',
  'head-production-control-ship-repair@airsys.com',
  'head-production-ship-repair@airsys.com',
  'head-peqc-ship-repair@airsys.com',
  'head-banjarmasin-business-support@airsys.com',
  'head-finance-accounting@airsys.com',
  'head-hcgaesrit@airsys.com',
  'head-strategic-planning@airsys.com',
];

$allowedBoth = [
  'admin@pmp.com',
  'hc1@pmp.com',
  'hc2@pmp.com',
  '1@pmp.com',
  'admin@airsys.com',
  'hc1@airsys.com',
  'hc2@airsys.com',
];

$allowedNewOnly = [
  'head-sales-marketing-ship-building@airsys.com',
  'head-sales-ship-repair-product-support@airsys.com',
  'head-procurement-subcont@airsys.com',
  'head-warehouse-invetory-management@airsys.com',
  'head-engineering@airsys.com',
  'head-peqc-ship-building@airsys.com',
  'head-production-control-ship-building@airsys.com',
  'head-production-ship-building@airsys.com',
  'head-production-control-ship-repair@airsys.com',
  'head-production-ship-repair@airsys.com',
  'head-peqc-ship-repair@airsys.com',
  'head-banjarmasin-business-support@airsys.com',
  'head-finance-accounting@airsys.com',
  'head-hcgaesrit@airsys.com',
  'head-strategic-planning@airsys.com',
];

$blocked = [
  'division.marketing.sales@pmp.local',
  'division.engineering.scm@pmp.local',
  'division.batam.operation@pmp.local',
  'division.banjarmasin.operation@pmp.local',
  'division.fa.hcga@pmp.local',
  'dic.ms.engineering.scm@pmp.local',
  'dic.operation@pmp.local',
  'dic.fa.hcga@pmp.local',
  'superadmin@gmail.com',
  'rimba.kusumadilaga@pmp.local',
  'teguh.patmuryanto@pmp.local',
];

$onlyEmail = null;
$maxVacanciesPerDept = null;
$persistChanges = false;

foreach (array_slice($argv, 1) as $arg) {
  if (str_starts_with($arg, '--only-email=')) {
    $onlyEmail = strtolower(trim(substr($arg, strlen('--only-email='))));
  }

  if (str_starts_with($arg, '--max-vacancies=')) {
    $value = (int) trim(substr($arg, strlen('--max-vacancies=')));
    if ($value > 0) {
      $maxVacanciesPerDept = $value;
    }
  }

  if ($arg === '--persist') {
    $persistChanges = true;
  }
}

function canCreateNewFormByPolicy(string $email, array $allowedBoth, array $allowedNewOnly, array $blocked): bool
{
  $email = strtolower(trim($email));

  if (in_array($email, $blocked, true)) {
    return false;
  }

  return in_array($email, $allowedBoth, true) || in_array($email, $allowedNewOnly, true);
}

function hasDuplicateForScenario(int $year, string $submissionType, ?int $vacancyId, ?string $customJabatanName): bool
{
  $query = MPPSubmission::query()
    ->where('year', $year)
    ->where('submission_type', $submissionType)
    ->whereIn('status', [MPPSubmission::STATUS_SUBMITTED, MPPSubmission::STATUS_APPROVED])
    ->whereNull('deleted_at');

  if ($vacancyId) {
    $query->where(function ($q) use ($vacancyId) {
      $q->where('vacancy_id', $vacancyId)
        ->orWhereHas('vacancies', function ($vacancyQuery) use ($vacancyId) {
          $vacancyQuery->where('vacancies.id', $vacancyId);
        });
    });
  } else {
    $normalizedCustomName = strtolower(trim((string) $customJabatanName));
    $query->whereRaw('LOWER(TRIM(custom_jabatan_name)) = ?', [$normalizedCustomName]);
  }

  return $query->exists();
}

function findFirstActiveUserByEmails(array $emails): ?User
{
  foreach ($emails as $email) {
    $user = User::query()
      ->whereRaw('LOWER(email) = ?', [strtolower($email)])
      ->where('status', 1)
      ->first();

    if ($user instanceof User) {
      return $user;
    }
  }

  return null;
}

function findFirstActiveUserByRoles(array $roles): ?User
{
  foreach ($roles as $role) {
    $user = User::query()
      ->role($role)
      ->active()
      ->orderBy('id')
      ->first();

    if ($user instanceof User) {
      return $user;
    }
  }

  return null;
}

function resolveDivisionHeadApproverForDepartment(int $departmentId): ?User
{
  $candidates = User::query()
    ->role('division_head')
    ->active()
    ->get()
    ->filter(function (User $account) use ($departmentId) {
      return $account->hasDepartmentAccess($departmentId);
    })
    ->values();

  if ($candidates->isEmpty()) {
    return null;
  }

  return $candidates
    ->sort(function (User $a, User $b) {
      $scopeCompare = count($a->getAccessibleDepartmentIds()) <=> count($b->getAccessibleDepartmentIds());
      if ($scopeCompare !== 0) {
        return $scopeCompare;
      }

      return $a->id <=> $b->id;
    })
    ->first();
}

function resolveApprovalSignerName(User $user): string
{
  return trim((string) ($user->approval_display_name ?: $user->name));
}

function buildApprovalsForScenario(User $requester, int $departmentId, bool $isOutsideMpp): array
{
  $approvalRoles = [
    'Diminta Oleh',
    'Diketahui Oleh Dept. Head',
    'Diketahui Oleh Div. Head',
    'Disetujui Oleh Executive 1',
    'Disetujui Oleh Executive 2',
  ];

  if ($isOutsideMpp) {
    $approvalRoles[] = 'Disetujui Oleh Executive 3';
  }

  $approvalRoles[] = 'Disetujui Oleh HCD Div Head';
  $approvalRoles[] = 'Diketahui Oleh HCD Dept Head';
  $approvalRoles[] = 'Diterima Oleh PIC Recruitment';

  $stageRoleMap = [
    2 => ['division_head'],
    3 => ['executive'],
    4 => ['executive'],
    5 => $isOutsideMpp ? ['dic_approver'] : ['hcd_div_head'],
    6 => $isOutsideMpp ? ['hcd_div_head'] : ['hcd_dept_head'],
    7 => $isOutsideMpp ? ['hcd_dept_head'] : ['pic_recruitment', 'team_hc_2'],
  ];

  if ($isOutsideMpp) {
    $stageRoleMap[8] = ['pic_recruitment', 'team_hc_2'];
  }

  $divisionHeadUser = resolveDivisionHeadApproverForDepartment($departmentId);
  $hcdDivHeadUser = findFirstActiveUserByRoles(['hcd_div_head'])
    ?? findFirstActiveUserByEmails(['division.fa.hcga@pmp.local']);
  $hcdDeptHeadUser = findFirstActiveUserByRoles(['hcd_dept_head'])
    ?? findFirstActiveUserByEmails(['head-hcgaesrit@airsys.com']);

  $executiveOneUser = findFirstActiveUserByEmails(['rimba.kusumadilaga@pmp.local']);
  $executiveTwoUser = findFirstActiveUserByEmails(['teguh.patmuryanto@pmp.local']);
  $executiveThreeUser = findFirstActiveUserByEmails(['dic.ms.engineering.scm@pmp.local']);

  $picRecruitmentUser = findFirstActiveUserByRoles(['pic_recruitment', 'team_hc_2'])
    ?? findFirstActiveUserByEmails(['hc2@pmp.com']);

  $approvals = [];
  foreach ($approvalRoles as $index => $role) {
    $approvalName = null;
    $isSigned = false;
    $approverUserId = null;
    $requiredRoles = $stageRoleMap[$index] ?? [];

    if ($index === 0 || $index === 1) {
      $approvalName = resolveApprovalSignerName($requester);
      $isSigned = true;
      $approverUserId = $requester->id;
    }

    if ($index === 2 && $divisionHeadUser) {
      $approvalName = resolveApprovalSignerName($divisionHeadUser);
      $approverUserId = $divisionHeadUser->id;
    }

    if ($index === 3 && $executiveOneUser) {
      $approvalName = resolveApprovalSignerName($executiveOneUser);
      $approverUserId = $executiveOneUser->id;
    }

    if ($index === 4 && $executiveTwoUser) {
      $approvalName = resolveApprovalSignerName($executiveTwoUser);
      $approverUserId = $executiveTwoUser->id;
    }

    if ($isOutsideMpp && $index === 5 && $executiveThreeUser) {
      $approvalName = resolveApprovalSignerName($executiveThreeUser);
      $approverUserId = $executiveThreeUser->id;
    }

    if ((($isOutsideMpp && $index === 6) || (!$isOutsideMpp && $index === 5)) && $hcdDivHeadUser) {
      $approvalName = resolveApprovalSignerName($hcdDivHeadUser);
      $approverUserId = $hcdDivHeadUser->id;
    }

    if ((($isOutsideMpp && $index === 7) || (!$isOutsideMpp && $index === 6)) && $hcdDeptHeadUser) {
      $approvalName = resolveApprovalSignerName($hcdDeptHeadUser);
      $approverUserId = $hcdDeptHeadUser->id;
    }

    if ((($isOutsideMpp && $index === 8) || (!$isOutsideMpp && $index === 7)) && $picRecruitmentUser) {
      $approvalName = resolveApprovalSignerName($picRecruitmentUser);
      $approverUserId = $picRecruitmentUser->id;
    }

    $approvals[] = [
      'role' => $role,
      'required_roles' => $requiredRoles,
      'name' => $approvalName,
      'approver_user_id' => $approverUserId,
      'decision' => $isSigned ? 'approved' : 'pending',
      'digitally_signed' => $isSigned,
      'signature_label' => $isSigned ? 'Digitally Signed' : null,
      'signed_at' => $isSigned ? now()->toDateTimeString() : null,
    ];
  }

  return $approvals;
}

function findApprovalByRole(array $approvals, string $role): ?array
{
  foreach ($approvals as $approval) {
    if ((string) ($approval['role'] ?? '') === $role) {
      return $approval;
    }
  }

  return null;
}

function createNewFormSubmissionForTest(User $user, int $departmentId, int $year, string $submissionType, ?int $vacancyId, ?string $customJabatanName): MPPSubmission
{
  $isOutsideMpp = $submissionType === 'unplanned';
  $approvals = buildApprovalsForScenario($user, $departmentId, $isOutsideMpp);

  $submission = MPPSubmission::create([
    'created_by_user_id' => $user->id,
    'department_id' => $departmentId,
    'year' => $year,
    'submission_type' => $submissionType,
    'form_version' => 'new',
    'vacancy_id' => $vacancyId,
    'custom_jabatan_name' => $customJabatanName,
    'kesesuaian_man_power_plan' => $isOutsideMpp ? 'di_luar_mpp' : 'sesuai_mpp',
    'status' => MPPSubmission::STATUS_SUBMITTED,
    'submitted_at' => now(),
  ]);

  foreach (array_values($approvals) as $index => $approval) {
    $submission->approvalStages()->create([
      'stage_index' => (int) $index,
      'role' => (string) ($approval['role'] ?? ''),
      'required_roles' => array_values((array) ($approval['required_roles'] ?? [])),
      'name' => $approval['name'] ?? null,
      'approver_user_id' => $approval['approver_user_id'] ?? null,
      'decision' => (string) ($approval['decision'] ?? 'pending'),
      'digitally_signed' => (bool) ($approval['digitally_signed'] ?? false),
      'signature_label' => $approval['signature_label'] ?? null,
      'signed_at' => $approval['signed_at'] ?? null,
    ]);
  }

  return $submission->load('approvalStages');
}

function getSubmissionApprovalsForScenario(MPPSubmission $submission): array
{
  return $submission->approvalStages
    ->sortBy('stage_index')
    ->values()
    ->map(function ($stage) {
      return [
        'role' => (string) ($stage->role ?? ''),
        'required_roles' => array_values((array) ($stage->required_roles ?? [])),
        'name' => $stage->name,
        'approver_user_id' => $stage->approver_user_id,
        'decision' => (string) ($stage->decision ?? 'pending'),
        'digitally_signed' => (bool) $stage->digitally_signed,
        'signature_label' => $stage->signature_label,
        'signed_at' => optional($stage->signed_at)->toDateTimeString(),
      ];
    })
    ->all();
}

function findSafeYearForVacancy(?int $vacancyId, ?string $customJabatanName): int
{
  for ($year = 2100; $year >= 2000; $year--) {
    $plannedExists = hasDuplicateForScenario($year, 'planned', $vacancyId, $customJabatanName);
    $unplannedExists = hasDuplicateForScenario($year, 'unplanned', $vacancyId, $customJabatanName);

    if (!$plannedExists && !$unplannedExists) {
      return $year;
    }
  }

  return (int) date('Y');
}

function assertResult(array &$rows, string $email, string $scope, string $scenario, bool $passed, string $details = ''): void
{
  $rows[] = [
    'email' => $email,
    'scope' => $scope,
    'scenario' => $scenario,
    'status' => $passed ? 'PASS' : 'FAIL',
    'details' => $details,
  ];
}

function runWithTransaction(callable $callback, bool $persistChanges)
{
  DB::beginTransaction();

  try {
    $result = $callback();

    if ($persistChanges) {
      DB::commit();
    } else {
      DB::rollBack();
    }

    return $result;
  } catch (Throwable $e) {
    DB::rollBack();
    throw $e;
  }
}

$resultRows = [];

echo "=== MPP CREATION SCENARIO TESTER ===\n";
echo 'Persist mode: ' . ($persistChanges ? 'ON (test records WILL be saved)' : 'OFF (rollback, no records saved)') . "\n";
echo "Timestamp: " . now()->toDateTimeString() . "\n\n";

foreach ($deptHeadEmails as $email) {
  $emailLower = strtolower($email);

  if ($onlyEmail && $onlyEmail !== $emailLower) {
    continue;
  }

  $user = User::query()->whereRaw('LOWER(email) = ?', [$emailLower])->first();
  if (!$user) {
    assertResult($resultRows, $email, '-', 'user_exists', false, 'User not found');
    continue;
  }

  /** @var User $user */

  $canCreate = canCreateNewFormByPolicy($user->email, $allowedBoth, $allowedNewOnly, $blocked);
  assertResult($resultRows, $email, '-', 'policy_can_create_new', $canCreate, $canCreate ? '' : 'Blocked by policy');

  $accessibleDepartmentIds = $user->getAccessibleDepartmentIds();
  if (empty($accessibleDepartmentIds)) {
    assertResult($resultRows, $email, '-', 'has_accessible_department', false, 'No accessible_department_ids and no department_id fallback');
    continue;
  }

  foreach ($accessibleDepartmentIds as $departmentId) {
    $department = Department::find($departmentId);
    if (!$department) {
      assertResult($resultRows, $email, 'dept:' . $departmentId, 'department_exists', false, 'Department not found');
      continue;
    }

    $vacancyQuery = Vacancy::query()
      ->where('department_id', $departmentId)
      ->where('is_active', true)
      ->orderBy('id');

    if ($maxVacanciesPerDept !== null) {
      $vacancyQuery->limit($maxVacanciesPerDept);
    }

    $vacancies = $vacancyQuery->get();
    if ($vacancies->isEmpty()) {
      assertResult($resultRows, $email, 'dept:' . $department->name, 'has_active_vacancy', false, 'No active jabatan in this department');
    }

    foreach ($vacancies as $vacancy) {
      $scope = 'dept:' . $department->name . ' | jabatan:' . $vacancy->name . ' (#' . $vacancy->id . ')';

      try {
        runWithTransaction(function () use ($user, $departmentId, $vacancy, $email, $scope, &$resultRows) {
          $year = findSafeYearForVacancy((int) $vacancy->id, null);

          $isDuplicateBeforePlanned = hasDuplicateForScenario($year, 'planned', (int) $vacancy->id, null);
          assertResult($resultRows, $email, $scope, 'planned_first_not_duplicate', !$isDuplicateBeforePlanned, 'year=' . $year);

          if (!$isDuplicateBeforePlanned) {
            $plannedSubmission = createNewFormSubmissionForTest($user, $departmentId, $year, 'planned', (int) $vacancy->id, null);

            $plannedApprovals = getSubmissionApprovalsForScenario($plannedSubmission);
            $plannedExec3 = findApprovalByRole($plannedApprovals, 'Disetujui Oleh Executive 3');
            assertResult($resultRows, $email, $scope, 'approval_planned_exec3_absent', $plannedExec3 === null);
            assertResult($resultRows, $email, $scope, 'approval_planned_stage_count', count($plannedApprovals) === 8, 'count=' . count($plannedApprovals));
          }

          $isDuplicateAfterPlanned = hasDuplicateForScenario($year, 'planned', (int) $vacancy->id, null);
          assertResult($resultRows, $email, $scope, 'planned_duplicate_detected', $isDuplicateAfterPlanned, 'year=' . $year);

          $isUnplannedDuplicateBefore = hasDuplicateForScenario($year, 'unplanned', (int) $vacancy->id, null);
          assertResult($resultRows, $email, $scope, 'outside_mpp_first_not_duplicate', !$isUnplannedDuplicateBefore, 'year=' . $year);

          if (!$isUnplannedDuplicateBefore) {
            $unplannedSubmission = createNewFormSubmissionForTest($user, $departmentId, $year, 'unplanned', (int) $vacancy->id, null);

            $unplannedApprovals = getSubmissionApprovalsForScenario($unplannedSubmission);
            $unplannedExec3 = findApprovalByRole($unplannedApprovals, 'Disetujui Oleh Executive 3');
            $exec3User = findFirstActiveUserByEmails(['dic.ms.engineering.scm@pmp.local']);

            assertResult($resultRows, $email, $scope, 'approval_outside_mpp_exec3_present', $unplannedExec3 !== null);
            assertResult($resultRows, $email, $scope, 'approval_outside_mpp_stage_count', count($unplannedApprovals) === 9, 'count=' . count($unplannedApprovals));

            if ($exec3User && is_array($unplannedExec3)) {
              $exec3Matched = ((int) ($unplannedExec3['approver_user_id'] ?? 0)) === (int) $exec3User->id;
              assertResult($resultRows, $email, $scope, 'approval_outside_mpp_exec3_is_dic_user', $exec3Matched, 'expected_user_id=' . $exec3User->id);
            }
          }

          $isUnplannedDuplicateAfter = hasDuplicateForScenario($year, 'unplanned', (int) $vacancy->id, null);
          assertResult($resultRows, $email, $scope, 'outside_mpp_duplicate_detected', $isUnplannedDuplicateAfter, 'year=' . $year);
        }, $persistChanges);
      } catch (Throwable $e) {
        assertResult($resultRows, $email, $scope, 'vacancy_case_runtime_error', false, $e->getMessage());
      }
    }

    $customScope = 'dept:' . $department->name . ' | jabatan:OTHER-CUSTOM';

    try {
      runWithTransaction(function () use ($user, $departmentId, $email, $customScope, &$resultRows) {
        $customName = 'AUTO TEST CUSTOM JABATAN';
        $year = findSafeYearForVacancy(null, $customName);

        $isDuplicateBeforePlanned = hasDuplicateForScenario($year, 'planned', null, $customName);
        assertResult($resultRows, $email, $customScope, 'custom_planned_first_not_duplicate', !$isDuplicateBeforePlanned, 'year=' . $year);

        if (!$isDuplicateBeforePlanned) {
          $plannedSubmission = createNewFormSubmissionForTest($user, $departmentId, $year, 'planned', null, $customName);

          $plannedApprovals = getSubmissionApprovalsForScenario($plannedSubmission);
          $plannedExec3 = findApprovalByRole($plannedApprovals, 'Disetujui Oleh Executive 3');
          assertResult($resultRows, $email, $customScope, 'approval_custom_planned_exec3_absent', $plannedExec3 === null);
          assertResult($resultRows, $email, $customScope, 'approval_custom_planned_stage_count', count($plannedApprovals) === 8, 'count=' . count($plannedApprovals));
        }

        $isDuplicateAfterPlanned = hasDuplicateForScenario($year, 'planned', null, $customName);
        assertResult($resultRows, $email, $customScope, 'custom_planned_duplicate_detected', $isDuplicateAfterPlanned, 'year=' . $year);

        $isDuplicateBeforeUnplanned = hasDuplicateForScenario($year, 'unplanned', null, $customName);
        assertResult($resultRows, $email, $customScope, 'custom_outside_mpp_first_not_duplicate', !$isDuplicateBeforeUnplanned, 'year=' . $year);

        if (!$isDuplicateBeforeUnplanned) {
          $unplannedSubmission = createNewFormSubmissionForTest($user, $departmentId, $year, 'unplanned', null, $customName);

          $unplannedApprovals = getSubmissionApprovalsForScenario($unplannedSubmission);
          $unplannedExec3 = findApprovalByRole($unplannedApprovals, 'Disetujui Oleh Executive 3');
          $exec3User = findFirstActiveUserByEmails(['dic.ms.engineering.scm@pmp.local']);

          assertResult($resultRows, $email, $customScope, 'approval_custom_outside_mpp_exec3_present', $unplannedExec3 !== null);
          assertResult($resultRows, $email, $customScope, 'approval_custom_outside_mpp_stage_count', count($unplannedApprovals) === 9, 'count=' . count($unplannedApprovals));

          if ($exec3User && is_array($unplannedExec3)) {
            $exec3Matched = ((int) ($unplannedExec3['approver_user_id'] ?? 0)) === (int) $exec3User->id;
            assertResult($resultRows, $email, $customScope, 'approval_custom_outside_mpp_exec3_is_dic_user', $exec3Matched, 'expected_user_id=' . $exec3User->id);
          }
        }

        $isDuplicateAfterUnplanned = hasDuplicateForScenario($year, 'unplanned', null, $customName);
        assertResult($resultRows, $email, $customScope, 'custom_outside_mpp_duplicate_detected', $isDuplicateAfterUnplanned, 'year=' . $year);
      }, $persistChanges);
    } catch (Throwable $e) {
      assertResult($resultRows, $email, $customScope, 'custom_case_runtime_error', false, $e->getMessage());
    }

    $otherDepartmentId = Department::query()
      ->whereNotIn('id', $accessibleDepartmentIds)
      ->orderBy('id')
      ->value('id');

    if ($otherDepartmentId) {
      $otherDepartmentAllowed = $user->hasDepartmentAccess((int) $otherDepartmentId);
      assertResult(
        $resultRows,
        $email,
        'dept:' . $department->name,
        'outside_scope_department_rejected',
        !$otherDepartmentAllowed,
        'other_department_id=' . $otherDepartmentId
      );
    } else {
      assertResult(
        $resultRows,
        $email,
        'dept:' . $department->name,
        'outside_scope_department_rejected',
        true,
        'Skipped: no other department available'
      );
    }
  }
}

$total = count($resultRows);
$passed = count(array_filter($resultRows, fn($row) => $row['status'] === 'PASS'));
$failed = $total - $passed;

echo "Scenario results:\n";
echo str_repeat('-', 140) . "\n";
echo str_pad('Email', 48)
  . str_pad('Scope', 52)
  . str_pad('Scenario', 38)
  . str_pad('Status', 8)
  . "Details\n";
echo str_repeat('-', 140) . "\n";

foreach ($resultRows as $row) {
  echo str_pad(substr($row['email'], 0, 47), 48)
    . str_pad(substr($row['scope'], 0, 51), 52)
    . str_pad(substr($row['scenario'], 0, 37), 38)
    . str_pad($row['status'], 8)
    . $row['details']
    . "\n";
}

echo str_repeat('-', 140) . "\n";
echo "TOTAL: {$total} | PASS: {$passed} | FAIL: {$failed}\n";

if ($failed > 0) {
  echo "\nFailed scenarios:\n";
  foreach ($resultRows as $row) {
    if ($row['status'] === 'FAIL') {
      echo '- ' . $row['email'] . ' | ' . $row['scope'] . ' | ' . $row['scenario'] . ' | ' . $row['details'] . "\n";
    }
  }
}

if ($persistChanges) {
  echo "\nDone. Persist mode ON: test records were committed to database.\n";
} else {
  echo "\nDone. Persist mode OFF: all database changes were rolled back.\n";
}
