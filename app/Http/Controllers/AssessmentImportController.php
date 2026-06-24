<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateAssessmentResult;
use App\Models\ImportHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AssessmentImportController extends Controller
{
  private function disabledMessage(): string
  {
    return 'Halaman Import Assessment sudah dinonaktifkan. Gunakan Import Kandidat (Unified).';
  }

  public function index()
  {
    return redirect()->route('import.index')->with('success', $this->disabledMessage());

    $import_history = ImportHistory::where('user_id', auth()->id())
      ->where('filename', 'like', '[ASSESSMENT]%')
      ->latest()
      ->take(10)
      ->get();

    return view('import.assessment', [
      'import_history' => $import_history,
    ]);
  }

  public function preview(Request $request)
  {
    return response()->json([
      'success' => false,
      'message' => $this->disabledMessage(),
    ], 410);

    $request->validate([
      'file' => 'required|mimes:xlsx,xls,csv|max:10240',
    ]);

    $file = $request->file('file');
    $fileId = 'assessment_preview_' . uniqid();
    $path = $file->storeAs('temp_imports', $fileId . '.' . $file->getClientOriginalExtension());
    $fullPath = Storage::path($path);

    try {
      $parsed = $this->parseAssessmentFile($fullPath);
      $rows = $parsed['rows'];

      if (count($rows) === 0) {
        Storage::delete($path);
        return response()->json([
          'success' => false,
          'message' => 'File tidak memiliki data assessment untuk diimpor.',
        ]);
      }

      $errors = [];
      $previewData = [];
      $previewRowCount = 20;

      foreach (array_slice($rows, 0, $previewRowCount) as $row) {
        $rowErrors = $this->validateAssessmentRow($row);
        if (!empty($rowErrors)) {
          $errors = array_merge($errors, $rowErrors);
          continue;
        }

        if (count($previewData) < 5) {
          $previewData[] = $this->stripInternalFields($row);
        }
      }

      Cache::put($fileId, [
        'path' => $path,
        'filename' => $file->getClientOriginalName(),
        'row_count' => count($rows),
      ], now()->addHour());

      $message = "Validasi awal pada {$previewRowCount} baris pertama selesai. " . count($rows) . ' total baris akan diproses.';
      if (!empty($errors)) {
        $message = 'Validasi awal selesai. Ditemukan beberapa masalah, baris tersebut akan dilewati saat import final. ' . count($rows) . ' total baris akan diproses.';
      }

      return response()->json([
        'success' => true,
        'message' => $message,
        'file_id' => $fileId,
        'total_rows' => count($rows),
        'preview' => $previewData,
        'headers' => $parsed['headers'],
        'errors' => $errors,
      ]);
    } catch (\Throwable $e) {
      if (Storage::exists($path)) {
        Storage::delete($path);
      }

      Log::error('Assessment import preview error: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses file assessment: ' . $e->getMessage(),
      ], 500);
    }
  }

  public function confirmImport(Request $request)
  {
    return response()->json([
      'success' => false,
      'message' => $this->disabledMessage(),
    ], 410);

    $request->validate(['file_id' => 'required|string']);

    $fileId = $request->input('file_id');
    $cachedData = Cache::get($fileId);

    if (!$cachedData || !Storage::exists($cachedData['path'])) {
      return response()->json([
        'success' => false,
        'message' => 'File tidak ditemukan atau sesi import assessment telah kedaluwarsa.',
      ], 404);
    }

    $path = $cachedData['path'];
    $fullPath = Storage::path($path);
    $filename = $cachedData['filename'];

    $importHistory = ImportHistory::create([
      'user_id' => auth()->id(),
      'filename' => '[ASSESSMENT] ' . $filename,
      'total_rows' => (int) $cachedData['row_count'],
      'success_rows' => 0,
      'failed_rows' => 0,
      'status' => 'processing',
      'error_message' => null,
      'error_details' => null,
    ]);

    try {
      $parsed = $this->parseAssessmentFile($fullPath);
      $processed = 0;
      $skipped = 0;
      $errors = [];

      foreach ($parsed['rows'] as $row) {
        $rowErrors = $this->validateAssessmentRow($row);
        if (!empty($rowErrors)) {
          $skipped++;
          foreach ($rowErrors as $errorMessage) {
            $errors[] = [
              'row' => $row['__row_number'] ?? null,
              'applicant_id' => $row['applicant_id'] ?? null,
              'nama' => $row['applicant_name'] ?? null,
              'error' => $errorMessage,
            ];
          }
          continue;
        }

        $testDate = $this->parseDate($row['test_date'] ?? null);
        if (!$testDate) {
          $skipped++;
          $errors[] = [
            'row' => $row['__row_number'] ?? null,
            'applicant_id' => $row['applicant_id'] ?? null,
            'nama' => $row['applicant_name'] ?? null,
            'error' => 'Format test_date tidak valid.',
          ];
          continue;
        }

        $payload = $this->mapRowToAssessmentPayload($row, $filename, $testDate);

        CandidateAssessmentResult::updateOrCreate(
          [
            'applicant_id' => $payload['applicant_id'],
            'test_date' => $payload['test_date'],
            'provider' => $payload['provider'],
            'assessment_type' => $payload['assessment_type'],
          ],
          $payload
        );

        $processed++;
      }

      $importHistory->update([
        'success_rows' => $processed,
        'failed_rows' => $skipped,
        'status' => 'completed',
        'error_message' => null,
        'error_details' => count($errors) > 0 ? $errors : null,
      ]);

      Cache::forget($fileId);
      Storage::delete($path);

      return response()->json([
        'success' => true,
        'message' => 'Import assessment selesai diproses.',
        'processed' => $processed,
        'skipped' => $skipped,
      ]);
    } catch (\Throwable $e) {
      $importHistory->update([
        'status' => 'failed',
        'error_message' => $e->getMessage(),
      ]);

      Log::error('Assessment import failed: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'history_id' => $importHistory->id,
      ]);

      Cache::forget($fileId);
      if (Storage::exists($path)) {
        Storage::delete($path);
      }

      return response()->json([
        'success' => false,
        'message' => 'Gagal memproses import assessment: ' . $e->getMessage(),
      ], 500);
    }
  }

  public function cancelImport(Request $request)
  {
    return response()->json([
      'success' => false,
      'message' => $this->disabledMessage(),
    ], 410);

    $request->validate(['file_id' => 'required|string']);

    $fileId = $request->input('file_id');
    $cachedData = Cache::get($fileId);

    if ($cachedData && isset($cachedData['path'])) {
      Storage::delete($cachedData['path']);
      Cache::forget($fileId);

      return response()->json([
        'success' => true,
        'message' => 'Import assessment dibatalkan dan file sementara dihapus.',
      ]);
    }

    return response()->json([
      'success' => false,
      'message' => 'Tidak ada proses import assessment untuk dibatalkan.',
    ], 404);
  }

  private function parseAssessmentFile(string $fullPath): array
  {
    $data = Excel::toArray(new \stdClass(), $fullPath);
    $sheet = $data[0] ?? [];

    if (count($sheet) < 4) {
      throw new \RuntimeException('Format file assessment tidak valid. Minimal harus memiliki 3 baris header dan 1 baris data.');
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

      // Keep final_result_hasil_cut_off_score, drop other cut off/hasil columns.
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

  private function validateAssessmentRow(array $row): array
  {
    $errors = [];
    $rowNumber = $row['__row_number'] ?? '-';

    $applicantId = trim((string) ($row['applicant_id'] ?? ''));
    if ($applicantId === '') {
      $errors[] = "Baris {$rowNumber}: Kolom applicant_id wajib diisi.";
      return $errors;
    }

    $candidate = Candidate::where('applicant_id', $applicantId)->first();
    if (!$candidate) {
      $errors[] = "Baris {$rowNumber}: applicant_id '{$applicantId}' tidak ditemukan pada data kandidat.";
    }

    if (empty($row['test_date']) || !$this->parseDate($row['test_date'])) {
      $errors[] = "Baris {$rowNumber}: Kolom test_date wajib diisi dengan format tanggal yang valid.";
    }

    $hasAnyScore = false;
    foreach ($this->getScoreColumnMap() as $sourceColumn => $targetColumn) {
      if (!array_key_exists($sourceColumn, $row)) {
        continue;
      }

      if ($row[$sourceColumn] === null || $row[$sourceColumn] === '') {
        continue;
      }

      $hasAnyScore = true;
      if ($this->parseScore($row[$sourceColumn]) === null) {
        $errors[] = "Baris {$rowNumber}: Nilai '{$sourceColumn}' bukan angka yang valid.";
      }
    }

    if (!$hasAnyScore) {
      $errors[] = "Baris {$rowNumber}: Tidak ada nilai score yang dapat diimpor.";
    }

    return $errors;
  }

  private function mapRowToAssessmentPayload(array $row, string $filename, string $testDate): array
  {
    $vacancyTitle = $this->normalizeCell($row['vacancy_title'] ?? null);
    if (is_string($vacancyTitle)) {
      $vacancyTitle = trim((string) preg_replace('/\s*-\s*PMP\b/i', '', $vacancyTitle));
    }

    $payload = [
      'applicant_id' => trim((string) ($row['applicant_id'] ?? '')),
      'provider' => 'astra',
      'assessment_type' => 'psychotest',
      'test_date' => $testDate,
      'norm_grade' => $this->normalizeCell($row['norm_grade'] ?? null),
      'source_file_name' => $filename,
      'imported_at' => now(),
      'raw_identity_snapshot' => [
        'no' => $this->normalizeCell($row['no'] ?? null),
        'applicant_name' => $this->normalizeCell($row['applicant_name'] ?? null),
        'gender' => $this->normalizeCell($row['gender'] ?? null),
        'date_of_birth' => $this->normalizeCell($row['date_of_birth'] ?? null),
        'email' => $this->normalizeCell($row['email'] ?? null),
        'university' => $this->normalizeCell($row['university'] ?? null),
        'major' => $this->normalizeCell($row['major'] ?? null),
        'company' => $this->normalizeCell($row['company'] ?? null),
        'vacancy_title' => $vacancyTitle,
        'cut_off_name' => $this->normalizeCell($row['cut_off_name'] ?? null),
        'final_result_hasil_cut_off_score' => $this->normalizeCell($row['final_result_hasil_cut_off_score'] ?? null),
      ],
    ];

    foreach ($this->getScoreColumnMap() as $sourceColumn => $targetColumn) {
      if (!array_key_exists($sourceColumn, $row)) {
        continue;
      }

      $payload[$targetColumn] = $this->parseScore($row[$sourceColumn]);
    }

    return $payload;
  }

  private function getScoreColumnMap(): array
  {
    return [
      'astra_spark_action_norm_score' => 'action_score',
      'astra_spark_achievement_orientation_norm_score' => 'achievement_orientation_score',
      'astra_spark_assertiveness_norm_score' => 'assertiveness_score',
      'astra_spark_conscientiousness_norm_score' => 'conscientiousness_score',
      'astra_spark_extraversion_norm_score' => 'extraversion_score',
      'astra_spark_flexibility_norm_score' => 'flexibility_score',
      'astra_spark_idea_norm_score' => 'idea_score',
      'astra_spark_impact_and_influence_norm_score' => 'impact_and_influence_score',
      'astra_spark_persistence_norm_score' => 'persistence_score',
      'astra_spark_personal_motivation_norm_score' => 'personal_motivation_score',
      'astra_spark_teamwork_norm_score' => 'teamwork_score',
      'astra_spark_working_autonomously_norm_score' => 'working_autonomously_score',
      'astra_ignite_reading_comprehension_norm_score' => 'reading_comprehension_score',
      'astra_ignite_quantitative_reasoning_norm_score' => 'quantitative_reasoning_score',
      'astra_ignite_inductive_reasoning_norm_score' => 'inductive_reasoning_score',
      'astra_ignite_working_memory_norm_score' => 'working_memory_score',
      'astra_ignite_perceptual_speed_norm_score' => 'perceptual_speed_score',
      'astra_ignite_deductive_reasoning_norm_score' => 'deductive_reasoning_score',
      'astra_ignite_visualization_norm_score' => 'visualization_score',
      'competency_aj_norm_score' => 'competency_aj_score',
      'competency_dc_norm_score' => 'competency_dc_score',
      'competency_is_norm_score' => 'competency_is_score',
      'competency_pda_norm_score' => 'competency_pda_score',
      'competency_tw_norm_score' => 'competency_tw_score',
      'astra_spark_social_desirability_norm_score' => 'social_desirability_score',
      'astra_spark_infrequency_norm_score' => 'infrequency_score',
      'astra_spark_inconsistency_norm_score' => 'inconsistency_score',

      // Support pre-transformed files that already use *_score
      'action_score' => 'action_score',
      'achievement_orientation_score' => 'achievement_orientation_score',
      'assertiveness_score' => 'assertiveness_score',
      'conscientiousness_score' => 'conscientiousness_score',
      'extraversion_score' => 'extraversion_score',
      'flexibility_score' => 'flexibility_score',
      'idea_score' => 'idea_score',
      'impact_and_influence_score' => 'impact_and_influence_score',
      'persistence_score' => 'persistence_score',
      'personal_motivation_score' => 'personal_motivation_score',
      'teamwork_score' => 'teamwork_score',
      'working_autonomously_score' => 'working_autonomously_score',
      'reading_comprehension_score' => 'reading_comprehension_score',
      'quantitative_reasoning_score' => 'quantitative_reasoning_score',
      'inductive_reasoning_score' => 'inductive_reasoning_score',
      'working_memory_score' => 'working_memory_score',
      'perceptual_speed_score' => 'perceptual_speed_score',
      'deductive_reasoning_score' => 'deductive_reasoning_score',
      'visualization_score' => 'visualization_score',
      'competency_aj_score' => 'competency_aj_score',
      'competency_dc_score' => 'competency_dc_score',
      'competency_is_score' => 'competency_is_score',
      'competency_pda_score' => 'competency_pda_score',
      'competency_tw_score' => 'competency_tw_score',
      'social_desirability_score' => 'social_desirability_score',
      'infrequency_score' => 'infrequency_score',
      'inconsistency_score' => 'inconsistency_score',
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

  private function stripInternalFields(array $row): array
  {
    unset($row['__row_number']);
    return $row;
  }

  private function parseDate($value): ?string
  {
    if ($value === null || $value === '') {
      return null;
    }

    try {
      if (is_numeric($value)) {
        $dateObj = Date::excelToDateTimeObject($value);
        return Carbon::instance($dateObj)->format('Y-m-d');
      }

      return Carbon::parse((string) $value)->format('Y-m-d');
    } catch (\Throwable $e) {
      return null;
    }
  }

  private function parseScore($value): ?float
  {
    if ($value === null || $value === '') {
      return null;
    }

    if (is_numeric($value)) {
      return (float) $value;
    }

    $normalized = str_replace(' ', '', (string) $value);
    if (str_contains($normalized, ',') && !str_contains($normalized, '.')) {
      $normalized = str_replace(',', '.', $normalized);
    }

    return is_numeric($normalized) ? (float) $normalized : null;
  }
}
