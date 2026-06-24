<?php

namespace App\Jobs;

use App\Imports\CandidatesImport;
use App\Models\Candidate;
use App\Models\CandidateAssessmentResult;
use App\Models\ImportHistory;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class ProcessCandidateImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // 20 menit
    public int $tries = 3;

    protected string $path;
    protected int $authUserId;
    protected int $importHistoryId;
    protected string $importType;
    protected ?string $originalFilename;

    public function __construct(string $path, int $authUserId, int $importHistoryId, string $importType = 'candidate', ?string $originalFilename = null)
    {
        $this->path = $path;
        $this->authUserId = $authUserId;
        $this->importHistoryId = $importHistoryId;
        $this->importType = $importType;
        $this->originalFilename = $originalFilename;
    }

    public function handle()
    {
        Log::info('ProcessCandidateImport: Job started', [
            'history_id' => $this->importHistoryId,
            'path' => $this->path,
        ]);

        $importHistory = ImportHistory::find($this->importHistoryId);

        if (!$importHistory) {
            Log::error('ProcessCandidateImport: ImportHistory not found', [
                'history_id' => $this->importHistoryId,
            ]);
            return;
        }

        try {
            $path = $this->path;
            
            // Support both relative (Storage path) and absolute paths
            if (!str_starts_with($path, '/') && !preg_match('/^[a-z]:/i', $path)) {
                // Relative path - use Storage
                $absolutePath = Storage::path($path);
            } else {
                $absolutePath = $path;
            }

            Log::info('ProcessCandidateImport: Checking for file', [
                'history_id' => $this->importHistoryId,
                'path_to_check' => $absolutePath,
            ]);

            if (!file_exists($absolutePath)) {
                throw new \Exception('Import file not found: ' . $this->path);
            }

            if ($this->importType === 'assessment_unified') {
                [$processed, $skipped, $errors] = $this->processUnifiedAssessmentImport($absolutePath);
            } else {
                $import = new CandidatesImport($this->authUserId);
                Excel::import($import, $path);

                $processed = $import->getProcessedCount();
                $skipped = $import->getSkippedCount();
                $errors = $import->getErrors();
            }

            $importHistory->update([
                'success_rows' => $processed,
                'failed_rows' => $skipped,
                'status' => 'completed',
                'error_message' => null,
                'error_details' => count($errors) > 0 ? $errors : null,
            ]);

            Log::info('ProcessCandidateImport: Import completed', [
                'history_id' => $this->importHistoryId,
                'processed' => $processed,
                'skipped' => $skipped,
                'error_count' => count($errors),
            ]);

            // Hapus file setelah impor sukses
            $this->deleteFile();

        } catch (Throwable $e) {

            Log::error('ProcessCandidateImport: Import failed', [
                'history_id' => $this->importHistoryId,
                'error' => $e->getMessage(),
            ]);

            $importHistory->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e; // penting supaya queue retry
        }
    }

    public function failed(Throwable $exception)
    {
        Log::critical('ProcessCandidateImport: Job permanently failed', [
            'history_id' => $this->importHistoryId,
            'exception' => $exception->getMessage(),
        ]);

        $importHistory = ImportHistory::find($this->importHistoryId);
        if ($importHistory) {
            $importHistory->update([
                'status' => 'failed',
                'error_message' => 'Job failed permanently: ' . $exception->getMessage(),
            ]);
        }

        // Hapus file setelah semua percobaan gagal
        $this->deleteFile();
    }

    private function deleteFile()
    {
        $path = $this->path;

        // Support both relative and absolute paths
        if (!str_starts_with($path, '/') && !preg_match('/^[a-z]:/i', $path)) {
            $path = Storage::path($path);
        }

        if (file_exists($path)) {
            unlink($path);
            Log::info('ProcessCandidateImport: File deleted', [
                'history_id' => $this->importHistoryId,
            ]);
        }
    }

    private function processUnifiedAssessmentImport(string $absolutePath): array
    {
        $parsed = $this->parseAssessmentFile($absolutePath);
        $assessmentRows = $parsed['rows'];

        $candidateRows = array_map(function (array $row) {
            return $this->mapAssessmentToCandidateRow($row);
        }, $assessmentRows);

        $candidateImport = new CandidatesImport($this->authUserId);
        $candidateImport->collection(collect($candidateRows));

        $errors = $candidateImport->getErrors();
        $assessmentProcessed = 0;
        $assessmentSkipped = 0;

        foreach ($assessmentRows as $row) {
            $rowNumber = $row['__row_number'] ?? null;
            $applicantId = trim((string) ($row['applicant_id'] ?? ''));
            $testDate = $this->parseDate($row['test_date'] ?? null);

            if ($applicantId === '' || !$testDate) {
                $assessmentSkipped++;
                $errors[] = [
                    'row' => $rowNumber,
                    'applicant_id' => $applicantId !== '' ? $applicantId : null,
                    'nama' => $row['applicant_name'] ?? null,
                    'error' => 'Data assessment tidak valid (applicant_id atau test_date kosong/tidak valid).',
                ];
                continue;
            }

            if (!Candidate::where('applicant_id', $applicantId)->exists()) {
                $assessmentSkipped++;
                $errors[] = [
                    'row' => $rowNumber,
                    'applicant_id' => $applicantId,
                    'nama' => $row['applicant_name'] ?? null,
                    'error' => 'Kandidat tidak ditemukan setelah proses candidate import.',
                ];
                continue;
            }

            $payload = $this->mapRowToAssessmentPayload($row, $this->originalFilename ?? basename($absolutePath), $testDate);

            CandidateAssessmentResult::updateOrCreate(
                [
                    'applicant_id' => $payload['applicant_id'],
                    'test_date' => $payload['test_date'],
                    'provider' => $payload['provider'],
                    'assessment_type' => $payload['assessment_type'],
                ],
                $payload
            );

            $assessmentProcessed++;
        }

        $processed = min($candidateImport->getProcessedCount(), $assessmentProcessed);
        $skipped = max($candidateImport->getSkippedCount(), $assessmentSkipped);

        return [$processed, $skipped, $errors];
    }

    private function mapAssessmentToCandidateRow(array $row): array
    {
        $vacancy = trim((string) ($row['vacancy_title'] ?? ''));
        $vacancy = preg_replace('/\s*-\s*PMP\b/i', '', $vacancy);

        return [
            'tahun_mpp' => (string) now()->year,
            'applicant_id' => $row['applicant_id'] ?? null,
            'nama' => $row['applicant_name'] ?? null,
            'email' => $row['email'] ?? null,
            'jk' => $row['gender'] ?? null,
            'tanggal_lahir' => $row['date_of_birth'] ?? null,
            'perguruan_tinggi' => $row['university'] ?? null,
            'jurusan' => $row['major'] ?? null,
            'source' => 'Airsys',
            'vacancy' => trim((string) $vacancy),
            'psikotest_result' => $row['final_result_hasil_cut_off_score'] ?? null,
            'test_date' => $row['test_date'] ?? null,
            'psikotes_notes' => '-',
        ];
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

    private function mapRowToAssessmentPayload(array $row, string $filename, string $testDate): array
    {
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
                'vacancy_title' => $this->normalizeCell($row['vacancy_title'] ?? null),
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
