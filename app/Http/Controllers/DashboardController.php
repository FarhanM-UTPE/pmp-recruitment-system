<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Event;
use App\Models\Department;
use App\Models\Application;
use App\Models\MasterData;
use App\Services\YearProvider;
use App\Models\ApplicationStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $user = Auth::user();

        $baseQuery = Application::query();
        if ($user->hasRole('kepala departemen')) {
            $baseQuery->whereHas('candidate', function ($query) use ($user) {
                $query->where('department_id', $user->department_id);
            });
        }

        // Filter by mpp_year to match Candidate List
        if ($year) {
            $baseQuery->where('applications.mpp_year', $year);
        }

        // Get real statistics based on overall_status
        // $stats = [
        //     'total_candidates' => (clone $baseQuery)->count(),
        //     'candidates_in_process' => (clone $baseQuery)->where('overall_status', 'PROSES')->count(),
        //     'candidates_passed' => (clone $baseQuery)->where('overall_status', 'LULUS')->count(),
        //     'candidates_failed' => (clone $baseQuery)->where('overall_status', 'DITOLAK')->count(),
        //     'candidates_cancelled' => (clone $baseQuery)->where('overall_status', 'CANCEL')->count(),
        // ];
        $filteredApplicationIds = (clone $baseQuery)->pluck('applications.id')->toArray();

        $stats = [
            'total_candidates' => 0,
            'candidates_in_process' => 0,
            'candidates_passed' => 0,
            'candidates_failed' => 0,
            'candidates_cancelled' => 0,
        ];

        if (!empty($filteredApplicationIds)) {
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

        $masterDataCancelSum = DB::table('master_data')
            ->where('type', 'candidate_stage_status')
            ->where('key', 'LIKE', '%|CANCEL')
            ->where('active', 1)
            ->when($year, function ($q) use ($year) {
                return $q->where('year', $year);
            })
            ->when($user->hasRole('kepala departemen') && $user->department_id, function ($q) use ($user) {
                return $q->where('department_id', $user->department_id);
            })
            ->sum('value');

        // 2. Sum all values where key ends with TIDAK LULUS
        $masterDataFailSum = DB::table('master_data')
            ->where('type', 'candidate_stage_status')
            ->where('key', 'LIKE', '%|TIDAK LULUS')
            ->where('active', 1)
            ->when($year, function ($q) use ($year) {
                return $q->where('year', $year);
            })
            ->when($user->hasRole('kepala departemen') && $user->department_id, function ($q) use ($user) {
                return $q->where('department_id', $user->department_id);
            })
            ->sum('value');

        // 3. Grand total sum of all values in the master_data table for this type
        $masterDataTotalSum = DB::table('master_data')
            ->where('type', 'candidate_stage_status')
            ->where('active', 1)
            ->when($year, function ($q) use ($year) {
                return $q->where('year', $year);
            })
            ->when($user->hasRole('kepala departemen') && $user->department_id, function ($q) use ($user) {
                return $q->where('department_id', $user->department_id);
            })
            ->sum('value');

        // Append the values to your stats logic
        $stats['candidates_cancelled'] += (int) $masterDataCancelSum;
        $stats['candidates_failed'] += (int) $masterDataFailSum;
        $stats['total_candidates'] += (int) $masterDataTotalSum;

        $applications = (clone $baseQuery)->with([
            'stages' => function ($query) {
                $query->orderBy('scheduled_date', 'desc')->orderBy('id', 'desc');
            }
        ])->get();

        $recentCandidatesQuery = Candidate::with('department', 'applications')
            ->orderBy('created_at', 'desc')
            ->limit(5);
        if ($user->hasRole('kepala departemen')) {
            $recentCandidatesQuery->where('department_id', $user->department_id);
        }

        $oldestCandidateQuery = Candidate::select('candidates.*')
            ->with('department', 'applications.vacancy')
            ->whereHas('applications', function ($query) {
                $query->whereIn('overall_status', ['PROSES', 'On Process']);
            })
            ->addSelect([
                'last_stage_updated_at' => ApplicationStage::select('application_stages.updated_at')
                    ->join('applications', 'application_stages.application_id', '=', 'applications.id')
                    ->whereColumn('applications.candidate_id', 'candidates.id')
                    ->orderBy('application_stages.updated_at', 'desc')
                    ->limit(1)
            ])
            ->orderBy('last_stage_updated_at', 'asc')
            ->limit(5);

        // Check if the user selected a specific department from the dropdown
        if ($request->filled('oldest_dept')) {
            $oldestCandidateQuery->where('department_id', $request->oldest_dept);
        }
        // Otherwise, fallback to the default role restriction
        elseif ($user->hasRole('kepala departemen')) {
            $oldestCandidateQuery->where('department_id', $user->department_id);
        }

        $oldest_candidates = $oldestCandidateQuery->get();
        $recent_candidates = $recentCandidatesQuery->get();

        $distributionQuery = ApplicationStage::whereIn('id', function ($query) use ($year, $user) {
            $query->select(DB::raw('MAX(id)'))
                ->from('application_stages')
                ->whereIn('application_id', function ($sub) use ($year, $user) {
                    $sub->select('id')
                        ->from('applications')
                        ->where('overall_status', 'PROSES');

                    if ($year) {
                        $sub->where('mpp_year', $year);
                    }

                    if ($user->hasRole('kepala departemen')) {
                        $sub->whereIn('candidate_id', function ($csub) use ($user) {
                            $csub->select('id')
                                ->from('candidates')
                                ->where('department_id', $user->department_id);
                        });
                    }
                })
                ->groupBy('application_id');
        });
        $stageOrder = [
            'psikotes',
            'hc_interview',
            'user_interview',
            'interview_bod',
            'offering_letter',
            'mcu',
            'hiring',
        ];

        $stageDisplayMap = [
            'psikotes' => 'Psikotes',
            'hc_interview' => 'HC Interview',
            'user_interview' => 'User Interview',
            'interview_bod' => 'BOD/GM Interview',
            'offering_letter' => 'Offering Letter',
            'mcu' => 'MCU',
            'hiring' => 'Hiring',
        ];

        $allStages = collect($stageDisplayMap)->map(function ($displayName, $stageKey) {
            return (object) [
                'stage_name' => $stageKey,
                'count' => 0,
            ];
        })->keyBy('stage_name');

        $distributionData = $distributionQuery
            ->groupBy('stage_name')
            ->selectRaw('stage_name, COUNT(*) as count')
            ->get()
            ->keyBy('stage_name');

        $mergedData = $allStages->merge($distributionData);

        $stageOrderMap = array_flip($stageOrder);
        $sortedData = $mergedData->sortBy(function ($item) use ($stageOrderMap) {
            return $stageOrderMap[$item->stage_name] ?? 999;
        });

        $process_distribution = $sortedData->map(function ($item) use ($stageDisplayMap) {
            return [
                'stage' => $item->stage_name,
                'display_name' => $stageDisplayMap[$item->stage_name] ?? Str::title(str_replace('_', ' ', $item->stage_name)),
                'count' => $item->count
            ];
        })->values();

        // Gender Distribution - Show all including NULL/empty as "Tidak Diketahui"
        $genderRaw = (clone $baseQuery)
            ->join('candidates', 'applications.candidate_id', '=', 'candidates.id')
            ->select('candidates.jk', DB::raw('count(*) as count'))
            ->groupBy('candidates.jk')
            ->get();

        // Normalize gender values - combine all empty/null as unknown
        $genderMap = [];
        $unknownCount = 0;

        foreach ($genderRaw as $item) {
            $jk = trim($item->jk ?? '');

            if (empty($jk)) {
                // All NULL and empty string combined as unknown
                $unknownCount += $item->count;
            } else {
                // Normalize gender values
                $normalizedJk = $jk;
                $lowerJk = strtolower($jk);

                if (in_array($lowerJk, ['laki-laki', 'l', 'laki laki', 'male'])) {
                    $normalizedJk = 'L';
                } elseif (in_array($lowerJk, ['perempuan', 'p', 'female'])) {
                    $normalizedJk = 'P';
                }

                if (!isset($genderMap[$normalizedJk])) {
                    $genderMap[$normalizedJk] = 0;
                }
                $genderMap[$normalizedJk] += $item->count;
            }
        }

        // Build final collection
        $gender_distribution = collect();

        // Add known genders first (sorted by count)
        arsort($genderMap);
        foreach ($genderMap as $jk => $count) {
            $gender_distribution->push((object) ['jk' => $jk, 'count' => $count]);
        }

        // Add unknown at the end
        if ($unknownCount > 0) {
            $gender_distribution->push((object) ['jk' => null, 'count' => $unknownCount]);
        }

        // University Distribution - Using same approach as StatisticsController
        $university_distribution = (clone $baseQuery)
            ->join('candidates', 'applications.candidate_id', '=', 'candidates.id')
            ->join('educations', 'candidates.id', '=', 'educations.candidate_id')
            ->select('educations.institution as perguruan_tinggi', DB::raw('count(*) as count'))
            ->whereNotNull('educations.institution')
            ->where('educations.institution', '!=', '')
            ->groupBy('educations.institution')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // $departments = [];
        // if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
        //     $departments = Department::orderBy('name')->get();
        // }
        $departments = [];
        if ($user->hasRole(['admin', 'super_admin', 'team_hc', 'team_hc_2'])) {
            $departments = Department::orderBy('name')->get();
        }

        $summaryMonth = $request->get('summary_month', Carbon::now()->subMonthNoOverflow()->month);
        $summaryYear = $request->get('summary_year', $request->get('year', Carbon::now()->subMonthNoOverflow()->year));
        $monthlySummary = $this->getMonthlySummary($summaryYear, $summaryMonth);

        $availableYears = YearProvider::availableYears();

        return view('dashboard', [
            'stats' => $stats,
            'recent_candidates' => $recent_candidates,
            'oldest_candidates' => $oldest_candidates,
            'process_distribution' => $process_distribution,
            'monthlySummary' => $monthlySummary,
            'summaryMonth' => $summaryMonth,
            'summaryYear' => $summaryYear,
            'availableYears' => $availableYears,
            'year' => $year,
            'departments' => $departments,
            'gender_distribution' => $gender_distribution,
            'university_distribution' => $university_distribution,
        ]);
    }

    private function getMonthlySummary($year, $month)
    {
        $year = intval($year);
        $month = intval($month);

        $date = Carbon::createFromDate($year, $month, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $applications = Application::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->when($year, function ($query) use ($year) {
                return $query->where('mpp_year', $year);
            })
            ->count();

        $hired = Application::where('overall_status', 'LULUS')
            ->when($year, function ($query) use ($year) {
                return $query->where('mpp_year', $year);
            })
            ->whereBetween('hired_date', [$startOfMonth, $endOfMonth])
            ->count();

        return [
            'month_name' => $date->isoFormat('MMMM YYYY'),
            'total_applications' => $applications,
            'total_hired' => $hired,
            'conversion_rate' => $applications > 0 ? round(($hired / $applications) * 100, 1) : 0,
            'filter_url' => route('candidates.index', [
                'created_from' => $startOfMonth->toDateString(),
                'created_to' => $endOfMonth->toDateString(),
                'year' => $year ?: null,
            ]),
        ];
    }

    public function getCalendarEvents(Request $request)
    {
        $user = Auth::user();
        $events = [];

        // Get only PENDING/IN-PROGRESS scheduled stages for each application
        $stagesQuery = ApplicationStage::with(['application.candidate'])
            ->whereNotNull('scheduled_date')
            ->where(function ($q) {
                $q->whereIn('status', ['PENDING', 'PROSES', 'MENUNGGU'])
                    ->orWhereNull('status');
            })
            ->whereHas('application', function ($q) {
                $q->whereNotIn('overall_status', ['CANCEL', 'PINDAH']);
            })
            ->whereDate('scheduled_date', '>=', now()->startOfYear())
            ->whereDate('scheduled_date', '<=', now()->addYear()->endOfYear());

        if ($user->hasRole('kepala departemen')) {
            $stagesQuery->whereHas('application.candidate', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }

        // Get all scheduled stages and group by application to find the latest one
        $allScheduledStages = $stagesQuery->get();
        $latestStagesByApplication = $allScheduledStages->groupBy('application_id')->map(function ($stages) {
            // Sort by scheduled_date desc, then by id desc to get the most recent one
            return $stages->sortByDesc(function ($stage) {
                return $stage->scheduled_date . '_' . $stage->id;
            })->first();
        });

        // Process ApplicationStage events first
        $processed = []; // To track candidate_id + date to avoid duplicates

        foreach ($latestStagesByApplication as $stage) {
            $displayName = Candidate::formatStageName($stage->stage_name);
            $candidateName = $stage->application->candidate->nama ?? 'Unknown';
            $dateKey = Carbon::parse($stage->scheduled_date)->format('Y-m-d');
            $candidateId = $stage->application->candidate_id;

            $events[] = [
                'id' => 'stage_' . $stage->id,
                'type' => 'candidate_test',
                'title' => $candidateName . ' - ' . $displayName,
                'description' => 'Jadwal ' . $displayName . ' untuk ' . $candidateName,
                'date' => $dateKey,
                'time' => Carbon::parse($stage->scheduled_date)->format('H:i'),
                'url' => route('candidates.show', $candidateId),
                'candidate_id' => $candidateId,
                'stage' => $stage->stage_name,
                'applicant_id' => $stage->application->candidate->applicant_id ?? 'N/A',
                'is_custom' => false
            ];

            // Mark this candidate + date as processed
            $processed[$candidateId . '_' . $dateKey] = true;
        }

        $customEventsQuery = Event::whereNotNull('date')
            ->whereDate('date', '>=', now()->startOfYear())
            ->whereDate('date', '<=', now()->addYear()->endOfYear());

        if ($user->hasRole('kepala departemen')) {
            $customEventsQuery->where('department_id', $user->department_id);
        }

        $customEvents = $customEventsQuery->get();

        foreach ($customEvents as $event) {
            $dateKey = Carbon::parse($event->date)->format('Y-m-d');
            $candidateId = $event->candidate_id;

            // Skip if we already have an ApplicationStage event for this candidate on this date
            if ($candidateId && isset($processed[$candidateId . '_' . $dateKey])) {
                continue;
            }

            $events[] = [
                'id' => 'custom_' . $event->id,
                'type' => 'custom_event',
                'title' => $event->title,
                'description' => $event->description ?? 'No description',
                'date' => $dateKey,
                'time' => $event->time ? Carbon::parse($event->time)->format('H:i') : null,
                'url' => $candidateId ? route('candidates.show', $candidateId) : '#',
                'is_custom' => true
            ];
        }

        return response()->json($events);
    }

    public function getCandidateStatsByMonth(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $user = Auth::user();
        $query = Application::query();

        if ($year) {
            $query->where('mpp_year', $year);
        }

        if ($user->hasRole('kepala departemen')) {
            $query->whereHas('candidate', function ($cq) use ($user) {
                $cq->where('department_id', $user->department_id);
            });
        }

        $stats = $query
            ->selectRaw('MONTH(created_at) as month,
                        COUNT(*) as total,
                        SUM(CASE WHEN overall_status = "LULUS" THEN 1 ELSE 0 END) as passed,
                        SUM(CASE WHEN overall_status = "PROSES" THEN 1 ELSE 0 END) as in_process,
                        SUM(CASE WHEN overall_status IN ("TIDAK LULUS", "DITOLAK") THEN 1 ELSE 0 END) as failed')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthlyStats = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthlyStats[] = [
                'month' => $month,
                'month_name' => Carbon::create()->month($month)->format('M'),
                'total' => $stats->get($month)->total ?? 0,
                'passed' => $stats->get($month)->passed ?? 0,
                'in_process' => $stats->get($month)->in_process ?? 0,
                'failed' => $stats->get($month)->failed ?? 0,
            ];
        }

        return response()->json($monthlyStats);
    }

    public function getAvailableYears()
    {
        $user = Auth::user();
        // Use the unified YearProvider so all pages return the same year list
        $years = \App\Services\YearProvider::availableYears();
        return response()->json($years);
    }
}
