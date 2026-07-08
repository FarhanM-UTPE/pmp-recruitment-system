<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Vacancy;
use App\Models\CandidateEditHistory;
use App\Services\ApplicationStageService;
use App\Enums\RecruitmentStage;
use App\Exports\CandidatesExport;
use App\Models\MPPSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class CandidateController extends Controller
{
  /**
   * =========================
   * UPDATE APPLICATION STAGE
   * =========================
   */
  public function updateStage(Request $request, Application $application, ApplicationStageService $stageService): JsonResponse
  {
    $updateDateOnly = $request->input('update_date_only', false);

    $rules = [
      'stage' => 'required|string',
      'notes' => 'nullable|string',
      'stage_date' => 'nullable|date',
      'next_stage_date' => 'nullable|date',
      'update_date_only' => 'nullable|boolean',
      'result' => $updateDateOnly ? 'nullable|string' : 'required|string',
    ];

    $validated = $request->validate($rules);

    try {
      $stageService->processStageUpdate($application, $validated);
      return response()->json(['message' => 'Stage updated successfully.']);
    } catch (\Exception $e) {
      Log::error('Error updating stage: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
      ]);
      return response()->json(['message' => 'Error updating stage: ' . $e->getMessage()], 500);
    }
  }

  /**
   * =========================
   * RESET APPLICATION STAGE
   * =========================
   */
  public function resetStage(Request $request, Application $application, ApplicationStageService $stageService): JsonResponse
  {
    $validated = $request->validate([
      'stage' => 'required|string',
    ]);

    try {
      $stageService->resetStage($application, $validated['stage']);
      return response()->json(['message' => 'Tahap berhasil di-reset.']);
    } catch (\Exception $e) {
      Log::error('Error resetting stage: ' . $e->getMessage());
      return response()->json(['message' => 'Gagal me-reset tahap: ' . $e->getMessage()], 500);
    }
  }

  /**
   * =========================
   * CANCEL POSITION MOVE
   * =========================
   */
  public function cancelMove(Application $application)
  {
    if (!Auth::user()->hasRole('team_hc_2')) {
      return response()->json(['message' => 'Unauthorized. Only HC 2 can cancel moves.'], 403);
    }

    if ($application->overall_status !== 'PINDAH') {
      return response()->json(['message' => 'Hanya aplikasi dengan status PINDAH yang dapat dibatalkan.'], 400);
    }

    try {
      DB::beginTransaction();

      $nextApplication = Application::where('candidate_id', $application->candidate_id)
        ->where('id', '>', $application->id)
        ->orderBy('id', 'asc')
        ->first();

      if ($nextApplication) {
        $nextApplication->stages()->delete();
        $nextApplication->delete();
      }

      $application->update([
        'overall_status' => 'PROSES',
        'internal_position' => null
      ]);

      $candidate = $application->candidate;
      if ($application->vacancy) {
        $candidate->department_id = $application->vacancy->department_id;

        $mpp = $application->vacancy->mppSubmissions()
          ->where('year', $application->mpp_year)
          ->where('proposal_status', 'approved')
          ->first();
        if ($mpp) {
          $candidate->airsys_internal = ($mpp->pivot->vacancy_status === 'OSPKWT') ? 'Yes' : 'No';
        }
        $candidate->save();
      }

      DB::commit();
      return response()->json(['message' => 'Perpindahan posisi berhasil dibatalkan. Aplikasi sebelumnya telah dipulihkan.']);
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Error canceling move: ' . $e->getMessage());
      return response()->json(['message' => 'Gagal membatalkan perpindahan: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Show the form for creating a new candidate
   */
  // public function create()
  // {
  //     $vacancies = Vacancy::whereHas('mppSubmissions', function ($q) {
  //         $q->where('proposal_status', 'approved');
  //     })->orderBy('name')->get();
  //     $departments = Department::orderBy('name')->get();

  //     $today = now()->format('ymd');
  //     $todayCount = Candidate::whereDate('created_at', today())->count();
  //     $nextId = str_pad($todayCount + 1, 3, '0', STR_PAD_LEFT);
  //     $applicantId = "{$today}-{$nextId}";

  //     return view('candidates.create', compact('vacancies', 'departments', 'applicantId'));
  // }

  public function create()
  {
    // Include vacancies approved through both old-form (pivot) and new-form (direct vacancy_id) MPP flows.
    $vacancies = Vacancy::where(function ($query) {
      $query->whereHas('mppSubmissions', function ($q) {
        $q->where('proposal_status', 'approved')
          ->whereNull('mpp_submissions.deleted_at');
      })->orWhereHas('directMppSubmissions', function ($q) {
        $q->where('status', MPPSubmission::STATUS_APPROVED)
          ->where('form_version', 'new')
          ->whereNull('deleted_at');
      });
    })
      ->with([
        'mppSubmissions' => function ($q) {
          $q->where('proposal_status', 'approved')
            ->whereNull('mpp_submissions.deleted_at');
        },
        'directMppSubmissions' => function ($q) {
          $q->where('status', MPPSubmission::STATUS_APPROVED)
            ->where('form_version', 'new')
            ->whereNull('deleted_at');
        },
      ])
      ->orderBy('name')
      ->get();

    $vacancies->each(function ($vacancy) {
      $oldSubmissions = collect($vacancy->mppSubmissions ?? []);
      $newSubmissions = collect($vacancy->directMppSubmissions ?? []);

      $mergedSubmissions = $oldSubmissions
        ->concat($newSubmissions)
        ->sortByDesc('year')
        ->unique(function ($item) {
          return (string) data_get($item, 'year') . '|' . (string) data_get($item, 'submission_type');
        })
        ->values();

      $vacancy->setRelation('mppSubmissions', $mergedSubmissions);
      $vacancy->unsetRelation('directMppSubmissions');
    });

    $departments = Department::orderBy('name')->get();

    $today = now()->format('ymd');
    $todayCount = Candidate::whereDate('created_at', today())->count();
    $nextId = str_pad($todayCount + 1, 3, '0', STR_PAD_LEFT);
    $applicantId = "{$today}-{$nextId}";

    return view('candidates.create', compact('vacancies', 'departments', 'applicantId'));
  }

  public function store(Request $request)
  {
    $validated = $request->validate([
      'nama' => 'required|string|max:255',
      'alamat_email' => 'required|email|unique:candidates,alamat_email',
      'applicant_id' => 'required|string|unique:candidates,applicant_id',
      'jk' => 'nullable|string',
      'tanggal_lahir' => 'nullable|date',
      'vacancy_id' => 'required|exists:vacancies,id',
      'mpp_year' => 'nullable|integer',
      'jenjang_pendidikan' => 'nullable|string|max:100',
      'perguruan_tinggi' => 'nullable|string|max:100',
      'jurusan' => 'nullable|string|max:100',
      'ipk' => 'nullable|numeric|min:0|max:4',
      'cv' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
      'flk' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
    ]);

    try {
      DB::beginTransaction();

      $vacancy = Vacancy::findOrFail($validated['vacancy_id']);
      $airsysInternal = null;

      if (!empty($validated['mpp_year'])) {
        $mppSubmission = $vacancy->mppSubmissions()
          ->where('year', $validated['mpp_year'])
          ->where('proposal_status', 'approved')
          ->first();

        if ($mppSubmission) {
          $vacancyStatus = $mppSubmission->pivot->vacancy_status;
          $airsysInternal = ($vacancyStatus === 'OSPKWT') ? 'Yes' : (($vacancyStatus === 'OS') ? 'No' : null);
        } else {
          $newFormMpp = $vacancy->directMppSubmissions()
            ->where('year', $validated['mpp_year'])
            ->where('status', MPPSubmission::STATUS_APPROVED)
            ->where('form_version', 'new')
            ->first();

          if ($newFormMpp) {
            $airsysInternal = $newFormMpp->submission_type === 'planned' ? 'Yes' : 'No';
          }
        }
      }

      $data = [
        'nama' => $validated['nama'],
        'alamat_email' => $validated['alamat_email'],
        'applicant_id' => $validated['applicant_id'],
        'source' => 'Airsys',
        'jk' => $validated['jk'] ?? null,
        'tanggal_lahir' => $validated['tanggal_lahir'],
        'department_id' => $vacancy->department_id,
        'airsys_internal' => $airsysInternal,
        'jenjang_pendidikan' => $validated['jenjang_pendidikan'],
        'perguruan_tinggi' => $validated['perguruan_tinggi'],
        'jurusan' => $validated['jurusan'],
        'ipk' => $validated['ipk'],
      ];

      if ($request->hasFile('cv')) {
        $data['cv'] = $request->file('cv')->store('candidate-files', 'public');
      }

      if ($request->hasFile('flk')) {
        $data['flk'] = $request->file('flk')->store('candidate-files', 'public');
      }

      $candidate = Candidate::create($data);

      if (isset($validated['mpp_year'])) {
        $candidate->mpp_year = $validated['mpp_year'];
        $candidate->save();
      }

      Application::create([
        'candidate_id' => $candidate->id,
        'vacancy_id' => $vacancy->id,
        'department_id' => $vacancy->department_id,
        'mpp_year' => $validated['mpp_year'] ?? null,
        'overall_status' => 'PROSES',
      ]);

      DB::commit();
      return redirect()->route('candidates.show', $candidate)->with('success', 'Candidate created successfully.');
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Error creating candidate: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
      ]);
      return back()->withInput()->with('error', 'There was an error creating the candidate. Please try again.');
    }
  }

  /**
   * Show the form for editing a candidate
   */
  public function edit(Candidate $candidate)
  {
    $vacancies = Vacancy::where(function ($query) {
      $query->whereHas('mppSubmissions', function ($q) {
        $q->where('proposal_status', 'approved')
          ->whereNull('mpp_submissions.deleted_at');
      })->orWhereHas('directMppSubmissions', function ($q) {
        $q->where('status', MPPSubmission::STATUS_APPROVED)
          ->where('form_version', 'new')
          ->whereNull('deleted_at');
      });
    })->orderBy('name')->get();
    $departments = Department::orderBy('name')->get();
    $editHistories = $candidate->editHistories()->with('user')->orderBy('created_at', 'desc')->get();

    return view('candidates.edit', compact('candidate', 'departments', 'vacancies', 'editHistories'));
  }

  /**
   * Update a candidate
   */
  public function update(Request $request, Candidate $candidate)
  {
    $validated = $request->validate([
      'nama' => 'required|string|max:255',
      'alamat_email' => 'required|email|unique:candidates,alamat_email,' . $candidate->id,
      'applicant_id' => 'required|string',
      'jk' => 'nullable|string',
      'tanggal_lahir' => 'nullable|date',
      'jenjang_pendidikan' => 'nullable|string|max:100',
      'perguruan_tinggi' => 'nullable|string|max:100',
      'jurusan' => 'nullable|string|max:100',
      'ipk' => 'nullable|numeric|min:0|max:4',
      'cv' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
      'flk' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
    ]);

    try {
      DB::beginTransaction();

      $data = [
        'nama' => $validated['nama'],
        'alamat_email' => $validated['alamat_email'],
        'jk' => $validated['jk'] ?? null,
        'tanggal_lahir' => $validated['tanggal_lahir'],
        'jenjang_pendidikan' => $validated['jenjang_pendidikan'],
        'perguruan_tinggi' => $validated['perguruan_tinggi'],
        'jurusan' => $validated['jurusan'],
        'ipk' => $validated['ipk'],
      ];

      if ($request->hasFile('cv')) {
        if ($candidate->cv) {
          Storage::disk('public')->delete($candidate->cv);
        }
        $data['cv'] = $request->file('cv')->store('candidate-files', 'public');
      }

      if ($request->hasFile('flk')) {
        if ($candidate->flk) {
          Storage::disk('public')->delete($candidate->flk);
        }
        $data['flk'] = $request->file('flk')->store('candidate-files', 'public');
      }

      $originalData = $candidate->fresh()->getAttributes();
      $candidate->update($data);

      $changes = $candidate->getChanges();
      $historyChanges = [];

      if (!empty($changes)) {
        foreach ($changes as $key => $value) {
          if ($key !== 'updated_at') {
            $historyChanges[$key] = [
              'old' => $originalData[$key] ?? null,
              'new' => $value,
            ];
          }
        }
      }

      if (!empty($historyChanges)) {
        CandidateEditHistory::create([
          'candidate_id' => $candidate->id,
          'user_id' => Auth::id(),
          'changes' => $historyChanges,
        ]);
      }

      DB::commit();
      return redirect()->route('candidates.show', $candidate)->with('success', 'Candidate updated successfully.');
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Error updating candidate: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
      ]);
      return back()->withInput()->with('error', 'There was an error updating the candidate. Please try again.');
    }
  }

  /**
   * Delete a candidate
   */
  public function destroy(Candidate $candidate)
  {
    $candidate->delete();
    return redirect()->route('candidates.index')->with('success', 'Candidate deleted successfully.');
  }

  /**
   * ======================================
   * CANDIDATE LIST & PIPELINE FUNNEL DATA
   * ======================================
   */
  public function index(Request $request)
  {
    $user = Auth::user();

    // Default filter for department scoped roles: stage=user_interview
    if ($user->hasAnyRole(['kepala departemen', 'division_head']) && !$request->has('stage')) {
      $queryParams = $request->query();
      $queryParams['stage'] = 'user_interview';

      return redirect()->route('candidates.index', $queryParams);
    }

    $type = $request->input('type');

    $hasFilters = $request->anyFilled(['year', 'vacancy_id', 'type', 'search', 'department_id', 'source', 'status', 'stage']) || $request->boolean('not_moved');

    // 1. Prepare Filter Options
    $years = \App\Services\YearProvider::availableYears();
    $selectedYear = $request->input('year');

    // 2. Base Query Formulation
    $query = Application::query()
      ->select('applications.*')
      ->join('candidates', 'applications.candidate_id', '=', 'candidates.id')
      ->join('vacancies', 'applications.vacancy_id', '=', 'vacancies.id')
      ->with([
        'candidate.department',
        'candidate.latestPsikotest',
        'candidate.latestHCInterview',
        'vacancy',
        'stages',
      ]);

    $statsQuery = Application::query()
      ->join('candidates', 'applications.candidate_id', '=', 'candidates.id')
      ->join('vacancies', 'applications.vacancy_id', '=', 'vacancies.id');

    // 3. Evaluation Global Filters
    if ($selectedYear) {
      $query->where('applications.mpp_year', $selectedYear);
      $statsQuery->where('applications.mpp_year', $selectedYear);
    }

    if ($user->hasAnyRole(['kepala departemen', 'division_head'])) {
      $accessibleDepartmentIds = $user->getAccessibleDepartmentIds();
      $query->whereIn('candidates.department_id', $accessibleDepartmentIds);
      $statsQuery->whereHas('candidate', function ($q) use ($accessibleDepartmentIds) {
        $q->whereIn('department_id', $accessibleDepartmentIds);
      });
    }

    if ($request->filled('vacancy_id')) {
      $query->where('applications.vacancy_id', $request->vacancy_id);
      $statsQuery->where('applications.vacancy_id', $request->vacancy_id);
    }

    // Duplicate Handling Filter
    $duplicateCandidateQuery = Application::select('applications.candidate_id', 'applications.mpp_year')
      ->groupBy('applications.candidate_id', 'applications.mpp_year')
      ->havingRaw('COUNT(*) > 1');

    if ($selectedYear) {
      $duplicateCandidateQuery->where('applications.mpp_year', $selectedYear);
    }
    $duplicateCandidateIds = $duplicateCandidateQuery->pluck('candidate_id');

    if ($request->filled('type')) {
      if ($request->type === 'duplicate') {
        $query->whereIn('applications.candidate_id', $duplicateCandidateIds);
        $statsQuery->whereIn('applications.candidate_id', $duplicateCandidateIds);
      } elseif ($request->type === 'non-duplicate') {
        $query->whereNotIn('applications.candidate_id', $duplicateCandidateIds);
        $statsQuery->whereNotIn('applications.candidate_id', $duplicateCandidateIds);
      } elseif ($request->type === 'organic') {
        $query->whereHas('candidate', function ($q) {
          $q->where('airsys_internal', 'Yes');
        });
        $statsQuery->whereHas('candidate', function ($q) {
          $q->where('airsys_internal', 'Yes');
        });
      } elseif ($request->type === 'non-organic') {
        $query->whereHas('candidate', function ($q) {
          $q->where('airsys_internal', 'No');
        });
        $statsQuery->whereHas('candidate', function ($q) {
          $q->where('airsys_internal', 'No');
        });
      } elseif ($request->type === 'non-duplicate') {
        $query->whereNotIn('applications.candidate_id', $duplicateCandidateIds);
        $statsQuery->whereNotIn('applications.candidate_id', $duplicateCandidateIds);
      }
    }

    if ($request->boolean('not_moved')) {
      $query->whereNotExists(function ($q) {
        $q->select(DB::raw(1))
          ->from('applications as sub_app')
          ->whereRaw('sub_app.candidate_id = applications.candidate_id')
          ->where('sub_app.overall_status', 'PINDAH');
      });
      $statsQuery->whereNotExists(function ($q) {
        $q->select(DB::raw(1))
          ->from('applications as sub_app')
          ->whereRaw('sub_app.candidate_id = applications.candidate_id')
          ->where('sub_app.overall_status', 'PINDAH');
      });
    }

    if ($request->filled('search')) {
      $search = $request->search;
      $filterSearch = function ($q) use ($search) {
        $q->where('candidates.nama', 'like', "%{$search}%")
          ->orWhere('candidates.applicant_id', 'like', "%{$search}%")
          ->orWhere('candidates.alamat_email', 'like', "%{$search}%");
      };
      $query->where($filterSearch);
      $statsQuery->where($filterSearch);
    }

    if ($request->filled('department_id')) {
      $query->where('candidates.department_id', $request->department_id);
      $statsQuery->where('candidates.department_id', $request->department_id);
    }

    if ($request->filled('source')) {
      $query->where('candidates.source', $request->source);
      $statsQuery->where('candidates.source', $request->source);
    }

    // if ($request->filled('status')) {
    //     $status = strtoupper($request->status);
    //     $overallStatus = match ($status) {
    //         'FAILED' => 'DITOLAK',
    //         'HIRED' => 'LULUS',
    //         'ON_PROCESS' => 'PROSES',
    //         'CANCEL' => 'CANCEL',
    //         default => $status
    //     };
    //     $query->where('applications.overall_status', $overallStatus);
    //     $statsQuery->where('applications.overall_status', $overallStatus);
    // }

    // ==========================================
    // DYNAMIC STATUS & STAGE FILTERING (SYNCED WITH CARDS)
    // ==========================================
    if ($request->filled('stage')) {
      $stage = strtolower(trim($request->stage));
      $reqStatus = strtoupper($request->status ?? '');

      $stageOrder = [
        'psikotes' => 1,
        'hc_interview' => 2,
        'user_interview' => 3,
        'interview_bod' => 4,
        'offering_letter' => 5,
        'mcu' => 6,
        'hiring' => 7,
      ];
      $targetWeight = $stageOrder[$stage] ?? 0;

      // 1. Table Query ($query) - Tampilkan semua kandidat yang pernah menyentuh stage ini
      $query->whereHas('stages', function ($q) use ($stage) {
        $q->where('stage_name', $stage);
      });

      // Replikasi "Logical Inference" ke dalam Query Database untuk Tabel
      if ($reqStatus) {
        if (in_array($reqStatus, ['HIRED', 'LULUS'])) {
          // LULUS: Jika kandidat sudah mencapai stage yang lebih tinggi, ATAU punya status LULUS di stage ini
          $higherStages = array_keys(array_filter($stageOrder, fn($w) => $w > $targetWeight));

          $query->where(function ($q) use ($higherStages, $stage) {
            if (!empty($higherStages)) {
              $q->whereHas('stages', fn($sq) => $sq->whereIn('stage_name', $higherStages));
            }
            $q->orWhereHas('stages', fn($sq) => $sq->where('stage_name', $stage)->whereIn('status', ['LULUS', 'HIRED']));
          });

        } elseif ($reqStatus === 'ON_PROCESS') {
          // PROSES: Mentok di stage ini (Max ID), status tidak final, dan tidak dicancel
          $query->whereNotIn('applications.overall_status', ['CANCEL', 'PINDAH'])
            ->whereHas('stages', function ($q) use ($stage) {
              $q->where('stage_name', $stage)
                ->whereNotIn('status', ['LULUS', 'HIRED', 'TIDAK LULUS', 'DITOLAK', 'FAILED', 'CANCEL'])
                ->where('id', function ($sub) {
                  $sub->select(DB::raw('max(id)'))->from('application_stages')->whereColumn('application_id', 'applications.id');
                });
            });

        } elseif ($reqStatus === 'FAILED') {
          // GAGAL: Mentok di stage ini dengan status Gagal
          $query->whereHas('stages', function ($q) use ($stage) {
            $q->where('stage_name', $stage)
              ->whereIn('status', ['TIDAK LULUS', 'DITOLAK', 'FAILED'])
              ->where('id', function ($sub) {
                $sub->select(DB::raw('max(id)'))->from('application_stages')->whereColumn('application_id', 'applications.id');
              });
          });

        } elseif ($reqStatus === 'CANCEL') {
          // CANCEL: Mentok di stage ini karena status stage CANCEL, atau aplikasi dipindah
          $query->whereHas('stages', function ($q) use ($stage) {
            $q->where('stage_name', $stage)
              ->where('id', function ($sub) {
                $sub->select(DB::raw('max(id)'))->from('application_stages')->whereColumn('application_id', 'applications.id');
              })
              ->where(function ($subQ) {
                $subQ->where('status', 'CANCEL')
                  ->orWhereExists(function ($ex) {
                    $ex->select(DB::raw(1))
                      ->from('applications as a')
                      ->whereColumn('a.id', 'application_stages.application_id')
                      ->whereIn('a.overall_status', ['CANCEL', 'PINDAH']);
                  });
              });
          });
        }
      }

      // 2. Stats Query Optimization
      $statsQuery->whereHas('stages', function ($q) use ($stage) {
        $q->where('stage_name', $stage);
      });

    } else {
      // GLOBAL STATUS FILTER (Jika tidak ada filter stage / Semua Tahapan)
      // if ($request->filled('status')) {
      //     $status = strtoupper($request->status);
      //     $overallStatus = match ($status) {
      //         'FAILED' => 'DITOLAK',
      //         'HIRED' => 'LULUS',
      //         'ON_PROCESS' => 'PROSES',
      //         'CANCEL' => 'CANCEL',
      //         default => $status
      //     };
      //     $query->where('applications.overall_status', $overallStatus);
      //     $statsQuery->where('applications.overall_status', $overallStatus);
      // }
      if ($request->filled('status')) {
        $status = strtoupper($request->status);

        if ($status === 'CANCEL') {
          // Fetch both CANCEL and PINDAH statuses
          $query->whereIn('applications.overall_status', ['CANCEL', 'PINDAH']);
          $statsQuery->whereIn('applications.overall_status', ['CANCEL', 'PINDAH']);
        } else {
          $overallStatus = match ($status) {
            'FAILED' => 'DITOLAK',
            'HIRED' => 'LULUS',
            'ON_PROCESS' => 'PROSES',
            default => $status
          };
          $query->where('applications.overall_status', $overallStatus);
          $statsQuery->where('applications.overall_status', $overallStatus);
        }
      }
    }

    // ==========================================
    // STAGE FILTERING FIX
    // ==========================================
    // if ($request->filled('stage')) {
    //     $stage = $request->stage;

    //     // For the Table: Only show candidates who are CURRENTLY in this stage (using MAX id)
    //     $filterByLatestStage = function ($q) use ($stage) {
    //         $q->where('stage_name', $stage)
    //           ->where('id', function($sub) {
    //               $sub->select(DB::raw('max(id)'))
    //                   ->from('application_stages')
    //                   ->whereColumn('application_id', 'applications.id');
    //           });
    //     };
    //     $query->whereHas('stages', $filterByLatestStage);

    //     // For the Cards (Stats): Include EVERYONE who has EVER touched this stage! (Ignore MAX id)
    //     $filterByAnyStage = function ($q) use ($stage) {
    //         $q->where('stage_name', $stage);
    //     };
    //     $statsQuery->whereHas('stages', $filterByAnyStage);
    // }

    // 4. Finalize Pagination
    $applications = $query
      ->orderBy('candidates.nama', 'asc')
      ->orderByRaw("CASE WHEN applications.overall_status = 'PROSES' THEN 1 ELSE 2 END")
      ->orderByDesc('applications.created_at')
      ->paginate(15);

    $statuses = ['ON_PROCESS' => 'Proses', 'HIRED' => 'Lulus', 'FAILED' => 'Tidak Lulus', 'CANCEL' => 'Cancel'];


    // 5. SEPARATED DATA METRICS COMPILATION LOGIC 
    $filteredApplicationIds = (clone $statsQuery)->pluck('applications.id')->toArray();

    $stats = [
      'total_candidates' => 0,
      'candidates_in_process' => 0,
      'candidates_passed' => 0,
      'candidates_failed' => 0,
      'candidates_cancelled' => 0,
      'duplicate' => $duplicateCandidateIds->count(),
    ];

    if ($request->filled('stage')) {
      $stage = strtolower(trim($request->stage));

      // 1. Definisikan urutan tahapan (dari terkecil ke terbesar)
      $stageOrder = [
        'psikotes' => 1,
        'hc_interview' => 2,
        'user_interview' => 3,
        'interview_bod' => 4,
        'offering_letter' => 5,
        'mcu' => 6,
        'hiring' => 7,
      ];

      $targetWeight = $stageOrder[$stage] ?? 0;

      // 2. Ambil SEMUA riwayat tahapan untuk kandidat-kandidat ini
      $allStages = DB::table('application_stages')
        ->join('applications', 'application_stages.application_id', '=', 'applications.id')
        ->whereIn('application_stages.application_id', $filteredApplicationIds)
        ->select(
          'application_stages.application_id',
          'application_stages.stage_name',
          'application_stages.status',
          'applications.overall_status as parent_status'
        )
        ->get();

      $appJourneys = [];

      // 3. Petakan perjalanan tiap kandidat untuk mencari "Tahap Terjauh" mereka
      foreach ($allStages as $rec) {
        $appId = $rec->application_id;
        $sName = strtolower(trim($rec->stage_name));
        $weight = $stageOrder[$sName] ?? 0;

        if (!isset($appJourneys[$appId])) {
          $appJourneys[$appId] = [
            'highest_weight' => 0,
            'target_stage_status' => null,
            'parent_status' => strtoupper(trim($rec->parent_status ?? '')),
          ];
        }

        // Cari tahapan terjauh yang pernah dicapai kandidat ini
        if ($weight > $appJourneys[$appId]['highest_weight']) {
          $appJourneys[$appId]['highest_weight'] = $weight;
        }

        // Simpan status asli dari tahap yang sedang difilter oleh user
        if ($sName === $stage) {
          $appJourneys[$appId]['target_stage_status'] = strtoupper(trim($rec->status));
        }
      }

      // 4. Hitung statistik menggunakan Logika Inferensi
      foreach ($appJourneys as $appId => $journey) {
        $highestWeight = $journey['highest_weight'];

        // Hanya hitung jika kandidat PERNAH MENCAPAI tahap yang difilter ini
        if ($highestWeight >= $targetWeight && $targetWeight > 0) {
          $stats['total_candidates']++;

          // INFERENSI KUNCI: Jika tahap maksimalnya LEBIH BESAR dari tahap yang dicari,
          // maka dia OTOMATIS DIANGGAP LULUS tahap ini (mengabaikan data database yang bocor)
          if ($highestWeight > $targetWeight) {
            $stats['candidates_passed']++;
          }
          // Jika tahap ini adalah posisi MENTOK mereka saat ini, baca status aslinya
          else {
            $rawStatus = $journey['target_stage_status'];
            $parentStatus = $journey['parent_status'];

            if (in_array($parentStatus, ['CANCEL', 'PINDAH'])) {
              $stats['candidates_cancelled']++;
            } else {
              if (in_array($rawStatus, ['LULUS', 'HIRED'])) {
                $stats['candidates_passed']++;
              } elseif (in_array($rawStatus, ['TIDAK LULUS', 'DITOLAK', 'FAILED'])) {
                $stats['candidates_failed']++;
              } elseif ($rawStatus === 'CANCEL') {
                $stats['candidates_cancelled']++;
              } else {
                $stats['candidates_in_process']++;
              }
            }
          }
        }
      }
    } else {
      // GLOBAL STATS (No Stage Selected - Shows Overall Pipeline)
      $latestApplicationStages = DB::table('application_stages')
        ->join('applications', 'application_stages.application_id', '=', 'applications.id')
        ->whereIn('application_stages.application_id', $filteredApplicationIds)
        ->whereIn('application_stages.id', function ($subQuery) {
          $subQuery->select(DB::raw('MAX(id)'))
            ->from('application_stages')
            ->groupBy('application_id');
        })
        ->select('application_stages.status', 'applications.overall_status as parent_status')
        ->get();

      $stats['total_candidates'] = count($latestApplicationStages);

      foreach ($latestApplicationStages as $rec) {
        $rawStatus = strtoupper(trim($rec->status));
        $parentStatus = strtoupper(trim($rec->parent_status ?? ''));

        if (in_array($parentStatus, ['CANCEL', 'PINDAH'])) {
          $stats['candidates_cancelled']++;
        } else {
          if (in_array($rawStatus, ['LULUS', 'HIRED'])) {
            $stats['candidates_passed']++;
          } elseif (in_array($rawStatus, ['TIDAK LULUS', 'DITOLAK', 'FAILED'])) {
            $stats['candidates_failed']++;
          } elseif ($rawStatus === 'CANCEL') {
            $stats['candidates_cancelled']++;
          } else {
            $stats['candidates_in_process']++;
          }
        }
      }
    }


    // Get Active Vacancies
    // $activeVacancies = Vacancy::whereHas('mppSubmissions', function ($q) {
    //     $q->where('proposal_status', 'approved');
    // })->with(['mppSubmissions' => function ($q) {
    //     $q->where('proposal_status', 'approved');
    // }, 'recruitmentSummaries'])
    // ->when($user->department_id, function ($q) use ($user) {
    //     $q->where('department_id', $user->department_id);
    // })
    // ->get();

    if (!$hasFilters) {
      $masterDataCancelSum = DB::table('master_data')
        ->where('type', 'candidate_stage_status')
        ->where('key', 'LIKE', '%|CANCEL')
        ->where('active', 1)
        ->sum('value');

      $masterDataFailSum = DB::table('master_data')
        ->where('type', 'candidate_stage_status')
        ->where('key', 'LIKE', '%|TIDAK LULUS')
        ->where('active', 1)
        ->sum('value');

      $masterDataTotalSum = DB::table('master_data')
        ->where('type', 'candidate_stage_status')
        ->where('active', 1)
        ->sum('value');
    } else {
      // Apply filters to master_data when filters are present
      $masterDataQuery = DB::table('master_data')
        ->where('type', 'candidate_stage_status')
        ->where('active', 1);

      if ($selectedYear) {
        $masterDataQuery->where('year', $selectedYear);
      }

      if ($user->hasRole('kepala departemen') && $user->department_id) {
        $masterDataQuery->where('department_id', $user->department_id);
      }

      if ($request->filled('department_id')) {
        $masterDataQuery->where('department_id', $request->department_id);
      }

      $masterDataCancelSum = (clone $masterDataQuery)->where('key', 'LIKE', '%|CANCEL')->sum('value');
      $masterDataFailSum = (clone $masterDataQuery)->where('key', 'LIKE', '%|TIDAK LULUS')->sum('value');
      $masterDataTotalSum = (clone $masterDataQuery)->sum('value');
    }

    $stats['candidates_cancelled'] += (int) $masterDataCancelSum;
    $stats['candidates_failed'] += (int) $masterDataFailSum;
    $stats['total_candidates'] += (int) $masterDataTotalSum;


    $activeVacancies = \App\Models\Vacancy::whereHas('mppSubmissions', function ($q) use ($selectedYear) {
      $q->where('proposal_status', 'approved');
      if ($selectedYear) {
        $q->where('year', $selectedYear);
      }
    })->with([
          'mppSubmissions' => function ($q) use ($selectedYear) {
            $q->where('proposal_status', 'approved');
            if ($selectedYear) {
              $q->where('year', $selectedYear);
            }
          }
        ])
      ->when($user->department_id, function ($q) use ($user) {
        $q->where('department_id', $user->department_id);
      })
      ->withCount([
        'applications' => function ($q) use ($selectedYear) {
          if ($selectedYear) {
            $q->where('mpp_year', $selectedYear);
          }
        }
      ])->get();


    $departments = Department::orderBy('name')->get();
    $sources = Candidate::distinct()->pluck('source');
    $stages = RecruitmentStage::cases();

    return view('candidates.index', compact(
      'applications',
      'statuses',
      'stats',
      'type',
      'duplicateCandidateIds',
      'departments',
      'sources',
      'stages',
      'selectedYear',
      'years',
      'activeVacancies',
    ));
  }

  /**
   * =========================
   * CANDIDATE DETAIL
   * =========================
   */
  public function show(Request $request, $id)
  {
    $candidate = Candidate::with([
      'department',
      'applications' => function ($query) {
        $query->orderByDesc('created_at');
      },
      'applications.vacancy',
      'applications.stages.conductedByUser',
    ])->findOrFail($id);

    $targetApplicationId = $request->query('application_id');
    $allTimelines = [];
    $primaryApplication = null;

    if ($candidate->applications->isNotEmpty()) {
      foreach ($candidate->applications as $app) {
        $app->loadMissing([
          'stages' => function ($query) {
            $query->orderBy('created_at', 'asc');
          },
          'stages.conductedByUser'
        ]);
        $allTimelines[$app->id] = $candidate->getTimelineForApplication($app);

        if ($targetApplicationId && $app->id == $targetApplicationId) {
          $primaryApplication = $app;
        }
      }

      if (!$primaryApplication) {
        $primaryApplication = $candidate->applications->first();
      }
    }

    $activeVacancies = Vacancy::whereHas('mppSubmissions', function ($q) {
      $q->where('proposal_status', 'approved');
    })
      ->with([
        'mppSubmissions' => function ($q) {
          $q->where('proposal_status', 'approved')->select('mpp_submissions.id', 'year');
        }
      ])
      ->get();

    $assessmentScoreController = app(CandidateAssessmentScoreController::class);
    $candidateAssessment = $assessmentScoreController->latestForCandidate($candidate);
    $hasAssessmentScore = $assessmentScoreController->hasDisplayableScore($candidateAssessment);

    return view('candidates.show', compact(
      'candidate',
      'allTimelines',
      'primaryApplication',
      'activeVacancies',
      'candidateAssessment',
      'hasAssessmentScore',
    ));
  }

  /**
   * =========================
   * UPDATE STATUS (MANUAL)
   * =========================
   */
  public function updateStatus(Request $request, $id)
  {
    $candidate = Candidate::findOrFail($id);
    $request->validate(['status' => 'required|string']);

    $candidate->update([
      'status' => strtoupper($request->status),
    ]);

    return redirect()->back()->with('success', 'Candidate status updated successfully.');
  }

  /**
   * Move position tracking pipeline logic assignments
   */
  public function movePosition(Request $request, Application $application, ApplicationStageService $stageService)
  {
    $validated = $request->validate([
      'new_vacancy_id' => 'required|exists:vacancies,id',
      'mpp_year' => 'required|integer',
    ]);

    try {
      DB::beginTransaction();

      $newVacancy = Vacancy::findOrFail($validated['new_vacancy_id']);
      $candidate = $application->candidate;

      $candidate->department_id = $newVacancy->department_id;

      $mppSubmission = $newVacancy->mppSubmissions()
        ->where('year', $validated['mpp_year'])
        ->where('proposal_status', 'approved')
        ->first();

      if ($mppSubmission) {
        $vacancyStatus = $mppSubmission->pivot->vacancy_status;
        $candidate->airsys_internal = ($vacancyStatus === 'OSPKWT') ? 'Yes' : 'No';
      }
      $candidate->save();

      $newApplication = Application::create([
        'candidate_id' => $candidate->id,
        'vacancy_id' => $newVacancy->id,
        'mpp_year' => $validated['mpp_year'],
        'overall_status' => $application->overall_status,
      ]);

      $application->update([
        'overall_status' => 'PINDAH',
        'internal_position' => $newVacancy->name . " (" . $validated['mpp_year'] . ")"
      ]);

      $stageService->copyStages($application, $newApplication);

      try {
        $stageService->processStageUpdate($newApplication, [
          'stage' => 'interview_bod',
          'result' => 'LULUS',
          'notes' => "[PINDAH POSISI] Otomatis lulus BOD karena pindah posisi dari: " . ($application->vacancy->name ?? 'N/A') . " (Tahun MPP: " . $application->mpp_year . ")",
          'stage_date' => now()->format('Y-m-d'),
        ]);
      } catch (\Exception $e) {
        Log::warning("Could not automatically pass BOD stage during move: " . $e->getMessage());

        $latestStage = $newApplication->stages()->orderBy('id', 'desc')->first();
        if ($latestStage) {
          $existingNotes = $latestStage->notes ?? '';
          $newNote = "\n[PINDAH POSISI] Berlanjut dari posisi: " . ($application->vacancy->name ?? 'N/A') . " (Tahun MPP: " . $validated['mpp_year'] . ")";
          $latestStage->update(['notes' => $existingNotes . $newNote]);
        }
      }

      DB::commit();
      return response()->json(['message' => 'Candidate position moved successfully. New application created with previous history.']);
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Error moving position: ' . $e->getMessage());
      return response()->json(['message' => 'Error moving position: ' . $e->getMessage()], 500);
    }
  }

  public function export(Request $request)
  {
    return Excel::download(new \App\Exports\CandidatesExport(), 'candidates_' . date('Ymd') . '.xlsx');
  }

  public function bulkExport(Request $request)
  {
    $validated = $request->validate(['ids' => 'required|array']);
    return Excel::download(new \App\Exports\CandidatesExport($validated['ids']), 'candidates_bulk_' . date('Ymd') . '.xlsx');
  }

  public function switchType(Request $request, Candidate $candidate)
  {
    $validated = $request->validate(['type' => 'required|string|in:internal,external']);
    $candidate->update(['type' => $validated['type']]);
    return response()->json(['message' => 'Candidate type switched.']);
  }

  public function bulkSwitchType(Request $request)
  {
    $validated = $request->validate([
      'ids' => 'required|array',
      'type' => 'required|string|in:internal,external',
    ]);
    Candidate::whereIn('id', $validated['ids'])->update(['type' => $validated['type']]);
    return response()->json(['message' => 'Candidate types switched.']);
  }

  public function bulkUpdateStatus(Request $request)
  {
    $validated = $request->validate([
      'ids' => 'required|array',
      'status' => 'required|string',
    ]);
    Candidate::whereIn('id', $validated['ids'])->update(['status' => $validated['status']]);
    return response()->json(['message' => 'Candidate statuses updated.']);
  }

  public function bulkMoveStage(Request $request)
  {
    $validated = $request->validate([
      'ids' => 'required|array',
      'stage' => 'required|string',
    ]);
    Application::whereIn('candidate_id', $validated['ids'])->update([
      'overall_status' => $validated['stage'],
    ]);
    return response()->json(['message' => 'Candidates moved to stage.']);
  }

  public function setNextTestDate(Request $request, Candidate $candidate)
  {
    $validated = $request->validate(['next_test_date' => 'required|date']);
    $candidate->applications()->latest()->first()?->update([
      'next_test_date' => $validated['next_test_date'],
    ]);
    return response()->json(['message' => 'Next test date set.']);
  }

  public function checkDuplicate(Request $request)
  {
    $validated = $request->validate(['email' => 'required|email']);
    $duplicate = Candidate::where('email', $validated['email'])->first();
    return response()->json([
      'is_duplicate' => !!$duplicate,
      'candidate' => $duplicate,
    ]);
  }

  public function bulkDelete(Request $request)
  {
    $validated = $request->validate(['ids' => 'required|array']);
    Candidate::whereIn('id', $validated['ids'])->delete();
    return response()->json(['message' => 'Candidates deleted.']);
  }
}