<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $source = $request->get('source');

        $user = Auth::user();
        $baseQuery = Application::query(); // Initialize $baseQuery here

        if ($user->hasRole('kepala departemen') && $user->department_id) {
            $baseQuery->whereHas('candidate', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }

        if ($startDate && $endDate) {
            $baseQuery->whereBetween('applications.created_at', [$startDate, $endDate]);
        }

        if ($source) {
            $baseQuery->whereHas('candidate', function ($q) use ($source) {
                $q->where('source', $source);
            });
        }

        $kpiData = $this->getKpiData(clone $baseQuery);
        $funnelData = $this->getRecruitmentFunnelData(clone $baseQuery);
        $sourceData = $this->getSourceEffectivenessData($startDate, $endDate, $source);
        $genderData = $this->getGenderDistributionData(clone $baseQuery);
        $universityData = $this->getUniversityDistributionData(clone $baseQuery);
        $universityPassRateData = $this->getUniversityPassRateData(clone $baseQuery);
        $monthlyData = $this->getMonthlyApplicationData($startDate, $endDate, $source);

        $passRateAnalysis = $this->getPassRateAnalysisData(clone $baseQuery);
        $timelineAnalysis = $this->getTimelineAnalysisData(clone $baseQuery);

        $sources = Candidate::whereNotNull('source')->distinct()->pluck('source');

        // $activeVacanciesQuery = \App\Models\Vacancy::whereHas('mppSubmissions', function ($q) {
        //     $q->where('proposal_status', 'approved');
        // })->with(['mppSubmissions' => function ($q) {
        //     $q->where('proposal_status', 'approved');
        // }, 'recruitmentSummaries']);

        // if ($user->hasRole('kepala departemen') && $user->department_id) {
        //     $activeVacanciesQuery->where('department_id', $user->department_id);
        // }

        // $activeVacancies = $activeVacanciesQuery->get();

        return view('statistics.index', compact(
            'kpiData',
            'funnelData',
            'sourceData',
            'genderData',
            'universityData',
            'universityPassRateData',
            'monthlyData',
            'passRateAnalysis',
            'timelineAnalysis',
            'sources',
            'startDate',
            'endDate',
            'source',
            // 'activeVacancies'
        ));
    }

    // private function getKpiData($query)
    // {
    //     $totalApplications = (clone $query)->count();
    //     $hiredApplicationsQuery = (clone $query)->where('overall_status', 'LULUS');
    //     $totalHired = $hiredApplicationsQuery->count();

    //     $avgTimeToHire = $hiredApplicationsQuery->selectRaw('AVG(DATEDIFF(hired_date, created_at)) as avg_days')
    //         ->value('avg_days');

    //     return [
    //         'total_applications' => $totalApplications,
    //         'total_hired' => $totalHired,
    //         'avg_time_to_hire' => round($avgTimeToHire ?? 0),
    //         'conversion_rate' => $totalApplications > 0 ? round(($totalHired / $totalApplications) * 100, 1) : 0,
    //     ];
    // }

    private function getKpiData($query)
    {
        $totalApplications = (clone $query)->count();
        $hiredApplicationsQuery = (clone $query)->where('applications.overall_status', 'LULUS');
        $totalHired = $hiredApplicationsQuery->count();

        $avgTimeToHire = $hiredApplicationsQuery->selectRaw('AVG(DATEDIFF(applications.updated_at, applications.created_at)) as avg_days')
            ->value('avg_days');

        $finalAvgDays = round($avgTimeToHire ?? 0);

        return [
            'total_applications' => $totalApplications,
            'total_hired' => $totalHired,
            'avg_time_to_hire' => $finalAvgDays,
            'conversion_rate' => $totalApplications > 0 ? round(($totalHired / $totalApplications) * 100, 1) : 0,
        ];
    }

    // private function getRecruitmentFunnelData($query)
    // {
    //     $totalApplications = (clone $query)->count();
        
    //     $stages = [
    //         'Psikotes' => (clone $query)->whereHas('stages', function($q) {$q->where('stage_name', 'psikotes');})->count(),
    //         'Interview HC' => (clone $query)->whereHas('stages', function($q) {$q->where('stage_name', 'hc_interview');})->count(),
    //         'Interview User' => (clone $query)->whereHas('stages', function($q) {$q->where('stage_name', 'user_interview');})->count(),
    //         'Offering' => (clone $query)->whereHas('stages', function($q) {$q->where('stage_name', 'offering_letter');})->count(),
    //         'Hired' => (clone $query)->where('overall_status', 'LULUS')->count(),
    //     ];

    //     $funnel = [];
    //     $previousStageCount = $totalApplications;

    //     foreach ($stages as $stageName => $count) {
    //         $conversionRate = $previousStageCount > 0 ? round(($count / $previousStageCount) * 100, 1) : 0;
    //         $funnel[] = [
    //             'stage' => $stageName,
    //             'count' => $count,
    //             'conversion' => $conversionRate,
    //         ];
    //         $previousStageCount = $count;
    //     }

    //     return $funnel;
    // }

    private function getSourceEffectivenessData($startDate, $endDate, $source)
    {
        $user = Auth::user();
        $query = DB::table('applications')
            ->join('candidates', 'applications.candidate_id', '=', 'candidates.id');

        if ($user->hasRole('kepala departemen') && $user->department_id) {
            $query->where('candidates.department_id', $user->department_id);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('applications.created_at', [$startDate, $endDate]);
        }

        if ($source) {
            $query->where('candidates.source', $source);
        }

        return $query->select('candidates.source', 
                DB::raw('COUNT(*) as total_applications'),
                DB::raw('SUM(CASE WHEN applications.overall_status = \'LULUS\' THEN 1 ELSE 0 END) as hired_count')
            )
            ->whereNotNull('candidates.source')
            ->groupBy('candidates.source')
            ->orderBy('hired_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $item->hire_rate = $item->total_applications > 0 ? round(($item->hired_count / $item->total_applications) * 100, 1) : 0;
                return $item;
            });
    }

    private function getGenderDistributionData($query)
    {
        $data = (clone $query)
            ->join('candidates', 'applications.candidate_id', '=', 'candidates.id')
            ->select('candidates.jk', DB::raw('count(*) as count'))
            ->groupBy('candidates.jk')
            ->get();

        // Transform the collection into a normalized associative array for Chart.js
        $transformedData = [
            'Laki-laki' => 0,
            'Perempuan' => 0,
        ];
        
        $unknownCount = 0;

        foreach ($data as $item) {
            $jk = trim($item->jk ?? '');
            $lowerJk = strtolower($jk);
            
            if (empty($jk)) {
                $unknownCount += $item->count;
            } elseif (in_array($lowerJk, ['laki-laki', 'l', 'laki laki', 'male'])) {
                $transformedData['Laki-laki'] += $item->count;
            } elseif (in_array($lowerJk, ['perempuan', 'p', 'female'])) {
                $transformedData['Perempuan'] += $item->count;
            } else {
                $unknownCount += $item->count;
            }
        }
        
        if ($unknownCount > 0) {
            $transformedData['Tidak Diketahui'] = $unknownCount;
        }

        return $transformedData;
    }

    private function getUniversityDistributionData($query)
    {
        $data = (clone $query)
            ->join('candidates', 'applications.candidate_id', '=', 'candidates.id')
            ->join('educations', 'candidates.id', '=', 'educations.candidate_id')
            ->select('educations.institution', DB::raw('count(*) as count'))
            ->whereNotNull('educations.institution')
            ->where('educations.institution', '!=', '')
            ->groupBy('educations.institution')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Transform the collection into an associative array for Chart.js
        $transformedData = [];
        foreach ($data as $item) {
            $transformedData[$item->institution] = $item->count;
        }

        return $transformedData;
    }

    private function getMonthlyApplicationData($startDate, $endDate, $source)
    {
        $user = Auth::user();
        $query = DB::table('applications')
            ->join('candidates', 'applications.candidate_id', '=', 'candidates.id')
            ->select(DB::raw('YEAR(applications.created_at) as year, MONTH(applications.created_at) as month'), DB::raw('COUNT(*) as count'));

        if ($user->hasRole('kepala departemen') && $user->department_id) {
            $query->where('candidates.department_id', $user->department_id);
        }

        $actualStartDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subMonths(11)->startOfMonth();
        $actualEndDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $query->whereBetween('applications.created_at', [$actualStartDate, $actualEndDate]);

        if ($source) {
            $query->where('candidates.source', $source);
        }

        $data = $query->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()->mapWithKeys(function ($item) {
                return [$item->year . '-' . $item->month => $item->count];
            });

        $period = CarbonPeriod::create($actualStartDate, '1 month', $actualEndDate);
        $result = [];

        foreach ($period as $date) {
            $key = $date->year . '-' . $date->month;
            $result[] = [
                'month' => $date->isoFormat('MMM YYYY'),
                'count' => $data->get($key) ?? 0,
            ];
        }

        return $result;
    }

    // private function getPassRateAnalysisData($baseQuery)
    // {
    //     $stages = [
    //         ['name' => 'Psikotes', 'stage_name' => 'psikotes', 'pass_values' => ['LULUS', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK LULUS', 'FAIL']],
    //         ['name' => 'Interview HC', 'stage_name' => 'hc_interview', 'pass_values' => ['LULUS', 'DISARANKAN', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK DISARANKAN', 'FAIL']],
    //         ['name' => 'Interview User', 'stage_name' => 'user_interview', 'pass_values' => ['LULUS', 'DISARANKAN', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK DISARANKAN', 'FAIL']],
    //         ['name' => 'Interview BOD', 'stage_name' => 'interview_bod', 'pass_values' => ['LULUS', 'DISARANKAN', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK DISARANKAN', 'FAIL']],
    //         ['name' => 'Offering Letter', 'stage_name' => 'offering_letter', 'pass_values' => ['DITERIMA', 'PASS', 'OK', 'DONE'], 'fail_values' => ['DITOLAK', 'FAIL']],
    //         ['name' => 'MCU', 'stage_name' => 'mcu', 'pass_values' => ['LULUS', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK LULUS', 'FAIL']],
    //         ['name' => 'Hired', 'stage_name' => 'hiring', 'pass_values' => ['HIRED', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK DIHIRING', 'FAIL']],
    //     ];

    //     $analysis = [];

    //     foreach ($stages as $stage) {
    //         $totalReachedQuery = (clone $baseQuery)->whereHas('stages', function($q) use ($stage) {
    //             $q->where('stage_name', $stage['stage_name']);
    //         });

    //         $totalReached = $totalReachedQuery->count();

    //         $passed = (clone $totalReachedQuery)->whereHas('stages', function($q) use ($stage) {
    //             $q->where('stage_name', $stage['stage_name'])->whereIn('status', $stage['pass_values']);
    //         })->count();

    //         $failed = (clone $totalReachedQuery)->whereHas('stages', function($q) use ($stage) {
    //             $q->where('stage_name', $stage['stage_name'])->whereIn('status', $stage['fail_values']);
    //         })->count();
            
    //         $totalEvaluated = $passed + $failed;
    //         $inProgress = $totalReached - $totalEvaluated;
    //         if ($inProgress < 0) {
    //             $inProgress = 0;
    //         }

    //         $pass_rate = 0;
    //         if ($totalEvaluated > 0) {
    //             $pass_rate = round(($passed / $totalEvaluated) * 100, 1);
    //         }

    //         $analysis[] = [
    //             'name' => $stage['name'],
    //             'total' => $totalReached,
    //             'passed' => $passed,
    //             'failed' => $failed,
    //             'in_progress' => $inProgress,
    //             'pass_rate' => $pass_rate,
    //         ];
    //     }

    //     return $analysis;
    // }

    
    private function getRecruitmentFunnelData($query)
    {
        $totalApplications = (clone $query)->count();
        $filteredApplicationIds = (clone $query)->pluck('applications.id')->toArray();

        // 1. Fetch all stages to determine highest reach
        $allStages = DB::table('application_stages')
            ->whereIn('application_id', $filteredApplicationIds)
            ->select('application_id', 'stage_name')
            ->get();

        $appHighestWeight = [];
        foreach ($allStages as $rec) {
            $appId = $rec->application_id;
            $sName = strtolower(trim($rec->stage_name));
            
            // Map stage weights
            $weight = match($sName) {
                'psikotes' => 1,
                'hc_interview' => 2,
                'user_interview' => 3,
                'interview_bod' => 4,
                'offering_letter' => 5,
                'mcu' => 6,
                'hiring' => 7,
                default => 0,
            };

            if (!isset($appHighestWeight[$appId]) || $weight > $appHighestWeight[$appId]) {
                $appHighestWeight[$appId] = $weight;
            }
        }

        // 2. Count using logical inference
        $counts = [
            'Psikotes' => 0,
            'Interview HC' => 0,
            'Interview User' => 0,
            'Offering' => 0,
        ];

        foreach ($appHighestWeight as $appId => $hw) {
            if ($hw >= 1) $counts['Psikotes']++;
            if ($hw >= 2) $counts['Interview HC']++;
            if ($hw >= 3) $counts['Interview User']++;
            if ($hw >= 5) $counts['Offering']++;
        }
        
        $counts['Hired'] = (clone $query)->where('overall_status', 'LULUS')->count();

        // 3. Build funnel array
        $funnel = [];
        $previousStageCount = $totalApplications;
        
        $stagesToMap = [
            'Psikotes' => $counts['Psikotes'],
            'Interview HC' => $counts['Interview HC'],
            'Interview User' => $counts['Interview User'],
            'Offering' => $counts['Offering'],
            'Hired' => $counts['Hired'],
        ];

        foreach ($stagesToMap as $stageName => $count) {
            $conversionRate = $previousStageCount > 0 ? round(($count / $previousStageCount) * 100, 1) : 0;
            $funnel[] = [
                'stage' => $stageName,
                'count' => $count,
                'conversion' => $conversionRate,
            ];
            $previousStageCount = $count;
        }

        return $funnel;
    }

    private function getPassRateAnalysisData($baseQuery)
    {
        $stageOrder = [
            'psikotes'        => 1,
            'hc_interview'    => 2,
            'user_interview'  => 3,
            'interview_bod'   => 4,
            'offering_letter' => 5,
            'mcu'             => 6,
            'hiring'          => 7,
        ];

        $stages = [
            ['name' => 'Psikotes', 'stage_name' => 'psikotes', 'pass_values' => ['LULUS', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK LULUS', 'FAIL']],
            ['name' => 'Interview HC', 'stage_name' => 'hc_interview', 'pass_values' => ['LULUS', 'DISARANKAN', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK DISARANKAN', 'FAIL']],
            ['name' => 'Interview User', 'stage_name' => 'user_interview', 'pass_values' => ['LULUS', 'DISARANKAN', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK DISARANKAN', 'FAIL']],
            ['name' => 'Interview BOD', 'stage_name' => 'interview_bod', 'pass_values' => ['LULUS', 'DISARANKAN', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK DISARANKAN', 'FAIL']],
            ['name' => 'Offering Letter', 'stage_name' => 'offering_letter', 'pass_values' => ['DITERIMA', 'PASS', 'OK', 'DONE'], 'fail_values' => ['DITOLAK', 'FAIL']],
            ['name' => 'MCU', 'stage_name' => 'mcu', 'pass_values' => ['LULUS', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK LULUS', 'FAIL']],
            ['name' => 'Hired', 'stage_name' => 'hiring', 'pass_values' => ['HIRED', 'PASS', 'OK', 'DONE'], 'fail_values' => ['TIDAK DIHIRING', 'FAIL']],
        ];

        $filteredApplicationIds = (clone $baseQuery)->pluck('applications.id')->toArray();

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

        // 1. Map candidate journeys
        $appJourneys = [];
        foreach ($allStages as $rec) {
            $appId = $rec->application_id;
            $sName = strtolower(trim($rec->stage_name));
            $weight = $stageOrder[$sName] ?? 0;

            if (!isset($appJourneys[$appId])) {
                $appJourneys[$appId] = [
                    'highest_weight' => 0,
                    'stages' => [],
                    'parent_status' => strtoupper(trim($rec->parent_status ?? '')),
                ];
            }

            if ($weight > $appJourneys[$appId]['highest_weight']) {
                $appJourneys[$appId]['highest_weight'] = $weight;
            }

            $appJourneys[$appId]['stages'][$sName] = strtoupper(trim($rec->status));
        }

        $analysis = [];

        // 2. Perform Logical Inference loop
        foreach ($stages as $stage) {
            $targetWeight = $stageOrder[$stage['stage_name']] ?? 0;

            $totalReached = 0;
            $passed = 0;
            $failed = 0;
            $inProgress = 0;

            foreach ($appJourneys as $appId => $journey) {
                $highestWeight = $journey['highest_weight'];
                $parentStatus = $journey['parent_status'];

                // OVERRIDE FIX: If the overall application status is 'LULUS' (Hired), 
                // it implies they successfully completed the entire funnel (Stage 7).
                if ($parentStatus === 'LULUS') {
                    $highestWeight = 7;
                }

                // If candidate ever reached this stage or beyond
                if ($highestWeight >= $targetWeight && $targetWeight > 0) {
                    $totalReached++;

                    // INFERENCE: Passed if highest weight is strictly greater than target weight
                    if ($highestWeight > $targetWeight) {
                        $passed++;
                    } else {
                        // Candidate's current ceiling is this stage, evaluate actual status
                        $rawStatus = $journey['stages'][$stage['stage_name']] ?? '';

                        if (in_array($parentStatus, ['CANCEL', 'PINDAH'])) {
                             // Mark as failed in analysis so they aren't calculated as 'progressing'
                             $failed++;
                        } else {
                            // FIX APPLIED HERE: Automatically pass them if overall_status is LULUS
                            if (in_array($rawStatus, $stage['pass_values']) || $parentStatus === 'LULUS') {
                                $passed++;
                            } elseif (in_array($rawStatus, $stage['fail_values']) || in_array($parentStatus, ['DITOLAK', 'FAILED'])) {
                                $failed++;
                            } elseif ($rawStatus === 'CANCEL') {
                                $failed++;
                            } else {
                                $inProgress++;
                            }
                        }
                    }
                }
            }

            $totalEvaluated = $passed + $failed;
            $pass_rate = 0;
            if ($totalEvaluated > 0) {
                $pass_rate = round(($passed / $totalEvaluated) * 100, 1);
            }

            $analysis[] = [
                'name' => $stage['name'],
                'total' => $totalReached,
                'passed' => $passed,
                'failed' => $failed,
                'in_progress' => $inProgress,
                'pass_rate' => $pass_rate,
            ];
        }

        return $analysis;
    }

    private function getTimelineAnalysisData($baseQuery)
    {
        $totalApplications = (clone $baseQuery)->count();
        \Log::info("Timeline Analysis - Total applications: {$totalApplications}");

        $allStages = [
            'Psikotes' => 'psikotes',
            'Interview HC' => 'hc_interview',
            'Interview User' => 'user_interview',
            'Interview BOD' => 'interview_bod',
            'Offering Letter' => 'offering_letter',
            'MCU' => 'mcu',
            'Hired' => 'hiring',
        ];

        $applications = (clone $baseQuery)->with([
            'candidate:id,nama',
            'stages' => function ($query) {
                $query->select('id', 'application_id', 'stage_name', 'status', 'created_at', 'updated_at')
                    ->whereNotNull('updated_at'); // Only consider completed stages
            }
        ])->get();

        \Log::info("Timeline Analysis - Applications loaded: " . $applications->count());

        $allDurations = [];

        foreach ($applications as $application) {
            if (!$application->candidate || $application->stages->isEmpty()) {
                continue;
            }

            foreach ($application->stages as $stage) {
                $startTime = $stage->created_at;
                $endTime = $stage->updated_at;

                if ($startTime && $endTime) {
                    $startCarbon = is_string($startTime) ? Carbon::parse($startTime) : $startTime;
                    $endCarbon = is_string($endTime) ? Carbon::parse($endTime) : $endTime;

                    if ($endCarbon->greaterThanOrEqualTo($startCarbon)) {
                        $duration = $startCarbon->diffInDays($endCarbon);
                        $stageName = array_search($stage->stage_name, $allStages);

                        if ($stageName) {
                            $allDurations[$stageName][] = [
                                'duration' => $duration,
                                'candidate_name' => $application->candidate->nama ?? 'Unknown',
                                'candidate_id' => $application->candidate->id ?? null,
                            ];
                        }
                    }
                }
            }
        }

        $analysis = [];
        foreach ($allStages as $stageName => $stageKey) {
            if (isset($allDurations[$stageName])) {
                $durationsWithCandidates = $allDurations[$stageName];
                $durationsCollection = collect($durationsWithCandidates);
                $minDurationItem = $durationsCollection->sortBy('duration')->first();
                $maxDurationItem = $durationsCollection->sortByDesc('duration')->first();

                $analysis[] = [
                    'stage_name' => $stageName,
                    'avg_days' => round($durationsCollection->pluck('duration')->avg(), 1),
                    'min_days' => $minDurationItem['duration'],
                    'min_days_candidate_name' => $minDurationItem['candidate_name'],
                    'min_days_candidate_id' => $minDurationItem['candidate_id'],
                    'max_days' => $maxDurationItem['duration'],
                    'max_days_candidate_name' => $maxDurationItem['candidate_name'],
                    'max_days_candidate_id' => $maxDurationItem['candidate_id'],
                ];
            } else {
                $analysis[] = [
                    'stage_name' => $stageName,
                    'avg_days' => 0,
                    'min_days' => 0,
                    'max_days' => 0,
                    'min_days_candidate_name' => null,
                    'min_days_candidate_id' => null,
                    'max_days_candidate_name' => null,
                    'max_days_candidate_id' => null,
                ];
            }
        }

        \Log::info("Timeline Analysis - Final analysis count: " . count($analysis));
        return $analysis;
    }

    /**
     * Get universities with most graduates (LULUS status)
     */
    private function getUniversityPassRateData($query)
    {
        $data = (clone $query)
            ->join('candidates', 'applications.candidate_id', '=', 'candidates.id')
            ->join('educations', 'candidates.id', '=', 'educations.candidate_id')
            ->select(
                'educations.institution',
                DB::raw('COUNT(*) as total_candidates'),
                DB::raw('SUM(CASE WHEN applications.overall_status = \'LULUS\' THEN 1 ELSE 0 END) as passed_count')
            )
            ->whereNotNull('educations.institution')
            ->where('educations.institution', '!=', '')
            ->groupBy('educations.institution')
            ->orderByDesc('passed_count')
            ->limit(15)
            ->get();

        // Transform untuk pie chart
        $transformedData = [];
        foreach ($data as $item) {
            $transformedData[] = [
                'institution' => $item->institution,
                'passed_count' => $item->passed_count,
                'total_candidates' => $item->total_candidates,
                'pass_rate' => $item->total_candidates > 0 ? round(($item->passed_count / $item->total_candidates) * 100, 1) : 0,
            ];
        }

        return $transformedData;
    }

    /**
     * Get summary statistics for candidates status
     */
    public function getSummaryStats()
    {
        $stats = [
            'total_candidates' => Candidate::count(),
            'in_process' => Application::where('overall_status', 'PROSES')->count(),
            'passed' => Application::where('overall_status', 'LULUS')->count(),
            'rejected' => Application::where('overall_status', 'DITOLAK')->count(),
            'cancelled' => Application::where('overall_status', 'CANCEL')->count(),
        ];

        return $stats;
    }
}
