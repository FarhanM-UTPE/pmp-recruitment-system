<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Exports\CandidateTemplateExport;
use App\Imports\CandidatesImport;
use App\Jobs\ProcessCandidateImport;
use App\Services\CandidateService;
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
            $data = Excel::toArray(new \stdClass(), $fullPath);
            $allRows = $data[0] ?? [];

            if (count($allRows) <= 1) {
                Storage::delete($path);
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak memiliki data untuk diimpor.'
                ]);
            }

            $headers = array_shift($allRows); // Get and remove header row
            $mappedHeaders = $this->mapHeaders($headers);
            $totalRows = count($allRows);

            $errors = [];
            $previewData = [];
            $previewRowCount = 20; // We only validate the first N rows for preview
            $rowsToValidate = array_slice($allRows, 0, $previewRowCount);

            foreach ($rowsToValidate as $index => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $rowData = array_combine($mappedHeaders, array_pad(array_slice($row, 0, count($mappedHeaders)), count($mappedHeaders), null));
                $rowIndex = $index + 2; // Excel rows are 1-based, and we shifted headers

                $validationErrors = $this->validateRow($rowData, $rowIndex); // Pass year to validateRow
                if (!empty($validationErrors)) {
                    $errors = array_merge($errors, $validationErrors);
                }

                // Still collect up to 5 valid rows for the visual preview
                if (count($previewData) < 5 && empty($validationErrors)) {
                    $previewData[] = $rowData;
                }
            }

            // If validation of the first rows passes, cache the file path for final import
            Cache::put($fileId, [
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'row_count' => $totalRows,
            ], now()->addHour());

            // Even if there are errors, we return success: true, but provide the errors for display
            $message = "Validasi awal pada {$previewRowCount} baris pertama berhasil. {$totalRows} total baris akan diimpor.";
            if (!empty($errors)) {
                $message = "Validasi awal selesai. Ditemukan beberapa masalah, baris tersebut akan dilewati saat import final. {$totalRows} total baris akan diimpor.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'file_id' => $fileId,
                'total_rows' => $totalRows, // Show total rows to the user
                'preview' => $previewData,
                'headers' => $mappedHeaders,
                'errors' => $errors, // Always return errors, even if empty
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
        $request->validate(['file_id' => 'required|string']);
        $fileId = $request->input('file_id');

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

            // Create import history record
            $importHistory = ImportHistory::create([
                'user_id' => auth()->id(),
                'filename' => $filename,
                'total_rows' => $totalRows,
                'success_rows' => 0,
                'failed_rows' => 0,
                'status' => 'processing',
                'year' => null, // Year is now per-row, so this is null
            ]);

            // Dispatch the job asynchronously
            ProcessCandidateImport::dispatch($path, auth()->id(), $importHistory->id)->delay(now()->addSeconds(2));

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

    public function downloadTemplate($type = 'candidates')
    {
        $fileName = 'template_import_candidates_' . date('Ymd') . '.xlsx';
        return Excel::download(new CandidateTemplateExport(), $fileName);
    }
}