<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Vacancy;
use App\Services\ApplicationStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\CandidateEditHistory;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class CandidateController extends Controller
{
    /**
     * =========================
     * UPDATE APPLICATION STAGE
     * =========================
     *
     * @param Request $request
     * @param Application $application
     * @param ApplicationStageService $stageService
     * @return JsonResponse
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
        ];
        
        // Result is only required if not updating date only
        if (!$updateDateOnly) {
            $rules['result'] = 'required|string';
        } else {
            $rules['result'] = 'nullable|string';
        }
        
        $validated = $request->validate($rules);

        try {
            $stageService->processStageUpdate($application, $validated);
            return response()->json(['message' => 'Stage updated successfully.']);
        } catch (\Exception $e) {
            \Log::error('Error updating stage: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error updating stage: ' . $e->getMessage()], 500);
        }
    }

    /**
     * =========================
     * RESET APPLICATION STAGE
     * =========================
     *
     * @param Request $request
     * @param Application $application
     * @param ApplicationStageService $stageService
     * @return JsonResponse
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
            \Log::error('Error resetting stage: ' . $e->getMessage());
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

            // Find the application created AFTER this one for this candidate
            $nextApplication = Application::where('candidate_id', $application->candidate_id)
                ->where('id', '>', $application->id)
                ->orderBy('id', 'asc')
                ->first();

            if ($nextApplication) {
                // Delete the new application and its stages
                $nextApplication->stages()->delete();
                $nextApplication->delete();
            }

            // Restore THIS application
            $application->update([
                'overall_status' => 'PROSES',
                'internal_position' => null
            ]);

            // Re-evaluate candidate's department and type from this restored application
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
            \Log::error('Error canceling move: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal membatalkan perpindahan: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Show the form for creating a new candidate
     */
    public function create()
    {
        $vacancies = \App\Models\Vacancy::with(['mppSubmissions' => function ($q) {
            $q->where('proposal_status', 'approved');
        }])->whereHas('mppSubmissions', function ($q) {
            $q->where('proposal_status', 'approved');
        })->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        
        // Generate Applicant ID
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

        \Log::info('Candidate store validation passed.', $validated);

        try {
            DB::beginTransaction();

            $vacancy = Vacancy::findOrFail($validated['vacancy_id']);

            // Determine candidate type from vacancy status from pivot
            $airsysInternal = null;
            if ($validated['mpp_year']) {
                $mppSubmission = $vacancy->mppSubmissions()
                    ->where('year', $validated['mpp_year'])
                    ->where('proposal_status', 'approved')
                    ->first();
                
                if ($mppSubmission) {
                    $vacancyStatus = $mppSubmission->pivot->vacancy_status;
                    if ($vacancyStatus === 'OSPKWT') {
                        $airsysInternal = 'Yes';
                    } elseif ($vacancyStatus === 'OS') {
                        $airsysInternal = 'No';
                    }
                }
            }
            
            $data = [
                'nama' => $validated['nama'],
                'alamat_email' => $validated['alamat_email'],
                'applicant_id' => $validated['applicant_id'],
                'jk' => $validated['jk'] ?? null,
                'tanggal_lahir' => $validated['tanggal_lahir'],
                'department_id' => $vacancy->department_id, // Get department from vacancy
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

            // Create the candidate
            $candidate = Candidate::create($data);

            // Set mpp_year for the candidate from the validated request
            // This assumes mpp_year from the request is the intended mpp_year for the candidate
            if (isset($validated['mpp_year'])) {
                $candidate->mpp_year = $validated['mpp_year'];
                $candidate->save();
            }

            // Create the application for the candidate
            Application::create([
                'candidate_id' => $candidate->id,
                'vacancy_id' => $vacancy->id,
                'mpp_year' => $validated['mpp_year'] ?? null,
                'overall_status' => 'PROSES', // Default status
            ]);

            DB::commit();

            return redirect()->route('candidates.show', $candidate)->with('success', 'Candidate created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating candidate: ' . $e->getMessage(), [
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
        $vacancies = \App\Models\Vacancy::whereHas('mppSubmissions', function ($q) {
            $q->where('proposal_status', 'approved');
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
                // Delete old file if it exists
                if ($candidate->cv) {
                    Storage::disk('public')->delete($candidate->cv);
                }
                $data['cv'] = $request->file('cv')->store('candidate-files', 'public');
            }

            if ($request->hasFile('flk')) {
                // Delete old file if it exists
                if ($candidate->flk) {
                    Storage::disk('public')->delete($candidate->flk);
                }
                $data['flk'] = $request->file('flk')->store('candidate-files', 'public');
            }

            // Get original data for history
            $originalData = $candidate->fresh()->getAttributes();

            // Update the candidate
            $candidate->update($data);

            // Get changed data
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

            // Record history if there are changes
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
            \Log::error('Error updating candidate: ' . $e->getMessage(), [
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
     * =========================
     * CANDIDATE LIST
     * =========================
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $user = Auth::user();

        // --- Year & Filter Preparation ---

        // Prepare years for filter dropdown by combining years from MPP submissions and applications
        $mppYears = \App\Models\MPPSubmission::select('year')->distinct()->pluck('year');
        $applicationYears = \App\Models\Application::select('mpp_year')->distinct()->pluck('mpp_year');
        
        $years = $mppYears->merge($applicationYears)
                         ->unique()
                         ->filter() // Ensure no null values
                         ->sortDesc()
                         ->values();

        // Ensure current year is always an option
        $currentYear = date('Y');
        if (!$years->contains($currentYear)) {
            $years->prepend($currentYear);
            $years = $years->sortDesc()->values();
        }
        
        // Default to the latest year with data, or the current year (but allow empty for 'all years')
        $selectedYear = $request->input('year'); // Let it be null if 'year' is not in request or is empty string

        // --- Main Query Initialization with JOIN ---
        $query = Application::query()
            ->select('applications.*') // IMPORTANT: Select from applications table to avoid conflicts
            ->join('candidates', 'applications.candidate_id', '=', 'candidates.id')
            ->with([
                'candidate.department',
                'candidate.latestPsikotest',
                'candidate.latestHCInterview',
                'vacancy',
                'stages',
            ]);

        $statsQuery = Application::query();

        // --- Applying Filters ---

        // Apply the year filter ONLY if a year is provided in the request
        if ($selectedYear) {
            $query->where('applications.mpp_year', $selectedYear);
            $statsQuery->where('applications.mpp_year', $selectedYear);
        }

        if ($user->hasRole('kepala departemen') && $user->department_id) {
            $query->where('candidates.department_id', $user->department_id);
            $statsQuery->whereHas('candidate', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }

        // --- DUPLICATE LOGIC ---
        // Find candidates who applied more than once in the same year
        // $duplicateCandidateQuery = Application::select('candidate_id')
        //     ->groupBy('candidate_id', 'mpp_year')
        //     ->havingRaw('COUNT(*) > 1');

        // if ($selectedYear) {
        //     $duplicateCandidateQuery->where('mpp_year', $selectedYear);
        // }

        // $duplicateCandidateIds = $duplicateCandidateQuery->pluck('candidate_id');

        $duplicateCandidateQuery = Application::select('candidate_id', 'mpp_year')
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
                
                // If we're looking at all years, ensure we only show the years that are duplicate
                // if (!$selectedYear) {
                //     $duplicateCondition = function($q) {
                //         $q->select('candidate_id', 'mpp_year')
                //           ->from('applications')
                //           ->groupBy('candidate_id', 'mpp_year')
                //           ->havingRaw('COUNT(*) > 1');
                //     };
                //     $query->whereIn(DB::raw('(candidate_id, mpp_year)'), $duplicateCondition);
                //     $statsQuery->whereIn(DB::raw('(candidate_id, mpp_year)'), $duplicateCondition);
                // }

                if (!$selectedYear) {
                    $duplicateCondition = function($q) {
                        $q->select('applications.candidate_id', 'applications.mpp_year')
                        ->from('applications')
                        ->groupBy('applications.candidate_id', 'applications.mpp_year')
                        ->havingRaw('COUNT(*) > 1');
                    };

                    $query->whereIn(DB::raw('(applications.candidate_id, applications.mpp_year)'), $duplicateCondition);
                    $statsQuery->whereIn(DB::raw('(applications.candidate_id, applications.mpp_year)'), $duplicateCondition);
                }

            } elseif ($request->type === 'organic') {
                $query->whereHas('candidate', function ($q) { $q->where('airsys_internal', 'Yes'); });
                $statsQuery->whereHas('candidate', function ($q) { $q->where('airsys_internal', 'Yes'); });
            } elseif ($request->type === 'non-organic') {
                $query->whereHas('candidate', function ($q) { $q->where('airsys_internal', 'No'); });
                $statsQuery->whereHas('candidate', function ($q) { $q->where('airsys_internal', 'No'); });
            }
        }

        if ($request->filled('status')) {
            $status = strtoupper($request->status);
            $overallStatus = match ($status) {
                'FAILED' => 'DITOLAK',
                'HIRED' => 'LULUS',
                'ON_PROCESS' => 'PROSES',
                'CANCEL' => 'CANCEL',
                default => $status
            };
            $query->where('applications.overall_status', $overallStatus);
            $statsQuery->where('applications.overall_status', $overallStatus);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $filterSearch = function($q) use ($search) {
                $q->where('candidates.nama', 'like', "%{$search}%")
                  ->orWhere('candidates.applicant_id', 'like', "%{$search}%")
                  ->orWhere('candidates.alamat_email', 'like', "%{$search}%");
            };
            $query->where($filterSearch);
            $statsQuery->whereHas('candidate', $filterSearch);
        }
        
        if ($request->filled('vacancy_id')) { 
            $query->where('applications.vacancy_id', $request->vacancy_id); 
            $statsQuery->where('applications.vacancy_id', $request->vacancy_id);
        }
        
        if ($request->filled('department_id')) { 
            $query->where('candidates.department_id', $request->department_id);
            $statsQuery->whereHas('candidate', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        
        if ($request->filled('source')) { 
            $query->where('candidates.source', $request->source);
            $statsQuery->whereHas('candidate', function($q) use ($request) {
                $q->where('source', $request->source);
            });
        }
        
        if ($request->filled('stage')) {
            $stage = $request->stage;
            $filterByLatestStage = function ($q) use ($stage) {
                // We use a subquery to ensure we are only matching if the LATEST stage for this application
                // is the one we're filtering for.
                $q->where('stage_name', $stage)
                  ->where('id', function($sub) use ($stage) {
                      $sub->select(DB::raw('max(id)'))
                          ->from('application_stages')
                          ->whereColumn('application_id', 'applications.id');
                  });
            };
            
            $query->whereHas('stages', $filterByLatestStage);
            $statsQuery->whereHas('stages', $filterByLatestStage);
        }

        // --- Finalizing Query & Pagination ---
        $applications = $query
            ->orderBy('candidates.nama', 'asc')
            ->orderByRaw("CASE WHEN applications.overall_status = 'PROSES' THEN 1 ELSE 2 END")
            ->orderByDesc('applications.created_at')
            ->paginate(15);

        // --- Data for View ---
        $statuses = [ 'ON_PROCESS' => 'Proses', 'HIRED' => 'Lulus', 'FAILED' => 'Tidak Lulus', 'CANCEL' => 'Cancel' ];
        $stats = [
            // 'total_candidates' => (clone $statsQuery)->distinct('candidate_id')->count('candidate_id'),
            'total_candidates' => (clone $statsQuery)->count(),
            'candidates_in_process' => (clone $statsQuery)->where('overall_status', 'PROSES')->count(),
            'candidates_passed' => (clone $statsQuery)->where('overall_status', 'LULUS')->count(),
            'candidates_failed' => (clone $statsQuery)->where('overall_status', 'DITOLAK')->count(),
            'candidates_cancelled' => (clone $statsQuery)->where('overall_status', 'CANCEL')->count(),
            'duplicate' => $duplicateCandidateIds->count(),
        ];
        $activeVacancies = \App\Models\Vacancy::whereHas('mppSubmissions', function ($q) use ($selectedYear) {
            $q->where('proposal_status', 'approved');
            if ($selectedYear) {
                $q->where('year', $selectedYear);
            }
        })->with(['mppSubmissions' => function ($q) use ($selectedYear) {
            $q->where('proposal_status', 'approved');
            if ($selectedYear) {
                $q->where('year', $selectedYear);
            }
        }])
        ->withCount(['applications' => function ($q) use ($selectedYear) {
            if ($selectedYear) {
                $q->where('mpp_year', $selectedYear);
            }
        }])->get();
        $departments = \App\Models\Department::orderBy('name')->get();
        $sources = \App\Models\Candidate::distinct()->pluck('source');
        $stages = \App\Enums\RecruitmentStage::cases();

        return view('candidates.index', compact('applications', 'statuses', 'stats', 'activeVacancies', 'type', 'duplicateCandidateIds', 'departments', 'sources', 'stages', 'selectedYear', 'years'));
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
            'applications' => function($query) {
                $query->orderByDesc('created_at'); // Order applications to easily get the "latest"
            },
            'applications.vacancy',
            'applications.stages.conductedByUser', // Eager load user for each stage
        ])->findOrFail($id);

        $targetApplicationId = $request->query('application_id');
        $allTimelines = [];
        $primaryApplication = null;

        if ($candidate->applications->isNotEmpty()) {
            foreach ($candidate->applications as $app) {
                // Ensure stages are loaded for each application before generating timeline
                $app->loadMissing(['stages' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                }, 'stages.conductedByUser']);
                $allTimelines[$app->id] = $candidate->getTimelineForApplication($app);
                
                if ($targetApplicationId && $app->id == $targetApplicationId) {
                    $primaryApplication = $app;
                }
            }
            
            if (!$primaryApplication) {
                $primaryApplication = $candidate->applications->first(); // The first one after ordering is the latest
            }
        }

        // The "Move Position" modal needs a list of other active vacancies.
        $appliedVacancyIds = $candidate->applications->whereNotIn('overall_status', ['CANCEL', 'PINDAH'])->pluck('vacancy_id')->filter()->unique();
        $activeVacancies = \App\Models\Vacancy::whereHas('mppSubmissions', function ($q) {
            $q->where('proposal_status', 'approved');
        })
        ->with(['mppSubmissions' => function($q) {
            $q->where('proposal_status', 'approved')->select('mpp_submissions.id', 'year');
        }])
        ->get();

        return view('candidates.show', compact(
            'candidate', 
            'allTimelines', // Pass all generated timelines
            'primaryApplication', // Pass the primary application object
            'activeVacancies'
        ));
    }

    /**
     * =========================
     * UPDATE STATUS (OPTIONAL)
     * =========================
     * Kalau suatu saat HR update manual
     */
    public function updateStatus(Request $request, $id)
    {
        $candidate = Candidate::findOrFail($id);

        $request->validate([
            'status' => 'required|string',
        ]);

        $candidate->update([
            'status' => strtoupper($request->status),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Candidate status updated successfully.');
    }

    /**
     * Placeholder for moving candidate position.
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

            // Re-evaluate candidate's department and internal status
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

            // Create NEW application as requested
            $newApplication = Application::create([
                'candidate_id' => $candidate->id,
                'vacancy_id' => $newVacancy->id,
                'mpp_year' => $validated['mpp_year'],
                'overall_status' => $application->overall_status, // Inherit status (usually PROSES)
            ]);

            // Deactivate OLD application
            $application->update([
                'overall_status' => 'PINDAH',
                'internal_position' => $newVacancy->name . " (" . $validated['mpp_year'] . ")"
            ]);

            // Clone all existing stages to the NEW application using the improved service method
            $stageService->copyStages($application, $newApplication);

            // Automatically pass the BOD stage for the new application
            try {
                $stageService->processStageUpdate($newApplication, [
                    'stage' => 'interview_bod',
                    'result' => 'LULUS',
                    'notes' => "[PINDAH POSISI] Otomatis lulus BOD karena pindah posisi dari: " . ($application->vacancy->name ?? 'N/A') . " (Tahun MPP: " . $application->mpp_year . ")",
                    'stage_date' => now()->format('Y-m-d'),
                ]);
            } catch (\Exception $e) {
                // If validation fails (e.g. they hadn't reached BOD), we just log it and proceed
                // The candidate might be moved from an earlier stage if the UI allows it
                \Log::warning("Could not automatically pass BOD stage during move: " . $e->getMessage());
                
                // At least add a note about the move to the most recent stage
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
            \Log::error('Error moving position: ' . $e->getMessage());
            return response()->json(['message' => 'Error moving position: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export candidates
     */
    public function export(Request $request)
    {
        return Excel::download(new \App\Exports\CandidatesExport(), 'candidates_' . date('Ymd') . '.xlsx');
    }

    /**
     * Bulk export candidates
     */
    public function bulkExport(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
        ]);

        return Excel::download(new \App\Exports\CandidatesExport($validated['ids']), 'candidates_bulk_' . date('Ymd') . '.xlsx');
    }

    /**
     * Switch candidate type
     */
    public function switchType(Request $request, Candidate $candidate)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:internal,external',
        ]);

        $candidate->update(['type' => $validated['type']]);

        return response()->json(['message' => 'Candidate type switched.']);
    }

    /**
     * Bulk switch candidate type
     */
    public function bulkSwitchType(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'type' => 'required|string|in:internal,external',
        ]);

        Candidate::whereIn('id', $validated['ids'])->update(['type' => $validated['type']]);

        return response()->json(['message' => 'Candidate types switched.']);
    }

    /**
     * Bulk update candidate status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'status' => 'required|string',
        ]);

        Candidate::whereIn('id', $validated['ids'])->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Candidate statuses updated.']);
    }

    /**
     * Bulk move candidates to a stage
     */
    public function bulkMoveStage(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'stage' => 'required|string',
        ]);

        // Move applications to the specified stage
        Application::whereIn('candidate_id', $validated['ids'])->update([
            'overall_status' => $validated['stage'],
        ]);

        return response()->json(['message' => 'Candidates moved to stage.']);
    }

    /**
     * Set next test date for a candidate
     */
    public function setNextTestDate(Request $request, Candidate $candidate)
    {
        $validated = $request->validate([
            'next_test_date' => 'required|date',
        ]);

        // Update the next test date in the latest application
        $candidate->applications()->latest()->first()?->update([
            'next_test_date' => $validated['next_test_date'],
        ]);

        return response()->json(['message' => 'Next test date set.']);
    }

    /**
     * Check for duplicate candidates
     */
    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $duplicate = Candidate::where('email', $validated['email'])->first();

        return response()->json([
            'is_duplicate' => !!$duplicate,
            'candidate' => $duplicate,
        ]);
    }

    /**
     * Bulk delete candidates
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
        ]);

        Candidate::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Candidates deleted.']);
    }
}
