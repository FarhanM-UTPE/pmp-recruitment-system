<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Vacancy;
use App\Models\MPPSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PositionApplicantController extends Controller
{
    public function index(Request $request)
    {
        $selectedYear = $request->input('year', date('Y'));

        $query = MPPSubmission::with([
            'department',
            'vacancies' => function ($query) {
                // Eager load only approved vacancies
                $query->where('mpp_submission_vacancy.proposal_status', 'approved');
            }
        ])
            ->whereIn('status', [MPPSubmission::STATUS_SUBMITTED, MPPSubmission::STATUS_APPROVED]);

        if ($selectedYear) {
            $query->where('year', $selectedYear);
        }

        $submissions = $query->get();

        $data = $submissions->groupBy('department.name')->map(function ($departmentSubmissions) {
            return $departmentSubmissions->flatMap(function ($submission) {
                // Now, $submission->vacancies contains only the approved ones
                return $submission->vacancies->map(function ($vacancy) use ($submission) {
                    $applicantCount = $vacancy->applications()
                        ->where('mpp_year', $submission->year)
                        ->count();

                    $acceptedCount = $vacancy->applications()
                        ->where('mpp_year', $submission->year)
                        ->whereIn('overall_status', ['HIRED', 'DITERIMA'])
                        ->count();

                    $neededCount = $vacancy->pivot->needed_count;
                    $status = 'Aktif';
                    if ($neededCount == 0) {
                        $status = 'Aktif (Jumlah Dibutuhkan Tidak Ditentukan)';
                    } elseif ($applicantCount >= $neededCount) {
                        $status = 'Aktif (Cukup Pelamar)';
                    } else {
                        $status = 'Aktif (Kurang Pelamar)';
                    }

                    return [
                        'id' => $vacancy->id,
                        'name' => $vacancy->name,
                        'mpp_year' => $submission->year,
                        'needed_count' => $neededCount,
                        'status' => $status,
                        'applicant_count' => $applicantCount,
                        'accepted_count' => $acceptedCount,
                    ];
                });
            })->groupBy('name');
        });

        $years = \App\Services\YearProvider::availableYears();

        return view('posisi-pelamar.index', [
            'departments' => $data,
            'years' => $years,
            'selectedYear' => $selectedYear,
        ]);
    }
}
