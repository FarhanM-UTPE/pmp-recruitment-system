<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Exports\CandidateTemplateExport;
use App\Imports\CandidatesImport;
use App\Jobs\ProcessCandidateImport;
use App\Services\CandidateService;
use App\Models\Candidate;
use App\Models\Vacancy;
use App\Models\ImportHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ImportController extends Controller
{
    protected $candidateService;

    public function __construct(CandidateService $candidateService)
    {
        $this->candidateService = $candidateService;
    }

    public function index()
    {
        $import_history = ImportHistory::where('user_id', auth()->id())
            ->latest()
            ->take(10)
            ->get();

        return view('import.index', [
            'import_history' => $import_history,
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        $file = $request->file('file');

        // Store the file temporarily
        $fileId = 'preview_' . uniqid();
        $path = $file->storeAs('temp_imports', $fileId . '.' . $file->getClientOriginalExtension());
        $fullPath = Storage::path($path);

        try {
            $importType = 'candidate';
            $previewRows = [];
            $mappedHeaders = [];
            $totalRows = 0;
            $errors = [];
            $previewData = [];
            $previewRowCount = 20;

            // Try to parse as assessment file first (3-tier header format).
            $assessmentParsed = $this->parseAssessmentFile($fullPath);
            $isAssessmentFormat = count($assessmentParsed['rows']) > 0
                && in_array('applicant_id', $assessmentParsed['headers'], true)
                && in_array('vacancy_title', $assessmentParsed['headers'], true);

            if ($isAssessmentFormat) {
                $importType = 'assessment_unified';
                $totalRows = count($assessmentParsed['rows']);
                $mappedHeaders = [
                    'tahun_mpp',
                    'id_pelamar',
                    'nama',
                    'alamat_email',
                    'jenis_kelamin',
                    'tanggal_lahir',
                    'perguruan_tinggi',
                    'jurusan',
                    'source',
                    'jabatan_dilamar',
                    'psikotest_result',
                    'test_date',
                    'psikotes_notes',
                ];

                $previewRows = array_map(function (array $assessmentRow) {
                    return $this->mapAssessmentToCandidateRow($assessmentRow);
                }, $assessmentParsed['rows']);

                foreach (array_slice($previewRows, 0, $previewRowCount, true) as $index => $rowData) {
                    if (empty(array_filter($rowData))) {
                        continue;
                    }

                    $rowIndex = $index + 2;
                    $validationErrors = $this->validateRow($rowData, $rowIndex);
                    if (!empty($validationErrors)) {
                        $errors = array_merge($errors, $validationErrors);
                    }

                    if (count($previewData) < 5 && empty($validationErrors)) {
                        $previewData[] = $rowData;
                    }
                }
            } else {
                // Legacy candidate-template preview flow intentionally disabled.
                // Kept here for reference per request.
                // $data = Excel::toArray(new \stdClass(), $fullPath);
                // $allRows = $data[0] ?? [];
                //
                // if (count($allRows) <= 1) {
                //     Storage::delete($path);
                //     return response()->json([
                //         'success' => false,
                //         'message' => 'File tidak memiliki data untuk diimpor.'
                //     ]);
                // }
                //
                // $headers = array_shift($allRows);
                // $mappedHeaders = $this->mapHeaders($headers);
                // $totalRows = count($allRows);
                //
                // foreach (array_slice($allRows, 0, $previewRowCount) as $index => $row) {
                //     if (empty(array_filter($row))) {
                //         continue;
                //     }
                //
                //     $rowData = array_combine($mappedHeaders, array_pad(array_slice($row, 0, count($mappedHeaders)), count($mappedHeaders), null));
                //     $rowIndex = $index + 2;
                //
                //     $validationErrors = $this->validateRow($rowData, $rowIndex);
                //     if (!empty($validationErrors)) {
                //         $errors = array_merge($errors, $validationErrors);
                //     }
                //
                //     if (count($previewData) < 5 && empty($validationErrors)) {
                //         $previewData[] = $rowData;
                //     }
                // }

                Storage::delete($path);

                return response()->json([
                    'success' => false,
                    'message' => 'Template lama tidak didukung. Gunakan file assessment terbaru yang memiliki nilai assessment.',
                ], 422);
            }

            Cache::put($fileId, [
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'row_count' => $totalRows,
                'import_type' => $importType,
            ], now()->addHour());

            $message = "Validasi awal pada {$previewRowCount} baris pertama berhasil. {$totalRows} total baris akan diimpor.";
            if (!empty($errors)) {
                $message = "Validasi awal selesai. Ditemukan beberapa masalah, baris tersebut akan dilewati saat import final. {$totalRows} total baris akan diimpor.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'file_id' => $fileId,
                'total_rows' => $totalRows,
                'preview' => $previewData,
                'headers' => $mappedHeaders,
                'errors' => $errors,
                'import_type' => $importType,
            ]);

        } catch (\Throwable $e) {
            if (isset($path) && Storage::exists($path)) {
                Storage::delete($path);
            }
            Log::error('Error during import preview generation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function confirmImport(Request $request)
    {
        $request->validate([
            'file_id' => 'required|string',
            'source' => 'required|in:Airsys,Campus Hiring,Others',
        ]);
        $fileId = $request->input('file_id');
        $selectedSource = $request->input('source');

        try {
            $cachedData = Cache::get($fileId);

            if (!$cachedData || !Storage::exists($cachedData['path'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan atau sesi import telah kedaluwarsa.'
                ], 404);
            }

            $path = $cachedData['path'];
            $filename = $cachedData['filename'];
            $totalRows = $cachedData['row_count'];

            $importType = $cachedData['import_type'] ?? 'candidate';

            // Create import history record
            $importHistory = ImportHistory::create([
                'user_id' => auth()->id(),
                'filename' => $importType === 'assessment_unified' ? '[UNIFIED] ' . $filename : $filename,
                'total_rows' => $totalRows,
                'success_rows' => 0,
                'failed_rows' => 0,
                'status' => 'processing',
                'year' => null, // Year is now per-row, so this is null
            ]);

            // Dispatch the job asynchronously
            ProcessCandidateImport::dispatch($path, auth()->id(), $importHistory->id, $importType, $filename, $selectedSource)->delay(now()->addSeconds(2));

            // Forget the cache key, the job will handle file deletion
            Cache::forget($fileId);

            Log::info('Import job dispatched successfully', [
                'user_id' => auth()->id(),
                'file_id' => $fileId,
                'history_id' => $importHistory->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proses import telah dimulai. Data akan diproses di latar belakang.'
            ]);
        } catch (\Throwable $e) {
            Log::error('Error during import confirmation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file_id' => $fileId,
            ]);

            // Attempt to clean up cache if it still exists
            if (isset($fileId)) {
                Cache::forget($fileId);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai proses import: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancelImport(Request $request)
    {
        $request->validate(['file_id' => 'required|string']);
        $fileId = $request->input('file_id');
        $cachedData = Cache::get($fileId);

        if ($cachedData && isset($cachedData['path'])) {
            Storage::delete($cachedData['path']);
            Cache::forget($fileId);
            return response()->json([
                'success' => true,
                'message' => 'Import dibatalkan dan file sementara telah dihapus.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Tidak ada proses import untuk dibatalkan.'
        ], 404);
    }

    private function validateRow(array $row, int $rowIndex): array
    {
        $errors = [];

        $mppYear = trim($row['tahun_mpp'] ?? '');

        // 1. Required fields check
        $requiredFields = ['nama', 'alamat_email', 'jabatan_dilamar'];
        foreach ($requiredFields as $field) {
            if (empty($row[$field]) || trim($row[$field]) === '') {
                $errors[] = "Baris {$rowIndex}: Kolom '{$field}' tidak boleh kosong.";
            }
        }

        // If a vacancy is present, a year must also be present
        if (!empty($row['jabatan_dilamar']) && empty($mppYear)) {
            $errors[] = "Baris {$rowIndex}: Kolom 'Tahun MPP' wajib diisi jika 'Jabatan Dilamar' diisi.";
        }

        // 2. Email format check
        if (!empty($row['alamat_email']) && !filter_var($row['alamat_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Baris {$rowIndex}: Format email '{$row['alamat_email']}' tidak valid.";
        }

        // 3. Date format check
        $birthDateValue = $row['tanggal_lahir'] ?? $row['tanggal lahir'] ?? null;
        if (!empty($birthDateValue) && !$this->parseDate($birthDateValue)) {
            $errors[] = "Baris {$rowIndex}: Format tanggal lahir tidak valid.";
        }

        // 4. Vacancy check - Must exist in an approved MPP for the selected year
        if (!empty($row['jabatan_dilamar']) && !empty($mppYear)) {
            if (!is_numeric($mppYear) || strlen($mppYear) != 4) {
                $errors[] = "Baris {$rowIndex}: Format 'Tahun MPP' ('{$mppYear}') tidak valid. Gunakan 4 digit angka (contoh: 2024).";
            } else {
                $vacancyId = $this->getVacancyId($row['jabatan_dilamar'], (int) $mppYear);
                if (!$vacancyId) {
                    $errors[] = "Baris {$rowIndex}: Jabatan/Posisi '{$row['jabatan_dilamar']}' tidak ditemukan di MPP yang disetujui untuk tahun {$mppYear}.";
                }
            }
        }

        // 5. Duplicate check (only if all required fields are valid and no vacancy error)
        if (empty($errors)) {
            $applicantId = trim((string) ($row['id_pelamar'] ?? $row['applicant_id'] ?? ''));
            $email = trim((string) ($row['alamat_email'] ?? ''));

            // If candidate already exists, this row should be treated as update, not duplicate.
            $existingCandidate = null;
            if ($applicantId !== '') {
                $existingCandidate = Candidate::where('applicant_id', $applicantId)->first();
            }
            if (!$existingCandidate && $email !== '') {
                $existingCandidate = Candidate::where('alamat_email', $email)->first();
            }

            if ($existingCandidate) {
                return $errors;
            }

            $birthDateValue = $row['tanggal_lahir'] ?? $row['tanggal lahir'] ?? null;
            $duplicateCheckData = [
                'email' => $row['alamat_email'],
                'nama' => $row['nama'],
                'jk' => $row['jenis_kelamin'] ?? null,
                'tanggal_lahir' => $this->parseDate($birthDateValue),
                'applicant_id' => $row['id_pelamar'] ?? null,
            ];

            if ($this->candidateService->findDuplicateCandidate($duplicateCheckData)) {
                $errors[] = "Baris {$rowIndex}: Kandidat '{$row['nama']}' dengan email '{$row['alamat_email']}' sudah terdaftar (duplikat).";
            }
        }

        return $errors;
    }

    private function mapHeaders(array $headers): array
    {
        $map = [
            'nama lengkap' => 'nama',
            'nama' => 'nama',
            'applicant name' => 'nama',
            'email' => 'alamat_email',
            'alamat email' => 'alamat_email',
            'email address' => 'alamat_email',
            'posisi yang dilamar' => 'jabatan_dilamar',
            'jabatan yang dilamar' => 'jabatan_dilamar',
            'jabatan dilamar' => 'jabatan_dilamar',
            'vacancy' => 'jabatan_dilamar',
            'vacancy title' => 'jabatan_dilamar',
            'position' => 'jabatan_dilamar',
            'tahun mpp' => 'tahun_mpp',
            'mpp year' => 'tahun_mpp',
            'jenis kelamin' => 'jenis_kelamin',
            'JK' => 'jenis_kelamin',
            'gender' => 'jenis_kelamin',
            'tanggal lahir' => 'tanggal_lahir',
            'date of birth' => 'tanggal_lahir',
            'dob' => 'tanggal_lahir',
            'sumber lamaran' => 'sumber_lamaran',
            'source' => 'sumber_lamaran',
            'id pelamar' => 'id_pelamar',
            'applicant id' => 'id_pelamar',
            'universitas' => 'perguruan_tinggi',
            'perguruan tinggi' => 'perguruan_tinggi',
            'university' => 'perguruan_tinggi',
            'jurusan' => 'jurusan',
            'major' => 'jurusan',
            'ipk' => 'ipk',
            'gpa' => 'ipk',
            'jenjang' => 'jenjang_pendidikan',
            'jenjang pendidikan' => 'jenjang_pendidikan',
            'education' => 'jenjang_pendidikan',
            'phone' => 'phone',
            'telepon' => 'phone',
            'no hp' => 'phone',
            'alamat' => 'alamat',
            'address' => 'alamat',
            'department' => 'department',
            'departemen' => 'department',
        ];

        return array_map(function ($header) use ($map) {
            $normalizedHeader = strtolower(trim($header));
            return $map[$normalizedHeader] ?? $normalizedHeader;
        }, $headers);
    }

    private function parseDate($dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Use official PhpSpreadsheet Date library for Excel serial conversion
            if (is_numeric($dateString)) {
                // Excel serial number
                $dateObj = Date::excelToDateTimeObject($dateString);
                return \Carbon\Carbon::instance($dateObj)->format('Y-m-d');
            } else {
                // Text format - use Carbon parse
                return \Carbon\Carbon::parse($dateString)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse date', ['value' => $dateString, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function getVacancyId(string $vacancyName, int $year): ?int
    {
        Log::debug('getVacancyId: Attempting to find vacancy', [
            'vacancy_name' => $vacancyName,
            'year' => $year
        ]);

        $normalizedVacancyName = strtolower(trim($vacancyName));

        // Modified query to accept vacancy with proposal_status 'approved'
        // even if MPP submission status is still 'submitted'
        $vacancy = Vacancy::whereRaw('LOWER(name) = ?', [$normalizedVacancyName])
            ->whereHas('mppSubmissions', function ($q) use ($year) {
                $q->where('year', $year)
                    ->whereIn('submission_type', ['planned', 'unplanned'])
                    ->whereIn('status', [
                        \App\Models\MPPSubmission::STATUS_APPROVED,
                        \App\Models\MPPSubmission::STATUS_SUBMITTED
                    ])
                    ->where('mpp_submission_vacancy.proposal_status', 'approved');
            });

        Log::debug('getVacancyId: Vacancy query build', ['sql' => $vacancy->toSql(), 'bindings' => $vacancy->getBindings()]);

        $foundVacancy = $vacancy->first();

        if ($foundVacancy) {
            Log::debug('getVacancyId: Vacancy found', ['id' => $foundVacancy->id, 'name' => $foundVacancy->name]);
            return $foundVacancy->id;
        }

        Log::debug('getVacancyId: Vacancy NOT found for given criteria', [
            'vacancy_name' => $vacancyName,
            'year' => $year
        ]);
        return null;
    }

    private function mapAssessmentToCandidateRow(array $row): array
    {
        $vacancy = $this->normalizeVacancyTitle((string) ($row['vacancy_title'] ?? ''));

        return [
            'tahun_mpp' => (string) now()->year,
            'id_pelamar' => $row['applicant_id'] ?? null,
            'nama' => $row['applicant_name'] ?? null,
            'alamat_email' => $row['email'] ?? null,
            'jenis_kelamin' => $row['gender'] ?? null,
            'tanggal_lahir' => $row['date_of_birth'] ?? null,
            'perguruan_tinggi' => $row['university'] ?? null,
            'jurusan' => $row['major'] ?? null,
            'source' => 'Airsys',
            'jabatan_dilamar' => trim((string) $vacancy),
            'psikotest_result' => $row['final_result_hasil_cut_off_score'] ?? null,
            'test_date' => $row['test_date'] ?? null,
            'psikotes_notes' => '-',
        ];
    }

    private function normalizeVacancyTitle(string $vacancyTitle): string
    {
        $vacancy = trim($vacancyTitle);

        // Remove source prefix such as "Campus Hiring SBY - " when present.
        $vacancy = preg_replace('/^\s*Campus\s+Hiring[^-]*-\s*/i', '', $vacancy);

        // Remove environment suffix such as " - PMP".
        $vacancy = preg_replace('/\s*-\s*PMP\b/i', '', $vacancy);

        return trim((string) $vacancy);
    }

    private function parseAssessmentFile(string $fullPath): array
    {
        $data = Excel::toArray(new \stdClass(), $fullPath);
        $sheet = $data[0] ?? [];

        if (count($sheet) < 4) {
            return ['headers' => [], 'rows' => []];
        }

        $maxCols = 0;
        foreach ($sheet as $row) {
            $maxCols = max($maxCols, count($row));
        }

        $headerRows = [
            $this->fillForwardRow($sheet[0] ?? [], $maxCols),
            $this->fillForwardRow($sheet[1] ?? [], $maxCols),
            $this->fillForwardRow($sheet[2] ?? [], $maxCols),
        ];

        $headers = [];
        $keptColumns = [];
        $headerCounts = [];

        for ($col = 0; $col < $maxCols; $col++) {
            $lvl0 = $this->normalizeHeaderPart($headerRows[0][$col] ?? null);
            $lvl1 = $this->normalizeHeaderPart($headerRows[1][$col] ?? null);
            $lvl2 = $this->normalizeHeaderPart($headerRows[2][$col] ?? null);

            $combined = trim(implode(' ', array_filter([$lvl0, $lvl1, $lvl2])));
            if ($combined === '') {
                $combined = 'Unnamed ' . $col;
            }

            $snake = $this->toSnakeCase($combined);
            if ($snake === '') {
                $snake = 'unnamed_' . $col;
            }

            if (
                $snake !== 'final_result_hasil_cut_off_score'
                && (str_contains($snake, 'cut_off_score') || str_contains($snake, 'hasil'))
            ) {
                continue;
            }

            if (isset($headerCounts[$snake])) {
                $headerCounts[$snake]++;
                $snake = $snake . '_' . $headerCounts[$snake];
            } else {
                $headerCounts[$snake] = 1;
            }

            $headers[] = $snake;
            $keptColumns[$col] = $snake;
        }

        $rows = [];
        for ($rowIndex = 3; $rowIndex < count($sheet); $rowIndex++) {
            $row = $sheet[$rowIndex] ?? [];
            $assoc = [];

            foreach ($keptColumns as $colIndex => $headerName) {
                $assoc[$headerName] = $this->normalizeCell($row[$colIndex] ?? null);
            }

            if ($this->isEmptyRow($assoc)) {
                continue;
            }

            $assoc['__row_number'] = $rowIndex + 1;
            $rows[] = $assoc;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function fillForwardRow(array $row, int $maxCols): array
    {
        $filled = [];
        $lastValue = null;

        for ($i = 0; $i < $maxCols; $i++) {
            $value = $row[$i] ?? null;
            $value = $this->normalizeCell($value);

            if ($value !== null && $value !== '') {
                $lastValue = $value;
            }

            $filled[$i] = $value === null || $value === '' ? $lastValue : $value;
        }

        return $filled;
    }

    private function normalizeHeaderPart($value): string
    {
        if ($value === null) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '' || strtolower($text) === 'nan') {
            return '';
        }

        return $text;
    }

    private function toSnakeCase(string $text): string
    {
        $cleanText = preg_replace('/[^a-zA-Z0-9]+/', '_', $text);
        return strtolower(trim((string) $cleanText, '_'));
    }

    private function normalizeCell($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key === '__row_number') {
                continue;
            }

            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    public function downloadTemplate($type = 'candidates')
    {
        $fileName = 'template_import_candidates_' . date('Ymd') . '.xlsx';
        return Excel::download(new CandidateTemplateExport(), $fileName);
    }
}