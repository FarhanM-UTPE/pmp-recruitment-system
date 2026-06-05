<?php

namespace App\Http\Controllers;

use App\Models\MPPSubmission;
use App\Models\Vacancy;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class MPPSubmissionController extends Controller
{
    /**
     * Display a listing of MPP submissions
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if user can view MPP submissions
        if (!$user->can('view-mpp-submissions')) {
            abort(403);
        }

        $query = MPPSubmission::with(['department', 'createdByUser', 'vacancies']);

        // Filter by department if user is department head or department staff
        if ($user->hasRole('kepala departemen') && $user->department_id) {
            $query->where('department_id', $user->department_id);
        } elseif (!$user->hasRole(['team_hc', 'team_hc_2'])) {
            // Only allow team_hc and team_hc_2 to see all submissions, others must be filtered by department
            abort(403);
        }

        // Filter by status if requested
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by year if requested
        if ($request->filled('year')) {
            $query->where('year', $request->input('year'));
        }

        $mppSubmissions = $query->orderBy('created_at', 'desc')->paginate(15);

        // Get distinct years for the filter dropdown (single source of truth)
        $years = \App\Services\YearProvider::availableYears();

        return view('mpp-submissions.index', [
            'mppSubmissions' => $mppSubmissions,
            'years' => $years,
        ]);
    }

    /**
     * Show the form for creating a new MPP submission
     */
    public function create()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->can('create-mpp-submission')) {
            abort(403);
        }

        // Get departments where user is team HC
        $departments = Department::all()->map(function ($dept) {
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
     * Store a newly created MPP submission
     */
    public function storeOld(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->can('create-mpp-submission')) {
            abort(403);
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

        if (!$user->can('create-mpp-submission')) {
            abort(403);
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'year' => 'required|integer|min:2000|max:2100',
            'submission_type' => 'required|in:planned,unplanned', // ADD THIS VALIDATION
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
     * Display the specified MPP submission
     */
    public function show(MPPSubmission $mppSubmission)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->can('view-mpp-submission-details')) {
            abort(403);
        }

        $mppSubmission->load([
            'department',
            'createdByUser',
            'vacancies.vacancyDocuments.uploadedByUser',
            'approvalHistories.user',
        ]);

        return view('mpp-submissions.show', [
            'mppSubmission' => $mppSubmission,
        ]);
    }

    /**
     * Approve a specific vacancy within an MPP
     */
    public function approveVacancy(Request $request, MPPSubmission $mppSubmission, Vacancy $vacancy)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

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
     * Delete the MPP
     */
    public function destroy(MPPSubmission $mppSubmission)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->can('delete-mpp-submission')) {
            abort(403);
        }

        $vacancyIds = $mppSubmission->vacancies()->pluck('vacancies.id');

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
