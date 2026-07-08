<?php

namespace App\Http\Controllers;

use App\Models\MPPSubmission;
use App\Models\Vacancy;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class MPPSubmissionController extends Controller
{
    /**
     * Display a listing of MPP submissions
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $canAccessOldForm = $this->canAccessOldFormSubmission($user);

        if ($user->hasRole('admin') && !$canAccessOldForm) {
            abort(403);
        }

        // Check if user can view MPP submissions
        if (!$user->can('view-mpp-submissions') && !$canAccessOldForm) {
            abort(403);
        }

        $query = MPPSubmission::with(['department', 'createdByUser', 'vacancies', 'vacancy', 'approvalStages']);
        $accessibleDepartmentIds = $user->getAccessibleDepartmentIds();
        $isGlobalHcdViewer = $this->isGlobalHcdDeptHeadViewer($user);

        // Old form can only be seen by whitelisted accounts.
        if (!$canAccessOldForm) {
            $query->where('form_version', 'new');
        }

        // Filter by department if user is department head or department staff
        if (
            !$canAccessOldForm
            && $user->hasAnyRole(['kepala departemen', 'division_head'])
            && !$isGlobalHcdViewer
        ) {
            if (empty($accessibleDepartmentIds)) {
                abort(403);
            }
            $query->whereIn('department_id', $accessibleDepartmentIds);
        }

        // Keep an unfiltered visible query for approval queue and high-level summary cards.
        $baseVisibleQuery = clone $query;

        // Filter by status if requested
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by year if requested
        if ($request->filled('year')) {
            $query->where('year', $request->input('year'));
        }

        $totalVisible = (clone $baseVisibleQuery)->count();
        $submittedVisible = (clone $baseVisibleQuery)->where('status', MPPSubmission::STATUS_SUBMITTED)->count();
        $approvedVisible = (clone $baseVisibleQuery)->where('status', MPPSubmission::STATUS_APPROVED)->count();
        $rejectedVisible = (clone $baseVisibleQuery)->where('status', MPPSubmission::STATUS_REJECTED)->count();

        $approvalQueueCandidates = (clone $baseVisibleQuery)
            ->where('status', MPPSubmission::STATUS_SUBMITTED)
            ->orderBy('created_at', 'asc')
            ->limit(1000)
            ->get();

        $isExecutiveApprovalAccount = $this->isExecutiveApprovalAccount($user);

        $myApprovalQueue = $approvalQueueCandidates
            ->map(function (MPPSubmission $submission) use ($user, $isExecutiveApprovalAccount) {
                if ($isExecutiveApprovalAccount) {
                    $executiveStage = $this->resolveExecutiveQueueStage($user, $submission);
                    if (!$executiveStage) {
                        return null;
                    }

                    return [
                        'submission' => $submission,
                        'stage_index' => (int) $executiveStage['stage_index'],
                        'stage_role' => (string) $executiveStage['stage_role'],
                        'stage_decision' => (string) $executiveStage['stage_decision'],
                    ];
                }

                $approvableStageIndexes = $this->getApprovableStageIndexes($user, $submission);
                if (empty($approvableStageIndexes)) {
                    return null;
                }

                $approvalStages = $this->getSubmissionApprovals($submission);
                $queueableStageIndexes = collect($approvableStageIndexes)
                    ->map(fn($index) => (int) $index)
                    ->filter(function (int $index) use ($approvalStages) {
                        $decision = strtolower((string) data_get($approvalStages->get($index, []), 'decision', 'pending'));

                        return $decision === 'pending';
                    })
                    ->values()
                    ->all();

                if (empty($queueableStageIndexes)) {
                    return null;
                }

                $stageIndex = (int) $queueableStageIndexes[0];
                $stageData = $approvalStages->get($stageIndex, []);

                return [
                    'submission' => $submission,
                    'stage_index' => $stageIndex,
                    'stage_role' => (string) data_get($stageData, 'role', '-'),
                    'stage_decision' => (string) data_get($stageData, 'decision', 'pending'),
                ];
            })
            ->filter()
            ->values();

        $mppSubmissions = $query->orderBy('created_at', 'desc')->paginate(15);

        // Get distinct years for the filter dropdown (single source of truth)
        $years = \App\Services\YearProvider::availableYears();

        return view('mpp-submissions.index', [
            'mppSubmissions' => $mppSubmissions,
            'years' => $years,
            'mppSummary' => [
                'total_visible' => $totalVisible,
                'submitted' => $submittedVisible,
                'approved' => $approvedVisible,
                'rejected' => $rejectedVisible,
                'need_my_approval' => $myApprovalQueue->count(),
            ],
            'myApprovalQueue' => $myApprovalQueue,
            'canCreateOldFormMpp' => $this->canCreateOldFormMpp($user),
            'canCreateNewFormMpp' => $this->canCreateNewFormMpp($user),
        ]);
    }

    /**
     * Show the form for creating a new MPP submission
     */
    public function create()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$this->canCreateOldFormMpp($user)) {
            abort(403);
        }

        $departmentQuery = Department::query();
        $accessibleDepartmentIds = $user->getAccessibleDepartmentIds();
        if ($user->hasAnyRole(['kepala departemen', 'division_head']) && !empty($accessibleDepartmentIds)) {
            $departmentQuery->whereIn('id', $accessibleDepartmentIds);
        }

        $departments = $departmentQuery->get()->map(function ($dept) {
            return [
                'id' => $dept->id,
                'name' => $dept->name,
            ];
        });

        // Get available positions for each department
        $positions = Vacancy::where('is_active', true)
            ->with('department')
            ->get()
            ->groupBy('department_id')
            ->map(function ($vacancies, $deptId) {
                return [
                    'department_id' => $deptId,
                    'positions' => $vacancies->map(fn($v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                    ])->values(),
                ];
            })->values();

        return view('mpp-submissions.create', [
            'departments' => $departments,
            'positions' => $positions,
        ]);
    }

    /**
     * Show the form for creating a new MPP submission (new digital form)
     */
    public function createNew()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$this->canCreateNewFormMpp($user)) {
            abort(403);
        }

        $departmentQuery = Department::query();
        $accessibleDepartmentIds = $user->getAccessibleDepartmentIds();
        if ($user->hasAnyRole(['kepala departemen', 'division_head']) && !empty($accessibleDepartmentIds)) {
            $departmentQuery->whereIn('id', $accessibleDepartmentIds);
        }

        $departments = $departmentQuery->get()->map(function ($dept) {
            return [
                'id' => $dept->id,
                'name' => $dept->name,
            ];
        });

        $years = \App\Services\YearProvider::availableYears();

        $positions = Vacancy::where('is_active', true)
            ->with('department')
            ->get()
            ->groupBy('department_id')
            ->map(function ($vacancies, $deptId) {
                return [
                    'department_id' => $deptId,
                    'positions' => $vacancies->map(fn($v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                    ])->values(),
                ];
            })->values();

        $approvalRoleLabels = [
            'Diminta Oleh',
            'Diketahui Oleh Dept. Head',
            'Diketahui Oleh Div. Head',
            'Disetujui Oleh HCD Div Head',
            'Diketahui Oleh HCD Dept Head',
            'Diterima Oleh PIC Recruitment',
            'Disetujui Oleh Executive 1',
            'Disetujui Oleh Executive 2',
        ];

        $roleAttachmentUsers = collect();
        if ($user->hasRole('admin')) {
            $roleAttachmentUsers = User::query()
                ->where('status', 1)
                ->with('roles')
                ->orderBy('name')
                ->get()
                ->map(function (User $account) {
                    return [
                        'id' => $account->id,
                        'name' => $account->name,
                        'role' => $account->getRoleNames()->first() ?? '-',
                    ];
                })
                ->values();
        }

        return view('mpp-submissions.create-new', [
            'departments' => $departments,
            'years' => $years,
            'positions' => $positions,
            'approvalRoleLabels' => $approvalRoleLabels,
            'roleAttachmentUsers' => $roleAttachmentUsers,
        ]);
    }

    /**
     * Store a newly created MPP submission
     */
    public function storeOld(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$this->canCreateOldFormMpp($user)) {
            abort(403);
        }

        if (
            $user->hasAnyRole(['kepala departemen', 'division_head'])
            && !$user->hasDepartmentAccess((int) $request->input('department_id'))
        ) {
            return back()->withErrors([
                'department_id' => 'Anda hanya dapat membuat pengajuan untuk departemen yang Anda miliki akses.',
            ])->withInput();
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'year' => 'required|integer|min:2000|max:2100',
            'positions' => 'required|array|min:1',
            'positions.*.vacancy_id' => 'required|exists:vacancies,id',
            'positions.*.vacancy_status' => 'required|in:OSPKWT,OS',
            'positions.*.needed_count' => 'required|integer|min:1',
        ]);

        // Custom validation to check for uniqueness
        foreach ($validated['positions'] as $position) {
            $existing = DB::table('mpp_submission_vacancy')
                ->join('mpp_submissions', 'mpp_submission_vacancy.m_p_p_submission_id', '=', 'mpp_submissions.id')
                ->where('mpp_submission_vacancy.vacancy_id', $position['vacancy_id'])
                ->where('mpp_submissions.year', $validated['year'])
                ->whereIn('mpp_submissions.status', [MPPSubmission::STATUS_SUBMITTED, MPPSubmission::STATUS_APPROVED])
                ->exists();

            if ($existing) {
                $vacancy = Vacancy::find($position['vacancy_id']);
                return back()->withErrors([
                    'positions' => 'Posisi "' . $vacancy->name . '" sudah ada di pengajuan MPP lain untuk tahun ' . $validated['year'] . '.'
                ])->withInput();
            }
        }

        DB::transaction(function () use ($validated, $user) {
            $mppSubmission = MPPSubmission::create([
                'created_by_user_id' => $user->id,
                'department_id' => $validated['department_id'],
                'year' => $validated['year'],
                'status' => MPPSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            $mppSubmission->approvalHistories()->create([
                'user_id' => $user->id,
                'action' => 'created_and_submitted',
            ]);

            $vacanciesToAttach = [];
            foreach ($validated['positions'] as $position) {
                $vacanciesToAttach[$position['vacancy_id']] = [
                    'vacancy_status' => $position['vacancy_status'],
                    'needed_count' => $position['needed_count'],
                    'proposal_status' => 'pending',
                    'proposed_by_user_id' => $user->id,
                ];
            }

            $mppSubmission->vacancies()->attach($vacanciesToAttach);
        });

        return redirect()->route('mpp-submissions.index')
            ->with('success', 'MPP submission created successfully');
    }

    /**
     * Store a newly created MPP submission
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            abort(403);
        }

        if (!$user->can('create-mpp-submission')) {
            abort(403);
        }

        if (
            $user->hasAnyRole(['kepala departemen', 'division_head'])
            && !$user->hasDepartmentAccess((int) $request->input('department_id'))
        ) {
            return back()->withErrors([
                'department_id' => 'Anda hanya dapat membuat pengajuan untuk departemen yang Anda miliki akses.',
            ])->withInput();
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'year' => 'required|integer|min:2000|max:2100',
            'submission_type' => 'required|in:planned,unplanned',
            'positions' => 'required|array|min:1',
            'positions.*.vacancy_id' => 'required|exists:vacancies,id',
            'positions.*.vacancy_status' => 'required|in:OSPKWT,OS',
            'positions.*.needed_count' => 'required|integer|min:1',
        ]);

        // Custom validation: prevent duplicate vacancy in the same year and submission type
        foreach ($validated['positions'] as $position) {
            $existing = DB::table('mpp_submission_vacancy')
                ->join('mpp_submissions', 'mpp_submission_vacancy.m_p_p_submission_id', '=', 'mpp_submissions.id')
                ->where('mpp_submission_vacancy.vacancy_id', $position['vacancy_id'])
                ->where('mpp_submissions.year', $validated['year'])
                ->where('mpp_submissions.submission_type', $validated['submission_type'])
                ->whereNull('mpp_submissions.deleted_at')
                ->whereIn('mpp_submissions.status', [MPPSubmission::STATUS_SUBMITTED, MPPSubmission::STATUS_APPROVED])
                ->exists();

            if ($existing) {
                $vacancy = Vacancy::find($position['vacancy_id']);
                $submissionTypeLabel = $validated['submission_type'] === 'planned' ? 'Terencana' : 'Di Luar MPP';

                return back()->withErrors([
                    'positions' => 'Posisi "' . $vacancy->name . '" sudah ada di pengajuan ' . strtolower($submissionTypeLabel) . ' untuk tahun ' . $validated['year'] . '.'
                ])->withInput();
            }
        }

        DB::transaction(function () use ($validated, $user) {
            $mppSubmission = MPPSubmission::create([
                'created_by_user_id' => $user->id,
                'department_id' => $validated['department_id'],
                'year' => $validated['year'],
                'submission_type' => $validated['submission_type'], // Store the type
                'status' => MPPSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            $mppSubmission->approvalHistories()->create([
                'user_id' => $user->id,
                'action' => 'created_and_submitted',
            ]);

            $vacanciesToAttach = [];
            foreach ($validated['positions'] as $position) {
                $vacanciesToAttach[$position['vacancy_id']] = [
                    'vacancy_status' => $position['vacancy_status'],
                    'needed_count' => $position['needed_count'],
                    'proposal_status' => 'pending',
                    'proposed_by_user_id' => $user->id,
                ];
            }

            $mppSubmission->vacancies()->attach($vacanciesToAttach);
        });

        return redirect()->route('mpp-submissions.index')
            ->with('success', 'MPP submission created successfully');
    }

    /**
     * Store a newly created MPP submission from the new digital MPP form.
     */
    public function storeNew(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$this->canCreateNewFormMpp($user)) {
            abort(403);
        }

        if (
            $user->hasAnyRole(['kepala departemen', 'division_head'])
            && !$user->hasDepartmentAccess((int) $request->input('department_id'))
        ) {
            return back()->withErrors([
                'department_id' => 'Anda hanya dapat membuat pengajuan untuk departemen yang Anda miliki akses.',
            ])->withInput();
        }

        $statusPegawaiOptions = [
            'sementara_3_bulan',
            'sementara_6_bulan',
            'sementara_12_bulan',
            'sementara_18_bulan',
        ];

        $fasilitasOptions = [
            'computer',
            'meja_dan_kursi_kerja',
            'seragam_apd',
            'safety_shoes',
            'extra_fooding',
            'safety_helmet',
            'others',
        ];

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'year' => 'required|integer|min:2000|max:2100',
            'vacancy_id' => 'required|string',
            'custom_jabatan_name' => 'nullable|required_if:vacancy_id,other|string|max:255',
            'golongan' => 'nullable|string|max:255',

            'status_pegawai' => ['required', Rule::in($statusPegawaiOptions)],
            'lokasi_pekerjaan' => 'required|in:head_office,cabang',
            'tanggal_mulai_bekerja' => 'required|date',
            'jumlah_diminta' => 'required|integer|min:1',

            'alasan_penambahan_manpower' => 'required|in:penggantian_karyawan,penambahan_karyawan_baru',
            'nama_karyawan_diganti' => 'nullable|required_if:alasan_penambahan_manpower,penggantian_karyawan|string|max:255',
            'tanggal_keluar' => 'nullable|required_if:alasan_penambahan_manpower,penggantian_karyawan|date',
            'alasan_penggantian' => 'nullable|required_if:alasan_penambahan_manpower,penggantian_karyawan|string',

            'jumlah_karyawan_ada' => 'nullable|required_if:alasan_penambahan_manpower,penambahan_karyawan_baru|integer|min:0',
            'kesesuaian_man_power_plan' => 'nullable|required_if:alasan_penambahan_manpower,penambahan_karyawan_baru|in:sesuai_mpp,di_luar_mpp',
            'alasan_kesesuaian' => 'nullable|required_if:kesesuaian_man_power_plan,di_luar_mpp|string',

            'pendidikan' => 'nullable|array',
            'pendidikan.*' => 'nullable|in:smk,d3,s1',
            'jurusan_smk' => 'nullable|string|max:255',
            'jurusan_d3' => 'nullable|string|max:255',
            'jurusan_s1' => 'nullable|string|max:255',
            'keahlian_khusus' => 'nullable|string',
            'jenis_kelamin' => 'required|in:laki_laki,perempuan',
            'status_perkawinan' => 'required|in:kawin,tidak_kawin',
            'pengalaman_kerja' => 'nullable|string',

            'uraian_jabatan' => 'required|array|min:1',
            'uraian_jabatan.*' => 'required|string',

            'fasilitas_dibutuhkan' => 'nullable|array',
            'fasilitas_dibutuhkan.*' => ['nullable', Rule::in($fasilitasOptions)],
            'fasilitas_lainnya' => 'nullable|string|max:255',

            'approval_attachments' => 'nullable|array',
            'approval_attachments.*.approver_user_id' => 'nullable|integer|exists:users,id',
            'approval_attachments.*.role' => 'nullable|string|max:255',

        ]);

        $selectedVacancyId = null;
        if ($validated['vacancy_id'] !== 'other') {
            if (!ctype_digit((string) $validated['vacancy_id'])) {
                return back()->withErrors([
                    'vacancy_id' => 'Nama jabatan tidak valid.',
                ])->withInput();
            }

            $selectedVacancyId = (int) $validated['vacancy_id'];
            $selectedVacancy = Vacancy::query()
                ->where('id', $selectedVacancyId)
                ->where('department_id', $validated['department_id'])
                ->where('is_active', true)
                ->first(['id']);

            if (!$selectedVacancy) {
                return back()->withErrors([
                    'vacancy_id' => 'Nama jabatan tidak ditemukan untuk departemen yang dipilih.',
                ])->withInput();
            }
        }

        $customJabatanName = $validated['vacancy_id'] === 'other'
            ? ($validated['custom_jabatan_name'] ?? null)
            : null;

        // Business rule: prevent duplicate position submission in the same year
        // for the same submission type (planned vs di luar MPP).
        $isOutsideMpp = ($validated['kesesuaian_man_power_plan'] ?? null) === 'di_luar_mpp';
        $duplicateQuery = MPPSubmission::query()
            ->where('year', $validated['year'])
            ->where('submission_type', $isOutsideMpp ? 'unplanned' : 'planned')
            ->whereIn('status', [MPPSubmission::STATUS_SUBMITTED, MPPSubmission::STATUS_APPROVED])
            ->whereNull('deleted_at');

        if ($selectedVacancyId) {
            $duplicateQuery->where(function ($query) use ($selectedVacancyId) {
                // New form stores selected position on mpp_submissions.vacancy_id.
                $query->where('vacancy_id', $selectedVacancyId)
                    // Old form stores selected positions in mpp_submission_vacancy pivot.
                    ->orWhereHas('vacancies', function ($vacancyQuery) use ($selectedVacancyId) {
                        $vacancyQuery->where('vacancies.id', $selectedVacancyId);
                    });
            });
        } else {
            $normalizedCustomName = strtolower(trim((string) $customJabatanName));
            $duplicateQuery->whereRaw('LOWER(TRIM(custom_jabatan_name)) = ?', [$normalizedCustomName]);
        }

        if ($duplicateQuery->exists()) {
            $positionLabel = $selectedVacancyId
                ? (optional(Vacancy::find($selectedVacancyId))->name ?? 'posisi ini')
                : ((string) $customJabatanName ?: 'posisi ini');

            if ($isOutsideMpp) {
                return back()->withErrors([
                    'vacancy_id' => 'Posisi "' . $positionLabel . '" sudah ada di pengajuan di luar MPP untuk tahun ' . $validated['year'] . '.',
                ])->withInput();
            }

            return back()->withErrors([
                'vacancy_id' => 'Posisi "' . $positionLabel . '" sudah ada pada pengajuan MPP tahun ' . $validated['year'] . '. Gunakan opsi "Di Luar Man Power Plan" jika ini memang pengajuan di luar MPP.',
            ])->withInput();
        }

        $fasilitasDibutuhkan = array_values($validated['fasilitas_dibutuhkan'] ?? []);
        $fasilitasLainnya = trim((string) ($validated['fasilitas_lainnya'] ?? ''));

        if (in_array('others', $fasilitasDibutuhkan, true)) {
            if ($fasilitasLainnya === '') {
                return back()->withErrors([
                    'fasilitas_lainnya' => 'Field Others wajib diisi saat opsi Others dipilih.',
                ])->withInput();
            }

            $fasilitasDibutuhkan = array_values(array_filter(
                $fasilitasDibutuhkan,
                fn($item) => $item !== 'others'
            ));
            $fasilitasDibutuhkan[] = 'others:' . $fasilitasLainnya;
        }

        $selectedPendidikan = $validated['pendidikan'] ?? [];
        $educationRequirements = [
            'smk' => [
                'selected' => in_array('smk', $selectedPendidikan, true),
                'major' => $validated['jurusan_smk'] ?? null,
            ],
            'd3' => [
                'selected' => in_array('d3', $selectedPendidikan, true),
                'major' => $validated['jurusan_d3'] ?? null,
            ],
            's1' => [
                'selected' => in_array('s1', $selectedPendidikan, true),
                'major' => $validated['jurusan_s1'] ?? null,
            ],
        ];

        $approvalRoles = [
            'Diminta Oleh',
            'Diketahui Oleh Dept. Head',
            'Diketahui Oleh Div. Head',
            'Disetujui Oleh HCD Div Head',
            'Diketahui Oleh HCD Dept Head',
            'Diterima Oleh PIC Recruitment',
            'Disetujui Oleh Executive 1',
            'Disetujui Oleh Executive 2',
        ];

        if ($isOutsideMpp) {
            $approvalRoles[] = 'Disetujui Oleh Executive 3';
        }

        $stageRoleMap = [
            2 => ['division_head'],
            3 => ['hcd_div_head'],
            4 => ['hcd_dept_head'],
            5 => ['pic_recruitment', 'team_hc_2'],
            6 => ['executive'],
            7 => ['executive'],
        ];

        if ($isOutsideMpp) {
            $stageRoleMap[8] = ['dic_approver'];
        }

        $divisionHeadUser = $this->resolveDivisionHeadApproverForDepartment((int) $validated['department_id']);

        $hcdDivHeadUser = $this->findFirstActiveUserByRoles(['hcd_div_head'])
            ?? $this->findFirstActiveUserByEmails([
                'division.fa.hcga@pmp.local',
            ]);

        $hcdDeptHeadUser = $this->findFirstActiveUserByRoles(['hcd_dept_head'])
            ?? $this->findFirstActiveUserByEmails([
                'head-hcgaesrit@airsys.com',
            ]);

        // Executive sequence is fixed by business rule:
        // 1) Rimba, 2) Teguh, 3) Isnaryanto (only for di luar MPP).
        $executiveOneUser = $this->findFirstActiveUserByEmails([
            'rimba.kusumadilaga@pmp.local',
        ]);

        $executiveTwoUser = $this->findFirstActiveUserByEmails([
            'teguh.patmuryanto@pmp.local',
        ]);

        $executiveThreeUser = $this->findFirstActiveUserByEmails([
            'dic.ms.engineering.scm@pmp.local',
        ]);

        $picRecruitmentUser = $this->findFirstActiveUserByRoles(['pic_recruitment', 'team_hc_2'])
            ?? $this->findFirstActiveUserByEmails([
                'hc2@pmp.com',
            ]);

        $approvals = [];
        foreach ($approvalRoles as $index => $role) {
            $approvalName = null;
            $isSigned = false;
            $approverUserId = null;
            $requiredRoles = $stageRoleMap[$index] ?? [];

            if ($index === 0) {
                // Diminta Oleh: requester (dept head) - automatically signed on submit.
                $approvalName = $this->resolveApprovalSignerName($user);
                $isSigned = true;
                $approverUserId = $user->id;
            }

            if ($index === 1) {
                // Diketahui Dept Head: requester - automatically signed for current stage.
                $approvalName = $this->resolveApprovalSignerName($user);
                $isSigned = true;
                $approverUserId = $user->id;
            }

            if ($index === 2 && $divisionHeadUser) {
                // Disetujui Div Head: resolved from division head account with department scope.
                $approvalName = $this->resolveApprovalSignerName($divisionHeadUser);
                $approverUserId = $divisionHeadUser->id;
            }

            if ($index === 3 && $hcdDivHeadUser) {
                // Disetujui HCD Div Head: fixed account.
                $approvalName = $this->resolveApprovalSignerName($hcdDivHeadUser);
                $approverUserId = $hcdDivHeadUser->id;
            }

            if ($index === 4 && $hcdDeptHeadUser) {
                // Diketahui HCD Dept Head: account already available, not auto-signed at creation.
                $approvalName = $this->resolveApprovalSignerName($hcdDeptHeadUser);
                $approverUserId = $hcdDeptHeadUser->id;
            }

            if ($index === 5 && $picRecruitmentUser) {
                // Diterima PIC Recruitment: account resolved from configured fallback emails.
                $approvalName = $this->resolveApprovalSignerName($picRecruitmentUser);
                $approverUserId = $picRecruitmentUser->id;
            }

            if ($index === 6 && $executiveOneUser) {
                // Executive 1: fixed account.
                $approvalName = $this->resolveApprovalSignerName($executiveOneUser);
                $approverUserId = $executiveOneUser->id;
            }

            if ($index === 7 && $executiveTwoUser) {
                // Executive 2: fixed account.
                $approvalName = $this->resolveApprovalSignerName($executiveTwoUser);
                $approverUserId = $executiveTwoUser->id;
            }

            if ($isOutsideMpp && $index === 8 && $executiveThreeUser) {
                // Executive 3 (Di Luar MPP only): fixed DIC account.
                $approvalName = $this->resolveApprovalSignerName($executiveThreeUser);
                $approverUserId = $executiveThreeUser->id;
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

        if ($user->hasRole('admin') && !empty($validated['approval_attachments'])) {
            foreach ($validated['approval_attachments'] as $index => $attachment) {
                if (!array_key_exists($index, $approvals)) {
                    continue;
                }

                $selectedUserId = data_get($attachment, 'approver_user_id');
                if (empty($selectedUserId)) {
                    continue;
                }

                $selectedUser = User::query()
                    ->where('status', 1)
                    ->find($selectedUserId);

                if (!$selectedUser) {
                    continue;
                }

                $approvals[$index]['approver_user_id'] = $selectedUser->id;
                $approvals[$index]['name'] = $this->resolveApprovalSignerName($selectedUser);
            }
        }

        $submissionType = ($validated['kesesuaian_man_power_plan'] ?? null) === 'di_luar_mpp'
            ? 'unplanned'
            : 'planned';

        $mppSubmission = DB::transaction(function () use ($validated, $user, $educationRequirements, $approvals, $submissionType, $selectedVacancyId, $customJabatanName, $fasilitasDibutuhkan) {
            $submission = MPPSubmission::create([
                'created_by_user_id' => $user->id,
                'department_id' => $validated['department_id'],
                'year' => $validated['year'],
                'submission_type' => $submissionType,
                'form_version' => 'new',
                'vacancy_id' => $selectedVacancyId,
                'custom_jabatan_name' => $customJabatanName,
                'golongan' => $validated['golongan'] ?? null,
                'status_pegawai' => [$validated['status_pegawai']],
                'lokasi_pekerjaan' => $validated['lokasi_pekerjaan'],
                'tanggal_mulai_bekerja' => $validated['tanggal_mulai_bekerja'],
                'jumlah_diminta' => $validated['jumlah_diminta'],
                'alasan_penambahan_manpower' => $validated['alasan_penambahan_manpower'],
                'nama_karyawan_diganti' => $validated['nama_karyawan_diganti'] ?? null,
                'tanggal_keluar' => $validated['tanggal_keluar'] ?? null,
                'alasan_penggantian' => $validated['alasan_penggantian'] ?? null,
                'jumlah_karyawan_ada' => $validated['jumlah_karyawan_ada'] ?? null,
                'kesesuaian_man_power_plan' => $validated['kesesuaian_man_power_plan'] ?? null,
                'alasan_kesesuaian' => $validated['alasan_kesesuaian'] ?? null,
                'pendidikan_requirements' => $educationRequirements,
                'keahlian_khusus' => $validated['keahlian_khusus'] ?? null,
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'status_perkawinan' => $validated['status_perkawinan'],
                'pengalaman_kerja' => $validated['pengalaman_kerja'] ?? null,
                'uraian_jabatan' => array_values($validated['uraian_jabatan']),
                'fasilitas_dibutuhkan' => $fasilitasDibutuhkan,
                'status' => MPPSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            $submission->approvalHistories()->create([
                'user_id' => $user->id,
                'action' => 'created_and_submitted',
            ]);

            $this->persistSubmissionApprovals($submission, $approvals);

            if (!is_null($selectedVacancyId)) {
                $this->upsertNewFormVacancyPivot($submission, $selectedVacancyId, null);
            }

            return $submission;
        });

        return redirect()->route('mpp-submissions.show', $mppSubmission)
            ->with('success', 'Pengajuan MPP form baru berhasil dibuat.');
    }

    /**
     * Resolve first active user by a prioritized email list.
     */
    private function findFirstActiveUserByEmails(array $emails): ?User
    {
        foreach ($emails as $email) {
            $user = User::where('email', $email)
                ->where('status', 1)
                ->first();

            if ($user) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Resolve first active user by prioritized role list.
     */
    private function findFirstActiveUserByRoles(array $roles): ?User
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

    /**
     * Resolve Div Head approver for a specific department using organization scope.
     *
     * Priority:
     * 1) Any division_head account with department access
     *
     * If multiple candidates match, prefer the most specific account (smallest
     * accessible department scope), then oldest id for deterministic ordering.
     */
    private function resolveDivisionHeadApproverForDepartment(int $departmentId): ?User
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

        // Prefer canonical division mailbox accounts first, e.g.
        // division.banjarmasin.operation@pmp.local.
        $canonicalDivisionCandidates = $candidates
            ->filter(function (User $account) {
                $email = strtolower((string) $account->email);

                return str_starts_with($email, 'division.')
                    && str_ends_with($email, '@pmp.local');
            })
            ->values();

        if ($canonicalDivisionCandidates->isNotEmpty()) {
            $candidates = $canonicalDivisionCandidates;
        }

        $divisionHeadCandidates = $candidates
            ->sort(function (User $a, User $b) {
                $scopeCompare = count($a->getAccessibleDepartmentIds()) <=> count($b->getAccessibleDepartmentIds());
                if ($scopeCompare !== 0) {
                    return $scopeCompare;
                }

                return $a->id <=> $b->id;
            })
            ->values();

        if ($divisionHeadCandidates->isNotEmpty()) {
            return $divisionHeadCandidates->first();
        }

        return $candidates->sortBy('id')->first();
    }

    /**
     * Executive account marker used to separate executive approvers from div-head scope.
     */
    private function isExecutiveAccount(User $user): bool
    {
        return strtolower(trim((string) $user->division_name)) === 'executive';
    }

    /**
     * Display the specified MPP submission
     */
    public function show(MPPSubmission $mppSubmission)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isOldForm = ($mppSubmission->form_version ?? 'old') !== 'new';
        $canAccessOldForm = $this->canAccessOldFormSubmission($user);

        if ($isOldForm && !$canAccessOldForm) {
            abort(403);
        }

        if ($user->hasRole('admin') && !$canAccessOldForm) {
            abort(403);
        }

        if (!$user->can('view-mpp-submission-details') && !$canAccessOldForm) {
            abort(403);
        }

        if (
            !$canAccessOldForm
            &&
            $user->hasAnyRole(['kepala departemen', 'division_head'])
            && !$this->isGlobalHcdDeptHeadViewer($user)
            && !$user->hasDepartmentAccess((int) $mppSubmission->department_id)
        ) {
            abort(403);
        }

        $mppSubmission->load([
            'department',
            'createdByUser',
            'vacancy',
            'vacancies.vacancyDocuments.uploadedByUser',
            'approvalHistories.user',
            'approvalStages',
        ]);

        $departmentVacancies = collect();
        if (($mppSubmission->form_version ?? 'old') === 'new') {
            $departmentVacancies = Vacancy::query()
                ->where('department_id', $mppSubmission->department_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $approvableStageIndexes = $this->getApprovableStageIndexes($user, $mppSubmission);

        return view('mpp-submissions.show', [
            'mppSubmission' => $mppSubmission,
            'departmentVacancies' => $departmentVacancies,
            'approvableStageIndexes' => $approvableStageIndexes,
        ]);
    }

    /**
     * Approve a single stage in the new MPP approval matrix.
     */
    public function approveStage(Request $request, MPPSubmission $mppSubmission)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            abort(403);
        }

        if (($mppSubmission->form_version ?? 'old') !== 'new') {
            return back()->with('error', 'Approval matrix stage hanya tersedia untuk form MPP baru.');
        }

        $validated = $request->validate([
            'stage_index' => 'required|integer|min:0',
        ]);

        $stageIndex = (int) $validated['stage_index'];
        $approvals = $this->getSubmissionApprovals($mppSubmission);
        $targetStage = $approvals->get($stageIndex);

        if (!$targetStage) {
            return back()->with('error', 'Tahapan approval tidak ditemukan.');
        }

        if (!$this->canApproveStage($user, $mppSubmission, $stageIndex, (array) $targetStage)) {
            abort(403);
        }

        $updatedApprovals = $approvals->map(function ($approval, $index) use ($stageIndex, $user) {
            if ($index !== $stageIndex) {
                return $approval;
            }

            $approval['approver_user_id'] = $user->id;
            $approval['name'] = $this->resolveApprovalSignerName($user);
            $approval['decision'] = 'approved';
            $approval['digitally_signed'] = true;
            $approval['signature_label'] = 'Digitally Approved';
            $approval['signed_at'] = now()->toDateTimeString();

            return $approval;
        })->values()->all();

        $updatedApprovals = $this->autoApproveHcdDivHeadWhenSameApprover($updatedApprovals, $stageIndex, $user);

        $this->updateSubmissionStageDecision($mppSubmission, $updatedApprovals);

        $mppSubmission->approvalHistories()->create([
            'user_id' => $user->id,
            'action' => 'approved_stage_' . $stageIndex,
            'notes' => (string) data_get($targetStage, 'role', 'stage'),
        ]);

        return back()->with('success', 'Tahapan approval berhasil disetujui.');
    }

    /**
     * Mass approve selected stage rows from the index approval queue.
     */
    public function massApprove(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            abort(403);
        }

        $validated = $request->validate([
            'selected' => 'required|array|min:1',
            'selected.*' => 'required|string',
        ]);

        $approvedCount = 0;
        $failed = [];

        foreach ($validated['selected'] as $selectedRow) {
            if (!preg_match('/^(\d+):(\d+)$/', (string) $selectedRow, $matches)) {
                $failed[] = 'Format item tidak valid: ' . $selectedRow;
                continue;
            }

            $submissionId = (int) $matches[1];
            $stageIndex = (int) $matches[2];

            $mppSubmission = MPPSubmission::query()->find($submissionId);
            if (!$mppSubmission) {
                $failed[] = 'MPP #' . $submissionId . ' tidak ditemukan.';
                continue;
            }

            if (($mppSubmission->form_version ?? 'old') !== 'new') {
                $failed[] = 'MPP #' . $submissionId . ' bukan form baru.';
                continue;
            }

            $approvals = $this->getSubmissionApprovals($mppSubmission);
            $targetStage = $approvals->get($stageIndex);

            if (!$targetStage) {
                $failed[] = 'Tahap untuk MPP #' . $submissionId . ' tidak ditemukan.';
                continue;
            }

            if (!$this->canApproveStage($user, $mppSubmission, $stageIndex, (array) $targetStage)) {
                $failed[] = 'MPP #' . $submissionId . ' tidak dapat di-approve oleh akun ini.';
                continue;
            }

            $updatedApprovals = $approvals->map(function ($approval, $index) use ($stageIndex, $user) {
                if ($index !== $stageIndex) {
                    return $approval;
                }

                $approval['approver_user_id'] = $user->id;
                $approval['name'] = $this->resolveApprovalSignerName($user);
                $approval['decision'] = 'approved';
                $approval['digitally_signed'] = true;
                $approval['signature_label'] = 'Digitally Approved';
                $approval['signed_at'] = now()->toDateTimeString();

                return $approval;
            })->values()->all();

            $updatedApprovals = $this->autoApproveHcdDivHeadWhenSameApprover($updatedApprovals, $stageIndex, $user);

            DB::transaction(function () use ($mppSubmission, $updatedApprovals, $user, $targetStage, $stageIndex) {
                $this->updateSubmissionStageDecision($mppSubmission, $updatedApprovals);

                $mppSubmission->approvalHistories()->create([
                    'user_id' => $user->id,
                    'action' => 'approved_stage_' . $stageIndex,
                    'notes' => (string) data_get($targetStage, 'role', 'stage'),
                ]);
            });

            $approvedCount++;
        }

        if ($approvedCount > 0) {
            session()->flash('success', $approvedCount . ' tahap approval berhasil diproses.');
        }

        if (!empty($failed)) {
            session()->flash('error', 'Sebagian item gagal diproses: ' . implode(' | ', array_slice($failed, 0, 5)));
        }

        return redirect()->route('mpp-submissions.index');
    }

    /**
     * Disapprove a single stage in the new MPP approval matrix.
     */
    public function disapproveStage(Request $request, MPPSubmission $mppSubmission)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            abort(403);
        }

        if (($mppSubmission->form_version ?? 'old') !== 'new') {
            return back()->with('error', 'Approval matrix stage hanya tersedia untuk form MPP baru.');
        }

        $validated = $request->validate([
            'stage_index' => 'required|integer|min:0',
        ]);

        $stageIndex = (int) $validated['stage_index'];
        $approvals = $this->getSubmissionApprovals($mppSubmission);
        $targetStage = $approvals->get($stageIndex);

        if (!$targetStage) {
            return back()->with('error', 'Tahapan approval tidak ditemukan.');
        }

        if (!$this->canApproveStage($user, $mppSubmission, $stageIndex, (array) $targetStage)) {
            abort(403);
        }

        $updatedApprovals = $approvals->map(function ($approval, $index) use ($stageIndex, $user) {
            if ($index !== $stageIndex) {
                return $approval;
            }

            $approval['approver_user_id'] = $user->id;
            $approval['name'] = $this->resolveApprovalSignerName($user);
            $approval['decision'] = 'disapproved';
            $approval['digitally_signed'] = false;
            $approval['signature_label'] = 'Digitally Disapproved';
            $approval['signed_at'] = now()->toDateTimeString();

            return $approval;
        })->values()->all();

        $this->updateSubmissionStageDecision($mppSubmission, $updatedApprovals);

        $mppSubmission->approvalHistories()->create([
            'user_id' => $user->id,
            'action' => 'disapproved_stage_' . $stageIndex,
            'notes' => (string) data_get($targetStage, 'role', 'stage'),
        ]);

        return back()->with('success', 'Tahapan approval berhasil ditolak.');
    }

    /**
     * Recalculate overall submission status based on stage decisions.
     */
    private function updateSubmissionStageDecision(MPPSubmission $mppSubmission, array $updatedApprovals): void
    {
        $allApproved = collect($updatedApprovals)->every(function ($item) {
            $decision = strtolower((string) data_get($item, 'decision', ''));

            if ($decision !== '') {
                return $decision === 'approved';
            }

            return (bool) data_get($item, 'digitally_signed');
        });

        $anyDisapproved = collect($updatedApprovals)->contains(function ($item) {
            return strtolower((string) data_get($item, 'decision', '')) === 'disapproved';
        });

        $updatePayload = [];

        if ($allApproved) {
            $updatePayload['status'] = MPPSubmission::STATUS_APPROVED;
            $updatePayload['approved_at'] = now();
            $updatePayload['rejected_at'] = null;
        } elseif ($anyDisapproved) {
            $updatePayload['status'] = MPPSubmission::STATUS_REJECTED;
            $updatePayload['rejected_at'] = now();
            $updatePayload['approved_at'] = null;
        } else {
            $updatePayload['status'] = MPPSubmission::STATUS_SUBMITTED;
            $updatePayload['approved_at'] = null;
            $updatePayload['rejected_at'] = null;
        }

        $mppSubmission->update($updatePayload);
        $this->persistSubmissionApprovals($mppSubmission, $updatedApprovals);

        // Keep new-form single vacancy pivot in sync for downstream modules
        // that depend on mpp_submission_vacancy.proposal_status = approved.
        if (($mppSubmission->form_version ?? 'old') === 'new' && !is_null($mppSubmission->vacancy_id)) {
            $this->upsertNewFormVacancyPivot(
                $mppSubmission,
                (int) $mppSubmission->vacancy_id,
                null
            );
        }
    }

    /**
     * If Div Head and HCD Div Head belong to the same account, approving
     * Div Head should auto-approve HCD Div Head to avoid duplicate actions.
     *
     * @param array<int, array<string, mixed>> $approvals
     * @return array<int, array<string, mixed>>
     */
    private function autoApproveHcdDivHeadWhenSameApprover(array $approvals, int $approvedStageIndex, User $user): array
    {
        $divHeadStageIndex = 2;
        $hcdDivHeadStageIndex = 3;

        if ($approvedStageIndex !== $divHeadStageIndex) {
            return $approvals;
        }

        $divHeadStage = (array) data_get($approvals, $divHeadStageIndex, []);
        $hcdDivHeadStage = (array) data_get($approvals, $hcdDivHeadStageIndex, []);

        if (empty($hcdDivHeadStage)) {
            return $approvals;
        }

        $divHeadApproverId = (int) data_get($divHeadStage, 'approver_user_id', 0);
        $hcdDivHeadApproverId = (int) data_get($hcdDivHeadStage, 'approver_user_id', 0);

        // Only auto-approve when both stages point to the same account.
        if ($divHeadApproverId <= 0 || $hcdDivHeadApproverId <= 0 || $divHeadApproverId !== $hcdDivHeadApproverId) {
            return $approvals;
        }

        if ($hcdDivHeadApproverId !== (int) $user->id) {
            return $approvals;
        }

        $hcdDecision = strtolower((string) data_get($hcdDivHeadStage, 'decision', 'pending'));
        if ($hcdDecision !== 'pending') {
            return $approvals;
        }

        $approvals[$hcdDivHeadStageIndex]['approver_user_id'] = $user->id;
        $approvals[$hcdDivHeadStageIndex]['name'] = $this->resolveApprovalSignerName($user);
        $approvals[$hcdDivHeadStageIndex]['decision'] = 'approved';
        $approvals[$hcdDivHeadStageIndex]['digitally_signed'] = true;
        $approvals[$hcdDivHeadStageIndex]['signature_label'] = 'Digitally Approved';
        $approvals[$hcdDivHeadStageIndex]['signed_at'] = now()->toDateTimeString();

        return array_values($approvals);
    }

    /**
     * Read approval stages from normalized table.
     */
    private function getSubmissionApprovals(MPPSubmission $mppSubmission): \Illuminate\Support\Collection
    {
        $mppSubmission->loadMissing('approvalStages');

        return $mppSubmission->approvalStages
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
            });
    }

    /**
     * Persist approval stages in normalized table.
     */
    private function persistSubmissionApprovals(MPPSubmission $mppSubmission, array $approvals): void
    {
        $normalized = collect($approvals)
            ->values()
            ->map(function ($approval, $index) {
                return [
                    'stage_index' => (int) $index,
                    'role' => (string) data_get($approval, 'role', ''),
                    'required_roles' => array_values((array) data_get($approval, 'required_roles', [])),
                    'name' => data_get($approval, 'name'),
                    'approver_user_id' => data_get($approval, 'approver_user_id'),
                    'decision' => (string) data_get($approval, 'decision', 'pending'),
                    'digitally_signed' => (bool) data_get($approval, 'digitally_signed', false),
                    'signature_label' => data_get($approval, 'signature_label'),
                    'signed_at' => data_get($approval, 'signed_at'),
                ];
            })
            ->values();

        foreach ($normalized as $stage) {
            $mppSubmission->approvalStages()->updateOrCreate(
                ['stage_index' => (int) $stage['stage_index']],
                [
                    'role' => $stage['role'],
                    'required_roles' => $stage['required_roles'],
                    'name' => $stage['name'],
                    'approver_user_id' => $stage['approver_user_id'],
                    'decision' => $stage['decision'],
                    'digitally_signed' => $stage['digitally_signed'],
                    'signature_label' => $stage['signature_label'],
                    'signed_at' => $stage['signed_at'],
                ]
            );
        }

        $mppSubmission->approvalStages()
            ->whereNotIn('stage_index', $normalized->pluck('stage_index')->all())
            ->delete();

        $mppSubmission->load('approvalStages');
    }

    /**
     * Sync new-form MPP custom jabatan to a vacancy from master data.
     */
    public function syncVacancy(Request $request, MPPSubmission $mppSubmission)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasAnyRole(['team_hc', 'team_hc_2', 'super_admin'])) {
            abort(403);
        }

        if (($mppSubmission->form_version ?? 'old') !== 'new') {
            return back()->with('error', 'Sinkronisasi vacancy hanya untuk form MPP baru.');
        }

        $validated = $request->validate([
            'vacancy_id' => [
                'required',
                Rule::exists('vacancies', 'id')->where(function ($query) use ($mppSubmission) {
                    $query->where('department_id', $mppSubmission->department_id)
                        ->where('is_active', true);
                }),
            ],
        ]);

        $selectedVacancyId = (int) $validated['vacancy_id'];

        DB::transaction(function () use ($mppSubmission, $selectedVacancyId) {
            $mppSubmission->update([
                'vacancy_id' => $selectedVacancyId,
                'custom_jabatan_name' => null,
            ]);

            $this->upsertNewFormVacancyPivot(
                $mppSubmission,
                $selectedVacancyId,
                null
            );
        });

        return back()->with('success', 'Jabatan MPP berhasil disinkronkan ke master data vacancy.');
    }

    /**
     * Map overall MPP status to pivot proposal_status used across vacancy flows.
     */
    private function mapProposalStatusFromMppStatus(string $status): string
    {
        return match ($status) {
            MPPSubmission::STATUS_APPROVED => 'approved',
            MPPSubmission::STATUS_REJECTED => 'rejected',
            default => 'pending',
        };
    }

    /**
     * Ensure new-form submission vacancy is represented on mpp_submission_vacancy.
     */
    private function upsertNewFormVacancyPivot(MPPSubmission $mppSubmission, int $vacancyId, ?string $vacancyStatus): void
    {
        $normalizedVacancyStatus = strtoupper(trim((string) $vacancyStatus));
        if (!in_array($normalizedVacancyStatus, ['OSPKWT', 'OS'], true)) {
            $normalizedVacancyStatus = 'OSPKWT';
        }

        $attributes = [
            'vacancy_status' => $normalizedVacancyStatus,
            'needed_count' => (int) ($mppSubmission->jumlah_diminta ?? 0),
            'proposal_status' => $this->mapProposalStatusFromMppStatus((string) $mppSubmission->status),
            'proposed_by_user_id' => $mppSubmission->created_by_user_id,
        ];

        $pivotExists = DB::table('mpp_submission_vacancy')
            ->where('m_p_p_submission_id', $mppSubmission->id)
            ->where('vacancy_id', $vacancyId)
            ->exists();

        if ($pivotExists) {
            $mppSubmission->vacancies()->updateExistingPivot($vacancyId, $attributes);
            return;
        }

        $mppSubmission->vacancies()->attach([
            $vacancyId => $attributes,
        ]);
    }

    /**
     * Approve a specific vacancy within an MPP
     */
    public function approveVacancy(Request $request, MPPSubmission $mppSubmission, Vacancy $vacancy)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            abort(403);
        }

        if (!$user->can('approve-mpp-submission')) {
            abort(403);
        }

        $pivot = $mppSubmission->vacancies()->where('vacancy_id', $vacancy->id)->first()->pivot;
        $message = 'Posisi berhasil disetujui.';

        // Define constants for proposal statuses
        $STATUS_PENDING = 'pending';
        $STATUS_PENDING_HC2_APPROVAL = 'pending_hc2_approval';
        $STATUS_APPROVED = 'approved';

        // Two-step approval logic
        if ($user->hasRole('team_hc')) {
            if ($pivot->proposal_status === $STATUS_PENDING) {
                $pivot->update(['proposal_status' => $STATUS_PENDING_HC2_APPROVAL]);
                $message = 'Posisi disetujui oleh Team HC 1 dan menunggu approval dari Team HC 2.';
            } else {
                return back()->with('error', 'Status approval tidak valid untuk aksi ini.');
            }
        } elseif ($user->hasRole('team_hc_2')) {
            if ($pivot->proposal_status === $STATUS_PENDING_HC2_APPROVAL) {
                $pivot->update(['proposal_status' => $STATUS_APPROVED]);
                $message = 'Posisi berhasil disetujui sepenuhnya.';

                $newType = null;
                if ($pivot->vacancy_status === 'OSPKWT') {
                    $newType = 'Yes'; // Organic
                } elseif ($pivot->vacancy_status === 'OS') {
                    $newType = 'No'; // Non-Organic
                }

                if ($newType) {
                    $candidateIds = $vacancy->applications()->pluck('candidate_id');
                    if ($candidateIds->isNotEmpty()) {
                        DB::table('candidates')->whereIn('id', $candidateIds)->update(['airsys_internal' => $newType]);
                    }
                }
            } else {
                return back()->with('error', 'Posisi ini belum disetujui oleh Team HC 1.');
            }
        } else {
            $pivot->update(['proposal_status' => $STATUS_APPROVED]);
        }

        $this->updateMPPStatus($mppSubmission);

        return back()->with('success', $message);
    }

    /**
     * Reject a specific vacancy within an MPP
     */
    public function rejectVacancy(Request $request, MPPSubmission $mppSubmission, Vacancy $vacancy)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            abort(403);
        }

        if (!$user->can('reject-mpp-submission')) {
            abort(403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $mppSubmission->vacancies()->updateExistingPivot($vacancy->id, [
            'proposal_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $this->updateMPPStatus($mppSubmission);

        return back()->with('success', 'Posisi ditolak.');
    }

    /**
     * Update the overall status of the MPP Submission based on its vacancies
     */
    private function updateMPPStatus(MPPSubmission $mppSubmission)
    {
        $mppSubmission->load('vacancies');
        $vacancies = $mppSubmission->vacancies;

        $pendingCount = $vacancies->filter(function ($v) {
            return in_array($v->pivot->proposal_status, ['pending', 'pending_hc2_approval', null]);
        })->count();

        if ($pendingCount === 0) {
            // All vacancies have been processed
            $approvedCount = $vacancies->where('pivot.proposal_status', 'approved')->count();

            if ($approvedCount > 0) {
                $mppSubmission->update([
                    'status' => MPPSubmission::STATUS_APPROVED,
                    'approved_at' => now(),
                ]);
            } else {
                // If no approved vacancies (meaning all are rejected)
                $mppSubmission->update([
                    'status' => MPPSubmission::STATUS_REJECTED,
                    'rejected_at' => now(),
                ]);
            }
        } else {
            // If there are still pending vacancies, ensure status is submitted (in case it was somehow changed)
            if ($mppSubmission->status !== MPPSubmission::STATUS_SUBMITTED) {
                $mppSubmission->update([
                    'status' => MPPSubmission::STATUS_SUBMITTED,
                ]);
            }
        }
    }

    /**
     * Determine which stage indexes can be approved by the current user.
     *
     * @return array<int>
     */
    private function getApprovableStageIndexes(User $user, MPPSubmission $mppSubmission): array
    {
        if (($mppSubmission->form_version ?? 'old') !== 'new') {
            return [];
        }

        return $this->getSubmissionApprovals($mppSubmission)
            ->values()
            ->filter(function ($approval, $index) use ($user, $mppSubmission) {
                return $this->canApproveStage($user, $mppSubmission, $index, (array) $approval);
            })
            ->keys()
            ->map(fn($index) => (int) $index)
            ->values()
            ->all();
    }

    /**
     * Check whether user can approve specific stage based on approval matrix rules.
     */
    private function canApproveStage(User $user, MPPSubmission $mppSubmission, int $stageIndex, array $stageData): bool
    {
        $approvals = $this->getSubmissionApprovals($mppSubmission);
        $currentDecision = strtolower((string) data_get($stageData, 'decision', 'pending'));
        $isPendingStage = $currentDecision === 'pending';

        if ($this->isTestingSuperApprover($user)) {
            return true;
        }

        // Wave-based gating:
        // Wave 1 (parallel): Diminta, Dept Head, Div Head
        // Wave 2 (parallel): HCD Div Head, HCD Dept Head, PIC Recruitment
        // Wave 3 (parallel): Executive 1/2/3
        // A wave opens only after all stages in earlier waves are approved.
        $currentWave = $this->resolveApprovalWave($stageData);

        if ($isPendingStage && $currentWave > 1) {
            $hasUnapprovedEarlierWave = $approvals->contains(function ($approval) use ($currentWave) {
                $wave = $this->resolveApprovalWave((array) $approval);
                if ($wave >= $currentWave) {
                    return false;
                }

                return strtolower((string) data_get($approval, 'decision', 'pending')) !== 'approved';
            });

            if ($hasUnapprovedEarlierWave) {
                return false;
            }
        }

        // Revision rule: once decided, it can still be changed by the same approver
        // only while no later-wave stage has started processing.
        if (!$isPendingStage) {
            $hasStartedLaterWave = $approvals->contains(function ($approval) use ($currentWave) {
                $wave = $this->resolveApprovalWave((array) $approval);
                if ($wave <= $currentWave) {
                    return false;
                }

                return strtolower((string) data_get($approval, 'decision', 'pending')) !== 'pending';
            });

            if ($hasStartedLaterWave) {
                return false;
            }
        }

        $expectedApproverId = (int) data_get($stageData, 'approver_user_id', 0);
        $email = strtolower((string) $user->email);
        $roleLabel = strtolower((string) data_get($stageData, 'role', ''));
        $isHcdDivHeadStage = str_contains($roleLabel, 'hcd div head');
        $isHcdDeptHeadStage = str_contains($roleLabel, 'hcd dept head');
        $isPicRecruitmentStage = str_contains($roleLabel, 'pic recruitment');
        $isExecutiveOneStage = str_contains($roleLabel, 'executive 1');
        $isExecutiveTwoStage = str_contains($roleLabel, 'executive 2');
        $isExecutiveThreeStage = str_contains($roleLabel, 'executive 3');
        $requiredRoles = collect(data_get($stageData, 'required_roles', []))
            ->filter(fn($role) => is_string($role) && trim($role) !== '')
            ->values()
            ->all();
        $hasRequiredRole = !empty($requiredRoles) && $user->hasAnyRole($requiredRoles);
        $mustMatchSignedApprover = !$isPendingStage;

        if ($isExecutiveThreeStage || (empty($roleLabel) && $stageIndex === 8 && in_array('dic_approver', $requiredRoles, true))) {
            if ($expectedApproverId > 0) {
                return $expectedApproverId === (int) $user->id;
            }

            if ($mustMatchSignedApprover) {
                return false;
            }

            return $hasRequiredRole || $email === 'dic.ms.engineering.scm@pmp.local';
        }

        if ($isHcdDivHeadStage) {
            if ($expectedApproverId > 0) {
                // Allow fallback by role/email when legacy approver_user_id snapshots
                // do not match the active HCD Div Head account.
                return $expectedApproverId === (int) $user->id
                    || $hasRequiredRole
                    || $email === 'division.fa.hcga@pmp.local';
            }

            if ($mustMatchSignedApprover) {
                return false;
            }

            return $hasRequiredRole || $email === 'division.fa.hcga@pmp.local';
        }

        if ($isHcdDeptHeadStage) {
            if ($expectedApproverId > 0) {
                return $expectedApproverId === (int) $user->id;
            }

            if ($mustMatchSignedApprover) {
                return false;
            }

            return $hasRequiredRole || $email === 'head-hcgaesrit@airsys.com';
        }

        if ($isPicRecruitmentStage) {
            if ($expectedApproverId > 0) {
                return $expectedApproverId === (int) $user->id;
            }

            if ($mustMatchSignedApprover) {
                return false;
            }

            return $hasRequiredRole || $email === 'hc2@pmp.com';
        }

        if ($isExecutiveOneStage) {
            if ($expectedApproverId > 0) {
                return $expectedApproverId === (int) $user->id;
            }

            if ($mustMatchSignedApprover) {
                return false;
            }

            return $hasRequiredRole || $email === 'rimba.kusumadilaga@pmp.local';
        }

        if ($isExecutiveTwoStage) {
            if ($expectedApproverId > 0) {
                return $expectedApproverId === (int) $user->id;
            }

            if ($mustMatchSignedApprover) {
                return false;
            }

            return $hasRequiredRole || $email === 'teguh.patmuryanto@pmp.local';
        }

        return match ($stageIndex) {
            // Div Head of the requester's division.
            2 => $expectedApproverId > 0
            ? (
                $expectedApproverId === (int) $user->id
                || (!$mustMatchSignedApprover && (
                    (
                        $hasRequiredRole
                        && $user->hasDepartmentAccess((int) $mppSubmission->department_id)
                    )
                    || (
                        empty($requiredRoles)
                        && $user->hasRole('division_head')
                        && $user->hasDepartmentAccess((int) $mppSubmission->department_id)
                    )
                    || $email === 'division.fa.hcga@pmp.local'
                ))
            )
            : (!$mustMatchSignedApprover && (
                (
                    $hasRequiredRole
                    && $user->hasDepartmentAccess((int) $mppSubmission->department_id)
                )
                || (
                    empty($requiredRoles)
                    && $user->hasRole('division_head')
                    && $user->hasDepartmentAccess((int) $mppSubmission->department_id)
                )
            )),

            // HCD Div Head role-based with legacy email fallback.
            3 => $expectedApproverId > 0
            ? $expectedApproverId === (int) $user->id
            : (!$mustMatchSignedApprover && ($hasRequiredRole || $email === 'division.fa.hcga@pmp.local')),

            // HCD Dept Head role-based with legacy email fallback.
            4 => $expectedApproverId > 0
            ? $expectedApproverId === (int) $user->id
            : (!$mustMatchSignedApprover && ($hasRequiredRole || $email === 'head-hcgaesrit@airsys.com')),

            // PIC Recruitment role-based with legacy email fallback.
            5 => $expectedApproverId > 0
            ? $expectedApproverId === (int) $user->id
            : (!$mustMatchSignedApprover && ($hasRequiredRole || $email === 'hc2@pmp.com')),

            // Executive approvals: role-based (executive) with legacy email fallback.
            6 => $expectedApproverId > 0
            ? $expectedApproverId === (int) $user->id
            : (!$mustMatchSignedApprover && ($hasRequiredRole || $email === 'rimba.kusumadilaga@pmp.local')),

            7 => $expectedApproverId > 0
            ? $expectedApproverId === (int) $user->id
            : (!$mustMatchSignedApprover && ($hasRequiredRole || $email === 'teguh.patmuryanto@pmp.local')),

            default => false,
        };
    }

    /**
     * Resolve signer display name used in MPP approval snapshots.
     */
    private function resolveApprovalSignerName(User $user): string
    {
        return trim((string) ($user->approval_display_name ?: $user->name));
    }

    /**
     * Executive queue is restricted to these fixed approval accounts.
     */
    private function isExecutiveApprovalAccount(User $user): bool
    {
        $email = strtolower((string) $user->email);

        return in_array($email, [
            'rimba.kusumadilaga@pmp.local',
            'teguh.patmuryanto@pmp.local',
            'dic.ms.engineering.scm@pmp.local',
        ], true);
    }

    /**
     * For executive accounts, only show queue rows when all non-executive
     * approvals are already approved and the pending executive stage belongs
     * to the current user.
     *
     * @return array{stage_index:int,stage_role:string,stage_decision:string}|null
     */
    private function resolveExecutiveQueueStage(User $user, MPPSubmission $submission): ?array
    {
        $approvals = $this->getSubmissionApprovals($submission);
        if ($approvals->isEmpty()) {
            return null;
        }

        if (!$this->hasApprovedNonExecutiveStages($approvals)) {
            return null;
        }

        foreach ($approvals as $index => $approval) {
            $stage = (array) $approval;

            if (!$this->isExecutiveStage($stage)) {
                continue;
            }

            $decision = strtolower((string) data_get($stage, 'decision', 'pending'));
            if ($decision !== 'pending') {
                continue;
            }

            if (!$this->isExecutiveStageOwnedByUser($user, $stage)) {
                continue;
            }

            return [
                'stage_index' => (int) $index,
                'stage_role' => (string) data_get($stage, 'role', '-'),
                'stage_decision' => (string) data_get($stage, 'decision', 'pending'),
            ];
        }

        return null;
    }

    /**
     * Required non-executive roles that must already be approved before
     * executive queue rows are shown.
     */
    private function hasApprovedNonExecutiveStages(\Illuminate\Support\Collection $approvals): bool
    {
        $requiredLabels = [
            'Diminta Oleh',
            'Diketahui Oleh Dept. Head',
            'Diketahui Oleh Div. Head',
            'Disetujui Oleh HCD Div Head',
            'Diketahui Oleh HCD Dept Head',
            'Diterima Oleh PIC Recruitment',
        ];

        foreach ($requiredLabels as $label) {
            $stage = $approvals->first(function ($approval) use ($label) {
                return $this->normalizeRoleLabel((string) data_get($approval, 'role', ''))
                    === $this->normalizeRoleLabel($label);
            });

            if (!$stage) {
                return false;
            }

            if (strtolower((string) data_get($stage, 'decision', 'pending')) !== 'approved') {
                return false;
            }
        }

        return true;
    }

    private function isExecutiveStage(array $stage): bool
    {
        $role = strtolower((string) data_get($stage, 'role', ''));
        $requiredRoles = collect((array) data_get($stage, 'required_roles', []))
            ->map(fn($item) => strtolower(trim((string) $item)))
            ->filter();

        if (str_contains($role, 'executive')) {
            return true;
        }

        return $requiredRoles->contains('executive') || $requiredRoles->contains('dic_approver');
    }

    private function isExecutiveStageOwnedByUser(User $user, array $stage): bool
    {
        $expectedApproverId = (int) data_get($stage, 'approver_user_id', 0);
        if ($expectedApproverId > 0) {
            return $expectedApproverId === (int) $user->id;
        }

        $email = strtolower((string) $user->email);
        $role = strtolower((string) data_get($stage, 'role', ''));

        if (str_contains($role, 'executive 1')) {
            return $email === 'rimba.kusumadilaga@pmp.local';
        }

        if (str_contains($role, 'executive 2')) {
            return $email === 'teguh.patmuryanto@pmp.local';
        }

        if (str_contains($role, 'executive 3')) {
            return $email === 'dic.ms.engineering.scm@pmp.local';
        }

        return false;
    }

    private function normalizeRoleLabel(string $value): string
    {
        $lowered = strtolower(trim($value));

        return (string) preg_replace('/[^a-z0-9]+/', '', $lowered);
    }

    /**
     * Resolve approval wave from role label / required roles.
     * Wave 1: requester/dept/div
     * Wave 2: hcd div, hcd dept, pic recruitment
     * Wave 3: executive 1/2/3
     */
    private function resolveApprovalWave(array $stage): int
    {
        $normalizedRole = $this->normalizeRoleLabel((string) data_get($stage, 'role', ''));
        $requiredRoles = collect((array) data_get($stage, 'required_roles', []))
            ->map(fn($item) => strtolower(trim((string) $item)))
            ->filter();

        if (
            str_contains($normalizedRole, 'executive')
            || $requiredRoles->contains('executive')
            || $requiredRoles->contains('dic_approver')
        ) {
            return 3;
        }

        if (
            str_contains($normalizedRole, 'hcddivhead')
            || str_contains($normalizedRole, 'hcddepthead')
            || str_contains($normalizedRole, 'picrecruitment')
            || $requiredRoles->contains('hcd_div_head')
            || $requiredRoles->contains('hcd_dept_head')
            || $requiredRoles->contains('pic_recruitment')
            || $requiredRoles->contains('team_hc_2')
        ) {
            return 2;
        }

        // Default to wave 1 for requester/dept/div stages.
        return 1;
    }

    /**
     * HCD Dept Head should be able to view all MPP submissions.
     */
    private function isGlobalHcdDeptHeadViewer(User $user): bool
    {
        if ($user->hasAnyRole(['hcd_div_head', 'hcd_dept_head'])) {
            return true;
        }

        $email = strtolower((string) $user->email);

        return in_array($email, [
            'head-hcgaesrit@airsys.com',
            'division.fa.hcga@pmp.local',
        ], true);
    }

    /**
     * Old MPP form can only be accessed by this fixed account whitelist.
     */
    private function canAccessOldFormSubmission(User $user): bool
    {
        $email = strtolower((string) $user->email);

        return in_array($email, [
            'admin@pmp.com',
            'hc1@pmp.com',
            'hc2@pmp.com',
            'admin@airsys.com',
            'hc1@airsys.com',
            'hc2@airsys.com',
        ], true);
    }

    /**
     * Accounts that can create both old and new MPP forms.
     */
    private function canCreateBothMppForms(User $user): bool
    {
        $email = strtolower((string) $user->email);

        return in_array($email, [
            'admin@pmp.com',
            'hc1@pmp.com',
            'hc2@pmp.com',
            '1@pmp.com',
            'admin@airsys.com',
            'hc1@airsys.com',
            'hc2@airsys.com',
        ], true);
    }

    /**
     * Accounts that can create new MPP form only.
     */
    private function canCreateNewOnlyMppForm(User $user): bool
    {
        $email = strtolower((string) $user->email);

        return in_array($email, [
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
        ], true);
    }

    /**
     * Accounts explicitly blocked from creating any MPP form.
     */
    private function isMppCreationBlocked(User $user): bool
    {
        $email = strtolower((string) $user->email);

        return in_array($email, [
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
        ], true);
    }

    /**
     * New-form creation gate by explicit email policy.
     */
    private function canCreateNewFormMpp(User $user): bool
    {
        if ($this->isMppCreationBlocked($user)) {
            return false;
        }

        return $this->canCreateBothMppForms($user)
            || $this->canCreateNewOnlyMppForm($user);
    }

    /**
     * Old-form creation gate by explicit email policy.
     */
    private function canCreateOldFormMpp(User $user): bool
    {
        if ($this->isMppCreationBlocked($user)) {
            return false;
        }

        return $this->canCreateBothMppForms($user);
    }

    /**
     * Dedicated testing account that can approve/disapprove any approval stage.
     */
    private function isTestingSuperApprover(User $user): bool
    {
        return strtolower((string) $user->email) === 'superadmin@gmail.com';
    }

    /**
     * Delete the MPP
     */
    public function destroy(MPPSubmission $mppSubmission)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            abort(403);
        }

        if (!$user->can('delete-mpp-submission')) {
            abort(403);
        }

        $vacancyIds = $mppSubmission->vacancies()->pluck('vacancies.id');

        // New-form MPP stores a single selected vacancy on mpp_submissions.vacancy_id.
        if (!is_null($mppSubmission->vacancy_id)) {
            $vacancyIds->push((int) $mppSubmission->vacancy_id);
        }

        $vacancyIds = $vacancyIds
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($vacancyIds->isNotEmpty()) {
            $relatedApplicationsCount = DB::table('applications')
                ->whereIn('vacancy_id', $vacancyIds)
                ->where('mpp_year', $mppSubmission->year)
                ->count();

            if ($relatedApplicationsCount > 0) {
                return redirect()->route('mpp-submissions.index')
                    ->with('error', 'MPP submission tidak dapat dihapus karena sudah memiliki kandidat.');
            }
        }

        // Detach all vacancies from the submission
        $mppSubmission->vacancies()->detach();

        $mppSubmission->delete();

        return redirect()->route('mpp-submissions.index')
            ->with('success', 'MPP submission deleted successfully');
    }
}
