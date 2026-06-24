<?php

namespace App\Imports;

use App\Models\Candidate;
use App\Models\Application;
use App\Models\Department;
use App\Models\Vacancy;
use App\Models\MPPSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class CandidatesImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected int $processed = 0;
    protected int $skipped = 0;
    protected int $userId;
    protected array $errors = []; // Collect error details

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        Log::debug('CandidatesImport: Constructor called', ['userId' => $userId]);
    }

    /**
     * ========================================================================
     * IMPORT LOGIC: VACANCY-BASED DEPARTMENT RESOLUTION
     * ========================================================================
     * 
     * FITUR BARU:
     * Sistem import sekarang mendukung penentuan departemen otomatis dari vacancy.
     * Tahun MPP sekarang dibaca per baris dari file Excel.
     * 
     * ALUR LOGIKA:
     * 1. Jika 'vacancy' & 'tahun_mpp' disediakan di file:
     *    - Cari vacancy di database berdasarkan nama DAN tahun MPP
     *    - JIKA TIDAK DITEMUKAN → IMPORT GAGAL (SKIP ROW) ❌
     *    - JIKA DITEMUKAN → Ambil department dari vacancy ✅
     * 
     * 2. Jika 'vacancy' kosong/tidak ada:
     *    - Gunakan kolom 'department' dari file (fallback)
     *    - Buat department baru jika belum ada
     * 
     * KOLOM YANG DIGUNAKAN:
     * - applicant_id: column for applicant ID (wajib)
     * - nama: column for candidate full name (wajib)
     * - vacancy: column for vacancy name (wajib jika ada tahun_mpp)
     * - tahun_mpp: column for MPP year (wajib jika ada vacancy)
     * - department: column for department name (opsional)
     * 
     * ========================================================================
     */

    public function chunkSize(): int
    {
        return 50; // Process 50 rows at a time
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowIndex = $index + 2; // Excel row number

            // ================= SAFETY NORMALIZATION =================
            $name = strtoupper(trim($row['nama'] ?? ''));
            if (empty($name)) {
                $this->skipped++;
                Log::info('CandidatesImport: Skip empty name', ['row' => $rowIndex]);
                continue;
            }

            $applicantId = trim($row['applicant_id'] ?? '');
            if (empty($applicantId)) {
                $this->skipped++;
                Log::info('CandidatesImport: Skip empty applicant_id', ['row' => $rowIndex]);
                continue;
            }

            // ================= VACANCY & DEPARTMENT RESOLVE =================
            $vacancyName = trim($row['vacancy'] ?? null);
            $mppYear = trim($row['tahun_mpp'] ?? null);
            $vacancy = null;
            $departmentId = null;
            $rawDept = trim($row['department'] ?? $row['raw_department_name'] ?? '');

            if ($vacancyName && !$mppYear) {
                $this->skipped++;
                $this->errors[] = ['row' => $rowIndex, 'applicant_id' => $applicantId, 'nama' => $name, 'error' => 'Kolom "Tahun MPP" wajib diisi jika "Vacancy" diisi.'];
                Log::error('CandidatesImport: Missing Tahun MPP', ['row' => $rowIndex, 'applicant_id' => $applicantId, 'vacancy_name' => $vacancyName]);
                continue;
            }

            if ($vacancyName && $mppYear) {
                Log::debug('CandidatesImport: Attempting to find vacancy for row', [
                    'row' => $rowIndex,
                    'applicant_id' => $applicantId,
                    'vacancy_name_from_file' => $vacancyName,
                    'mpp_year_from_file' => $mppYear
                ]);

                // Eager load the specific MPP submission relationship
                // Modified to accept vacancy with proposal_status 'approved'
                // even if MPP submission status is still 'submitted'

                $vacancy = Vacancy::where('name', $vacancyName)
                    ->with([
                        'mppSubmissions' => function ($query) use ($mppYear) {
                            $query->where('year', $mppYear)
                                ->whereIn('submission_type', ['planned', 'unplanned'])
                                ->whereIn('status', [
                                    MPPSubmission::STATUS_APPROVED,
                                    MPPSubmission::STATUS_SUBMITTED
                                ])
                                ->where('mpp_submission_vacancy.proposal_status', 'approved');
                        }
                    ])
                    ->whereHas('mppSubmissions', function ($q) use ($mppYear) {
                        $q->where('year', $mppYear)
                            ->whereIn('submission_type', ['planned', 'unplanned'])
                            ->whereIn('status', [
                                MPPSubmission::STATUS_APPROVED,
                                MPPSubmission::STATUS_SUBMITTED
                            ])
                            ->where('mpp_submission_vacancy.proposal_status', 'approved');
                    })->first();

                // Get the pivot data from the loaded relationship
                $pivotData = $vacancy && $vacancy->mppSubmissions->isNotEmpty()
                    ? $vacancy->mppSubmissions->first()->pivot
                    : null;

                if (!$vacancy || !$pivotData) {
                    $this->skipped++;
                    $errorMessage = 'Invalid Vacancy: "' . $vacancyName . '" for MPP year ' . $mppYear . ' was not found or not approved. Row skipped.';

                    $this->errors[] = [
                        'row' => $rowIndex,
                        'applicant_id' => $applicantId,
                        'nama' => $name,
                        'vacancy_name_provided' => $vacancyName,
                        'error' => $errorMessage,
                    ];

                    Log::warning('CandidatesImport: Vacancy not found or not approved. Skipping row.', [
                        'row' => $rowIndex,
                        'applicant_id' => $applicantId,
                        'vacancy_name_provided' => $vacancyName,
                        'year_used' => $mppYear,
                    ]);

                    continue;
                } else {
                    Log::debug('CandidatesImport: Vacancy found for row', [
                        'row' => $rowIndex,
                        'id' => $vacancy->id,
                        'mpp_year' => $mppYear,
                    ]);

                    if ($vacancy->department_id) {
                        $departmentId = $vacancy->department_id;
                        Log::info('CandidatesImport: Department resolved from vacancy', [
                            'row' => $rowIndex,
                            'department_id' => $departmentId,
                        ]);
                    }
                }
            }

            // Priority 2: Use provided department name if vacancy didn't provide it
            if (!$departmentId && $rawDept !== '') {
                $departmentId = Department::firstOrCreate(
                    ['name' => $rawDept],
                    ['created_at' => now()]
                )->id;
                Log::info('CandidatesImport: Department resolved from raw department name', [
                    'row' => $rowIndex,
                    'department_id' => $departmentId,
                ]);
            }

            // ================= GENDER NORMALIZATION =================
            // Convert FEMALE/MALE to Laki-laki/Perempuan
            $genderRaw = trim($row['jk'] ?? '');
            $genderNormalized = null;

            if ($genderRaw !== '') {
                $genderLower = strtolower($genderRaw);
                if (in_array($genderLower, ['female', 'perempuan', 'p', 'wanita'])) {
                    $genderNormalized = 'Perempuan';
                } elseif (in_array($genderLower, ['male', 'laki-laki', 'l', 'pria'])) {
                    $genderNormalized = 'Laki-laki';
                }
            }

            // ================= PSIKOTEST LOGIC (REVISED) =================
            // Try to extract psikotest result from multiple possible field names
            $psikotestResultRaw = '';
            $possibleFields = ['psikotest_result', 'psikotes_result', 'hasil', 'result', 'status', 'keterangan', 'hasil_psikotes'];

            foreach ($possibleFields as $field) {
                if (!empty($row[$field])) {
                    $psikotestResultRaw = strtolower(trim($row[$field]));
                    break;
                }
            }

            $psikotestResult = 'PROSES'; // Default
            $isPass = false;
            $isFail = false;
            $isRetest = false;

            // Normalize status
            if (in_array($psikotestResultRaw, ['lulus', 'pass', 'passed', 'pass psikotes', 'lulus psikotes'])) {
                $psikotestResult = 'LULUS';
                $isPass = true;
            } elseif (in_array($psikotestResultRaw, ['gagal', 'fail', 'failed', 'tidak lulus', 'fail psikotes', 'gagal psikotes'])) {
                $psikotestResult = 'TIDAK LULUS';
                $isFail = true;
            } elseif (in_array($psikotestResultRaw, ['retest', 'ulang', 'retry', 'tes ulang', 'psikotes ulang', 'dipertimbangkan'])) {
                $psikotestResult = 'DIPERTIMBANGKAN';
                $isRetest = true;
            }

            // Log the extracted status for debugging
            if ($psikotestResultRaw !== '') {
                Log::info('CandidatesImport: Extracted psikotes result', [
                    'row' => $index + 2,
                    'raw_value' => $psikotestResultRaw,
                    'normalized' => $psikotestResult,
                ]);
            }

            // Determine candidate status
            if ($isFail) {
                $candidateStatus = 'active'; // Kandidat tetap aktif, hanya aplikasi yang ditolak
                $overallStatus = 'DITOLAK';
            } elseif ($isPass) {
                $candidateStatus = 'active';
                $overallStatus = 'PROSES'; // Still in process, not final LULUS until hiring
            } else {
                $candidateStatus = 'active';
                $overallStatus = 'PROSES';
            }

            // Determine airsys_internal based on vacancy's MPP status
            $airsysInternal = null; // Default to null
            if ($pivotData && $pivotData->vacancy_status) {
                // Assuming 'OSPKWT' means internal and 'OS' means external/non-organic
                if (in_array($pivotData->vacancy_status, ['OSPKWT', 'INTERNAL'])) { // Add 'INTERNAL' for clarity if needed
                    $airsysInternal = 'Yes';
                } elseif (in_array($pivotData->vacancy_status, ['OS', 'EXTERNAL'])) { // Add 'EXTERNAL' for clarity if needed
                    $airsysInternal = 'No';
                }
            }

            // ================= BIRTH DATE VALIDATION =================
            // Support both header formats: 'tanggal_lahir' (normalized) and 'tanggal lahir' (with space)
            $birthDate = null;
            $birthDateValue = $row['tanggal_lahir'] ?? $row['tanggal lahir'] ?? null;

            if (!$birthDateValue) {
                // Empty birth date - skip row
                $this->skipped++;
                $this->errors[] = ['row' => $rowIndex, 'applicant_id' => $applicantId, 'nama' => $name, 'error' => 'Tanggal lahir wajib diisi'];
                Log::warning('CandidatesImport: Missing birth date', ['row' => $rowIndex, 'applicant_id' => $applicantId]);
                continue;
            }

            try {
                // Use official PhpSpreadsheet Date library for Excel serial conversion
                // This handles both numeric (Excel serial) and text date formats
                if (is_numeric($birthDateValue)) {
                    // Excel serial number
                    $birthDate = \Carbon\Carbon::instance(Date::excelToDateTimeObject($birthDateValue));
                    Log::debug('CandidatesImport: Parsed Excel serial date', [
                        'serial' => $birthDateValue,
                        'result' => $birthDate->format('Y-m-d'),
                        'row' => $rowIndex,
                    ]);
                } else {
                    // Text format - try Carbon parse which supports multiple formats
                    $birthDate = \Carbon\Carbon::parse($birthDateValue);
                    Log::debug('CandidatesImport: Parsed text date', [
                        'raw' => $birthDateValue,
                        'result' => $birthDate->format('Y-m-d'),
                        'row' => $rowIndex,
                    ]);
                }

                // Validate year is reasonable (between 1930 and current year)
                if ($birthDate->year < 1930 || $birthDate->year > date('Y')) {
                    $this->skipped++;
                    $this->errors[] = [
                        'row' => $rowIndex,
                        'applicant_id' => $applicantId,
                        'nama' => $name,
                        'error' => 'Tahun lahir tidak valid: ' . $birthDate->format('Y-m-d') . '. Tahun harus antara 1930 dan ' . date('Y')
                    ];
                    Log::warning('CandidatesImport: Birth year out of range', [
                        'row' => $rowIndex,
                        'applicant_id' => $applicantId,
                        'parsed_year' => $birthDate->year,
                        'value' => $birthDateValue,
                    ]);
                    continue;
                }
            } catch (\Exception $e) {
                // Failed to parse by any method
                $this->skipped++;
                $this->errors[] = [
                    'row' => $rowIndex,
                    'applicant_id' => $applicantId,
                    'nama' => $name,
                    'error' => 'Tidak dapat membaca format tanggal lahir: ' . (string) $birthDateValue
                ];
                Log::warning('CandidatesImport: Could not parse birth date', [
                    'row' => $rowIndex,
                    'applicant_id' => $applicantId,
                    'value' => $birthDateValue,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            // ================= UPSERT CANDIDATE =================
            $existingCandidate = Candidate::where('applicant_id', $applicantId)->first();
            $cvFromImport = isset($row['cv']) ? trim((string) $row['cv']) : '';
            $flkFromImport = isset($row['flk']) ? trim((string) $row['flk']) : '';

            $candidate = Candidate::updateOrCreate(
                ['applicant_id' => $applicantId],
                [
                    'nama' => $name,
                    'source' => $this->keepExistingIfEmpty($row['source'] ?? null, $existingCandidate?->source),
                    'jk' => $this->keepExistingIfEmpty($genderNormalized, $existingCandidate?->jk), // Use normalized gender
                    'tanggal_lahir' => $birthDate,
                    'alamat_email' => $this->keepExistingIfEmpty($row['email'] ?? null, $existingCandidate?->alamat_email),
                    'jenjang_pendidikan' => $this->keepExistingIfEmpty($row['jenjang_pendidikan'] ?? null, $existingCandidate?->jenjang_pendidikan),
                    'perguruan_tinggi' => $this->keepExistingIfEmpty($row['perguruan_tinggi'] ?? null, $existingCandidate?->perguruan_tinggi),
                    'jurusan' => $this->keepExistingIfEmpty($row['jurusan'] ?? null, $existingCandidate?->jurusan),
                    'ipk' => $this->keepExistingIfEmpty($row['ipk'] ?? null, $existingCandidate?->ipk),
                    // Keep existing file path when import file does not provide CV/FLK columns.
                    'cv' => $this->keepExistingIfEmpty($cvFromImport, $existingCandidate?->cv),
                    'flk' => $this->keepExistingIfEmpty($flkFromImport, $existingCandidate?->flk),
                    'raw_department_name' => $this->keepExistingIfEmpty($rawDept, $existingCandidate?->raw_department_name),
                    'department_id' => $this->keepExistingIfEmpty($departmentId, $existingCandidate?->department_id),
                    'mpp_year' => $this->keepExistingIfEmpty($mppYear, $existingCandidate?->mpp_year),
                    'airsys_internal' => $this->keepExistingIfEmpty($airsysInternal, $existingCandidate?->airsys_internal), // Set dynamically
                    'status' => $candidateStatus,
                ]
            );

            // ================= UPSERT APPLICATION & STAGE =================
            // If candidate already exists, keep existing vacancy (do not update from import vacancy_title).
            $existingApplication = null;
            if ($existingCandidate) {
                $existingApplication = $candidate->applications()->latest('id')->first();
            }

            if ($existingApplication) {
                $existingApplication->update([
                    'overall_status' => $overallStatus,
                    'department_id' => $existingApplication->department_id ?? $departmentId,
                ]);
                $application = $existingApplication;
            } else {
                // New candidate (or candidate with no application yet): use imported vacancy.
                $application = Application::updateOrCreate(
                    [
                        'candidate_id' => $candidate->id,
                        'vacancy_id' => $vacancy ? $vacancy->id : null,
                        'mpp_year' => $mppYear,
                    ],
                    [
                        'overall_status' => $overallStatus,
                        'department_id' => $departmentId,
                    ]
                );
            }

            Log::info('CandidatesImport: Application processed', [
                'row' => $rowIndex,
                'application_id' => $application->id,
                'candidate_id' => $candidate->id,
                'vacancy_id' => $vacancy ? $vacancy->id : null,
                'mpp_year' => $mppYear,
                'overall_status' => $overallStatus,
            ]);

            // 3. Find or create the 'psikotes' stage for this application.
            // IMPORTANT: Force update to ensure status is refreshed
            $psikotesStage = $application->stages()
                ->where('stage_name', 'psikotes')
                ->first();

            if ($psikotesStage) {
                // Update existing stage
                $psikotesStage->update([
                    'scheduled_date' => $row['psikotest_date'] ?? $row['psikotes_date'] ?? $row['test_date'] ?? now(),
                    'status' => $psikotestResult,
                    'notes' => $row['psikotes_notes'] ?? null,
                    'conducted_by_user_id' => $this->userId,
                ]);
                Log::info('CandidatesImport: Updated existing psikotes stage', [
                    'application_id' => $application->id,
                    'stage_id' => $psikotesStage->id,
                    'new_status' => $psikotestResult,
                ]);
            } else {
                // Create new stage
                $application->stages()->create([
                    'stage_name' => 'psikotes',
                    'scheduled_date' => $row['psikotest_date'] ?? $row['psikotes_date'] ?? $row['test_date'] ?? now(),
                    'status' => $psikotestResult,
                    'notes' => $row['psikotes_notes'] ?? null,
                    'conducted_by_user_id' => $this->userId,
                ]);
                Log::info('CandidatesImport: Created new psikotes stage', [
                    'application_id' => $application->id,
                    'stage_status' => $psikotestResult,
                ]);
            }

            // Handle stage progression based on psycho test result
            if ($isPass) {
                // LULUS: Create HC Interview stage automatically
                $nextTestDate = now()->addDays(10)->toDateString();

                $application->stages()->updateOrCreate(
                    [
                        'stage_name' => 'hc_interview',
                    ],
                    [
                        'scheduled_date' => $nextTestDate,
                        'status' => 'PENDING',
                        'notes' => 'Otomatis dibuat karena lulus psikotes.',
                        'conducted_by_user_id' => null,
                    ]
                );

                // Create event for HC Interview
                \App\Models\Event::updateOrCreate(
                    [
                        'candidate_id' => $candidate->id,
                        'stage' => 'hc_interview'
                    ],
                    [
                        'title' => 'Interview HC: ' . $candidate->nama,
                        'description' => 'Jadwal Interview HC untuk kandidat ' . $candidate->nama,
                        'date' => $nextTestDate,
                        'time' => '09:00',
                        'status' => 'active',
                        'created_by' => $this->userId,
                    ]
                );

                Log::info('CandidatesImport: Candidate PASSED psikotes - HC Interview stage created', [
                    'candidate_id' => $candidate->id,
                    'applicant_id' => $applicantId,
                ]);

            } elseif ($isFail) {
                // TIDAK LULUS: Mark all stages as rejected
                $application->stages()->update(['status' => 'TIDAK LULUS']);

                Log::info('CandidatesImport: Candidate FAILED psikotes - Application rejected', [
                    'candidate_id' => $candidate->id,
                    'applicant_id' => $applicantId,
                ]);

            } elseif ($isRetest) {
                // RETEST: Keep status as PROSES, no next stage created yet
                Log::info('CandidatesImport: Candidate requires RETEST', [
                    'candidate_id' => $candidate->id,
                    'applicant_id' => $applicantId,
                ]);
            }

            $this->processed++;
        }

        Log::info('CandidatesImport: completed', [
            'processed' => $this->processed,
            'skipped' => $this->skipped,
        ]);
    }

    public function getProcessedCount(): int
    {
        return $this->processed;
    }

    private function keepExistingIfEmpty($importedValue, $existingValue)
    {
        if (is_null($importedValue)) {
            return $existingValue;
        }

        if (is_string($importedValue) && trim($importedValue) === '') {
            return $existingValue;
        }

        return $importedValue;
    }

    public function getSkippedCount(): int
    {
        return $this->skipped;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
