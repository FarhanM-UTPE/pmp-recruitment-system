<?php

namespace App\Services;

use App\Exceptions\DuplicateCandidateException;
use App\Models\Application;
use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CandidateService
{
    /**
     * Create a new candidate and their related records.
     *
     * @param array $validatedData
     * @param Request $request
     * @return Candidate
     * @throws DuplicateCandidateException
     * @throws \Throwable
     */
    public function createCandidate(array $validatedData, Request $request): Candidate
    {
        // 1. Check for duplicates
        $duplicateCheckData = [
            'applicant_id' => $validatedData['applicant_id'],
            'email' => $validatedData['alamat_email'],
            'nama' => $validatedData['nama'],
            'jk' => $validatedData['jk'],
            'tanggal_lahir' => $validatedData['tanggal_lahir'],
        ];

        if ($this->findDuplicateCandidate($duplicateCheckData)) {
            throw new DuplicateCandidateException(
                'Kandidat ini terdeteksi sebagai duplikat karena sudah pernah melamar dalam kurun waktu satu tahun terakhir.'
            );
        }

        // 2. Use a database transaction
        return DB::transaction(function () use ($validatedData, $request) {
            // 3. Prepare data for different models
            $candidateData = Arr::except($validatedData, [
                'vacancy_name', 'internal_position', 'cv', 'flk',
                'jenjang_pendidikan', 'perguruan_tinggi', 'jurusan', 'ipk', // Education fields
                'alamat', 'phone', // Profile fields
            ]);
            $candidateData['alamat_email'] = $validatedData['alamat_email'];
            $candidateData['jk'] = $validatedData['jk'];
            $candidateData['tanggal_lahir'] = $validatedData['tanggal_lahir'];
            $candidateData['perguruan_tinggi'] = $validatedData['perguruan_tinggi'];

            $applicationData = [
                'vacancy_name' => $validatedData['vacancy_name'] ?? null,
                'internal_position' => $validatedData['internal_position'] ?? null,
            ];

            $educationData = Arr::only($validatedData, [
                'jenjang_pendidikan', 'perguruan_tinggi', 'jurusan', 'ipk'
            ]);

            $profileData = Arr::only($validatedData, [
                'alamat', 'phone'
            ]);
            $profileData['email'] = $validatedData['alamat_email'];
            $profileData['tanggal_lahir'] = $validatedData['tanggal_lahir'];
            $profileData['jk'] = $validatedData['jk'];

            // 4. Handle file uploads
            if ($request->hasFile('cv')) {
                $candidateData['cv'] = $request->file('cv')->store('private/cvs');
                $applicationData['cv_path'] = $candidateData['cv'];
            }
            if ($request->hasFile('flk')) {
                $candidateData['flk'] = $request->file('flk')->store('private/flks');
                $applicationData['flk_path'] = $candidateData['flk'];
            }

            // 5. Create the records
            Log::info('Attempting Candidate::create from CandidateService');
            $candidate = Candidate::create($candidateData);

            // Get or create vacancy to ensure we have an ID
            if (!empty($applicationData['vacancy_name'])) {
                $vacancy = Vacancy::firstOrCreate(
                    ['name' => $applicationData['vacancy_name']],
                    ['created_at' => now(), 'updated_at' => now()]
                );
                $applicationData['vacancy_id'] = $vacancy->id;
            }
            unset($applicationData['vacancy_name']);

            $applicationData['candidate_id'] = $candidate->id;
            $applicationData['department_id'] = $candidate->department_id;
            Application::create($applicationData);

            if (!empty(array_filter($educationData))) {
                $candidate->educations()->create($educationData);
            }

            if (!empty(array_filter($profileData))) {
                $candidate->profile()->create($profileData);
            }

            return $candidate;
        });
    }

    /**
     * Find a potential duplicate candidate based on specific criteria within the last year.
     *
     * @param array $data
     * @return Candidate|null
     */
    public function findDuplicateCandidate(array $data): ?Candidate
    {
        if (empty($data['applicant_id']) && empty($data['email']) && (empty($data['nama']) || empty($data['jk']) || empty($data['tanggal_lahir']))) {
            return null;
        }

        $oneYearAgo = now()->subYear();

        $query = Candidate::query();

        $query->where(function ($q) use ($data) {
            if (!empty($data['applicant_id'])) {
                $q->orWhere('applicant_id', $data['applicant_id']);
            }

            if (!empty($data['email'])) {
                $q->orWhere('alamat_email', $data['email']);
            }

            if (!empty($data['nama']) && !empty($data['jk']) && !empty($data['tanggal_lahir'])) {
                $q->orWhere(function ($sub) use ($data) {
                    $sub->where('nama', $data['nama'])
                        ->where('jk', $data['jk'])
                        ->where('tanggal_lahir', $data['tanggal_lahir']);
                });
            }
        });

        // Filter by application date and status that indicates rejection
        $query->whereHas('applications', function ($appQuery) use ($oneYearAgo) {
            $appQuery->where('created_at', '>=', $oneYearAgo)
                ->whereIn('overall_status', ['DITOLAK', 'TIDAK LULUS', 'TIDAK DIHIRING']);
        });

        return $query->first();
    }

    /**
     * Update an existing candidate.
     *
     * @param Candidate $candidate
     * @param array $validatedData
     * @param Request $request
     * @return Candidate
     */
    public function updateCandidate(Candidate $candidate, array $validatedData, Request $request): Candidate
    {
        if ($request->hasFile('cv')) {
            // TODO: Delete old file if it exists
            $validatedData['cv'] = $request->file('cv')->store('private/cvs');
        } elseif (array_key_exists('cv', $validatedData) && is_null($validatedData['cv'])) {
            unset($validatedData['cv']);
        }
        if ($request->hasFile('flk')) {
            // TODO: Delete old file if it exists
            $validatedData['flk'] = $request->file('flk')->store('private/flks');
        } elseif (array_key_exists('flk', $validatedData) && is_null($validatedData['flk'])) {
            unset($validatedData['flk']);
        }

        $candidate->update($validatedData);

        return $candidate;
    }

    public function getDashboardStatistics(): array
    {
        $user = Auth::user();

        // Base query for candidates based on the user's role
        $candidateQuery = Candidate::query();
        if ($user->hasRole('department') && $user->department_id) {
            $candidateQuery->where('department_id', $user->department_id);
        }

        // Get IDs of candidates accessible by the user
        $accessibleCandidateIds = $candidateQuery->pluck('id');
        $total_candidates = $accessibleCandidateIds->count();

        // Base query for applications linked to accessible candidates
        $baseAppQuery = Application::whereIn('candidate_id', $accessibleCandidateIds);

        // --- Calculate Mutually Exclusive Stats ---

        // 1. Cancelled: Highest priority. If an application's latest stage is CANCEL, it's just cancelled.
        $cancelledAppIds = (clone $baseAppQuery)->whereHas('latestStage', function ($q) {
            $q->where('status', 'CANCEL');
        })->pluck('id');
        $candidates_cancelled = $cancelledAppIds->count();

        // 2. Passed: Applications with a final 'passed' status, AND are not cancelled.
        $passedStatuses = ['LULUS', 'DITERIMA', 'HIRED'];
        $candidates_passed = (clone $baseAppQuery)
            ->whereIn('overall_status', $passedStatuses)
            ->whereNotIn('id', $cancelledAppIds)
            ->count();

        // 3. Failed: Applications with a final 'failed' status, AND are not cancelled.
        $candidates_failed = (clone $baseAppQuery)
            ->whereIn('overall_status', ['DITOLAK', 'TIDAK LULUS'])
            ->whereNotIn('id', $cancelledAppIds)
            ->count();
            
        // 4. In Process: Total candidates minus all terminal states.
        $candidates_in_process = $total_candidates - ($candidates_passed + $candidates_failed + $candidates_cancelled);

        // --- Other Stats ---
        $duplicate_count = (clone $candidateQuery)->where('is_suspected_duplicate', true)->count();
        $needs_action_count = (clone $candidateQuery)->whereHas('applications', function ($q) {
            $q->where('overall_status', 'PROSES')
                ->whereHas('stages', function ($sq) {
                    $sq->whereDate('scheduled_date', '<=', now())
                        ->where(function ($ssq) {
                            $ssq->whereNull('status')
                                ->orWhere('status', '')
                                ->orWhere('status', 'IN_PROGRESS');
                        });
                });
        })->count();

        return [
            'total_candidates' => $total_candidates,
            'candidates_in_process' => $candidates_in_process,
            'candidates_passed' => $candidates_passed,
            'candidates_failed' => $candidates_failed,
            'candidates_cancelled' => $candidates_cancelled,
            'duplicate' => $duplicate_count,
            'needs_action' => $needs_action_count,
            'success_rate' => $total_candidates > 0 ? round(($candidates_passed / $total_candidates) * 100, 2) : 0,
            'rejection_rate' => $total_candidates > 0 ? round(($candidates_failed / $total_candidates) * 100, 2) : 0,
        ];
    }

    public function getFilteredCandidates(Request $request): LengthAwarePaginator
    {
        DB::enableQueryLog();

        $baseQuery = Candidate::with([
            'department',
            'applications' => function ($query) {
                $query->orderByDesc('updated_at');
            },
            'applications.stages',
            'applications.vacancy',
            'educations'
        ]);

        if (Auth::user()->hasRole('department') && Auth::user()->department_id) {
            $baseQuery->where('department_id', Auth::user()->department_id);
        }

        if ($request->filled('search')) {
            $baseQuery->search($request->search);
        }

        if ($request->filled('status')) {
            $baseQuery->whereHas('applications', function ($q) use ($request) {
                $q->where('overall_status', $request->status);
            });
        }

        if ($request->filled('gender')) {
            $baseQuery->byGender($request->gender);
        }

        if ($request->filled('source')) {
            $baseQuery->bySource($request->source);
        }

        if ($request->filled('current_stage')) {
            $baseQuery->whereHas('applications.stages', function ($q) use ($request) {
                $q->where('stage_name', $request->current_stage)->where('status', '!=', 'DITOLAK');
            });
        }

        if ($request->filled('vacancy_id')) {
            $baseQuery->whereHas('applications', function ($q) use ($request) {
                $q->where('vacancy_id', $request->vacancy_id);
            });
        }

        $type = $request->input('type', 'organic');
        switch ($type) {
            case 'non-organic':
                $baseQuery->airsysInternal(false);
                break;
            case 'duplicate':
                $baseQuery->where('is_suspected_duplicate', true);
                break;
            case 'organic':
            default:
                $baseQuery->airsysInternal(true);
                break;
        }

        $candidates = $baseQuery
            ->orderByRaw("CASE
                WHEN (SELECT overall_status FROM applications WHERE applications.candidate_id = candidates.id ORDER BY updated_at DESC LIMIT 1) IN ('LULUS', 'DITERIMA', 'HIRED') THEN 1
                ELSE 0
            END")
            ->orderBy('updated_at', 'desc')
            ->paginate(15);
            
        Log::info('Candidate List Query:', DB::getQueryLog());
        DB::disableQueryLog();

        return $candidates;
    }
}


        

                

        