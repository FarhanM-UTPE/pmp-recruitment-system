<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Candidate extends Model
{
    use HasFactory;

    /**
     * =========================
     * TABLE & MASS ASSIGNMENT
     * =========================
     */
    protected $table = 'candidates';

    protected $fillable = [
        'no',
        'raw_department_name',
        'department_id',
        'mpp_year',
        'applicant_id',
        'nama',
        'source',
        'jk',
        'tanggal_lahir',
        'alamat_email',
        'jenjang_pendidikan',
        'perguruan_tinggi',
        'jurusan',
        'ipk',
        'cv',
        'flk',
        'airsys_internal',
        'status',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    /**
     * =========================
     * RELATIONSHIPS
     * =========================
     */

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function educations()
    {
        return $this->hasMany(Education::class);
    }

    public function applicationStages()
    {
        return $this->hasManyThrough(ApplicationStage::class, Application::class);
    }

    public function editHistories()
    {
        return $this->hasMany(CandidateEditHistory::class);
    }

    /**
     * Psikotest result (latest)
     */
    public function latestPsikotest()
    {
        return $this->hasOneThrough(ApplicationStage::class, Application::class)
            ->where('application_stages.stage_name', 'psikotes')
            ->latest('application_stages.updated_at');
    }

    /**
     * HC Interview result (latest)
     */
    public function latestHCInterview()
    {
        return $this->hasOneThrough(ApplicationStage::class, Application::class)
            ->where('application_stages.stage_name', 'hc_interview')
            ->latest('application_stages.updated_at');
    }

    public function latestUserInterview()
    {
        return $this->hasOneThrough(ApplicationStage::class, Application::class)
            ->where('application_stages.stage_name', 'user_interview')
            ->latest('application_stages.updated_at');
    }

    public function latestBodInterview()
    {
        return $this->hasOneThrough(ApplicationStage::class, Application::class)
            ->where('application_stages.stage_name', 'bod_interview')
            ->latest('application_stages.updated_at');
    }

    public function latestOffering()
    {
        return $this->hasOneThrough(ApplicationStage::class, Application::class)
            ->where('application_stages.stage_name', 'offering')
            ->latest('application_stages.updated_at');
    }

    public function latestMcu()
    {
        return $this->hasOneThrough(ApplicationStage::class, Application::class)
            ->where('application_stages.stage_name', 'mcu')
            ->latest('application_stages.updated_at');
    }

    public function latestHired()
    {
        return $this->hasOneThrough(ApplicationStage::class, Application::class)
            ->where('application_stages.stage_name', 'hired')
            ->latest('application_stages.updated_at');
    }

    /**
     * =========================
     * BUSINESS LOGIC (CORE)
     * =========================
     */

    /**
     * FINAL STATUS (dipakai di badge, tabel, summary)
     */
    public function getFinalStatusAttribute(): string
    {
        $stages = $this->applicationStages()->orderBy('created_at', 'desc')->get();

        if ($stages->isEmpty()) {
            return 'ON_PROCESS';
        }

        // Check for any "cancel" status
        $cancelledStage = $stages->first(function ($stage) {
            return in_array(strtoupper($stage->status), ['CANCEL']);
        });

        if ($cancelledStage) {
            return 'CANCELLED';
        }

        // Check for any failure status
        $failedStage = $stages->first(function ($stage) {
            return in_array(strtoupper($stage->status), ['GAGAL', 'TIDAK LULUS', 'DITOLAK']);
        });

        if ($failedStage) {
            return 'FAILED';
        }

        // Check for hired status
        $hiredStage = $stages->first(function ($stage) {
            return in_array(strtoupper($stage->status), ['LULUS', 'DITERIMA', 'HIRED']);
        });

        if ($hiredStage) {
            // Special check for last stage completion
            $lastStage = $this->applicationStages()->orderBy('created_at', 'desc')->first();
            if (in_array(strtoupper($lastStage->stage_name), ['HC_INTERVIEW', 'HIRING']) && $hiredStage->id == $lastStage->id) {
                return 'HIRED';
            }
        }

        // Check for waiting for HC interview
        $psikotestPassed = $stages->first(function ($stage) {
            return strtoupper($stage->stage_name) === 'PSIKOTES' && strtoupper($stage->status) === 'LULUS';
        });

        if ($psikotestPassed) {
            $hcInterview = $stages->first(function ($stage) {
                return strtoupper($stage->stage_name) === 'HC_INTERVIEW';
            });
            if (!$hcInterview || $hcInterview->status === null) {
                return 'WAITING_HC_INTERVIEW';
            }
        }

        return 'ON_PROCESS';
    }

    /**
     * =========================
     * CURRENT STAGE (TIMELINE)
     * =========================
     */
    public function getCurrentStageAttribute(): string
    {
        $stages = $this->applicationStages()->orderBy('created_at', 'desc')->get();

        if ($stages->isEmpty()) {
            return 'APPLIED';
        }

        $failedStage = $stages->first(function ($stage) {
            return in_array(strtoupper($stage->status), ['GAGAL', 'TIDAK LULUS', 'DITOLAK']);
        });

        if ($failedStage) {
            return 'FAILED_AT_' . strtoupper($failedStage->stage_name);
        }

        $passedStage = $stages->first(function ($stage) {
            return in_array(strtoupper($stage->status), ['LULUS', 'DITERIMA', 'HIRED']);
        });

        if ($passedStage) {
            // If the latest passed stage is HC interview, they are hired
            if (strtoupper($passedStage->stage_name) === 'HC_INTERVIEW') {
                return 'HIRED';
            }
            // Otherwise, return the next stage
            $nextStage = $this->applicationStages()
                ->where('application_stages.created_at', '>', $passedStage->created_at)
                ->orderBy('application_stages.created_at')
                ->first();
            return $nextStage ? strtoupper($nextStage->stage_name) : strtoupper($passedStage->stage_name);
        }

        // If no stage is passed or failed, they are in the first stage
        $firstStage = $this->applicationStages()
            ->orderBy('application_stages.created_at')
            ->first();
        return $firstStage ? strtoupper($firstStage->stage_name) : 'APPLIED';
    }

    /**
     * =========================
     * TIMELINE DATA (VIEW READY)
     * =========================
     */
    public function getTimelineAttribute(): array
    {
        // For backward compatibility, default to the latest application's timeline
        $latestApplication = $this->applications()
            ->with([
                'stages' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                },
                'stages.conductedByUser'
            ])
            ->latest('updated_at') // Use updated_at for latest overall activity
            ->first();

        if (!$latestApplication) {
            return [];
        }

        return $this->getTimelineForApplication($latestApplication);
    }

    /**
     * Generates the timeline data for a specific application.
     */
    public function getTimelineForApplication(Application $application): array
    {
        // Single source of truth for stage order and display names.
        $stageConfig = [
            'psikotes' => 'Psikotest',
            'hc_interview' => 'HC Interview',
            'user_interview' => 'User Interview',
            'interview_bod' => 'Interview BOD',
            'offering_letter' => 'Offering Letter',
            'mcu' => 'MCU',
            'hiring' => 'Hiring',
        ];

        // Ensure stages and conductedByUser are loaded for the provided application
        if (!$application->relationLoaded('stages') || !$application->stages->every(fn($stage) => $stage->relationLoaded('conductedByUser'))) {
            $application->load([
                'stages' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                },
                'stages.conductedByUser'
            ]);
        }

        // Create a lookup map of existing stages for efficient access.
        $existingStages = $application->stages->keyBy('stage_name');
        $timeline = [];
        $previousStagePassed = true; // Start with the assumption that the first stage is unlocked.

        // Get stage keys in order
        $stageKeys = array_keys($stageConfig);

        foreach ($stageConfig as $key => $displayName) {
            $stage = $existingStages->get($key);
            $stageStatus = $stage->status ?? null;

            $currentStageStatus = 'locked'; // Default to locked.
            $hasPassed = false;

            if ($previousStagePassed) {
                if ($stage) {
                    $hasPassed = in_array(strtoupper($stageStatus), ['LULUS', 'DITERIMA', 'DISARANKAN', 'HIRED']);
                    if ($hasPassed) {
                        $currentStageStatus = 'completed';
                    } elseif (in_array(strtoupper($stageStatus), ['GAGAL', 'TIDAK LULUS', 'DITOLAK', 'TIDAK DISARANKAN'])) {
                        $currentStageStatus = 'failed';
                    } elseif (in_array(strtoupper($stageStatus), ['DIPERTIMBANGKAN', 'MENUNGGU'])) {
                        $currentStageStatus = 'pending';
                    } else {
                        $currentStageStatus = 'in_progress';
                    }
                } else {
                    // If the stage does not exist but the previous one passed, it's the current, pending stage.
                    $currentStageStatus = 'pending';
                }
            }

            // Check if next stage exists
            $currentIndex = array_search($key, $stageKeys);
            $nextStageKey = ($currentIndex !== false && isset($stageKeys[$currentIndex + 1])) ? $stageKeys[$currentIndex + 1] : null;
            $nextStage = $nextStageKey ? $existingStages->get($nextStageKey) : null;
            $nextStageExists = $nextStage !== null;
            $nextStageScheduledDate = $nextStage && $nextStage->scheduled_date ? $nextStage->scheduled_date->format('Y-m-d') : null;

            // Check previous stage for min date validation
            $previousStageKey = ($currentIndex !== false && $currentIndex > 0) ? $stageKeys[$currentIndex - 1] : null;
            $previousStage = $previousStageKey ? $existingStages->get($previousStageKey) : null;
            $previousStageDate = $previousStage && $previousStage->scheduled_date ? $previousStage->scheduled_date->format('Y-m-d') : null;

            // Check if this stage was edited
            $isEdited = false;

            // Can edit result only if next stage doesn't exist yet
            $canEditResult = !$nextStageExists;

            // Can reset only if THIS stage has a result (not MENUNGGU) 
            // AND next stage doesn't have a result (is MENUNGGU or doesn't exist)
            // AND is NOT an automated BOD pass from a position move (this is locked)
            $canReset = false;
            if ($stage && strtoupper($stageStatus) !== 'MENUNGGU') {
                if (!$nextStage || strtoupper($nextStage->status) === 'MENUNGGU') {
                    $canReset = true;

                    // Special rule: If this is the automated BOD stage from a move, do not allow reset.
                    if ($key === 'interview_bod' && $stage->notes && str_contains($stage->notes, '[PINDAH POSISI]')) {
                        $canReset = false;
                    }
                }
            }

            // SPECIAL CASE: If application overall_status is 'PINDAH', 
            // the last completed stage should be resettable to undo the move.
            if ($application->overall_status === 'PINDAH') {
                // Find the actual last stage that exists in the DB for this application
                $lastStageInDb = $application->stages->sortByDesc(function ($s) use ($stageConfig) {
                    return array_search($s->stage_name, array_keys($stageConfig));
                })->first();

                if ($lastStageInDb && $stage && $stage->id === $lastStageInDb->id) {
                    $canReset = true;
                }
            }

            $timeline[] = [
                'stage_key' => $key,
                'display_name' => $displayName,
                'status' => $currentStageStatus,
                'result' => $stageStatus,
                'date' => $stage?->scheduled_date ? $stage->scheduled_date->format('Y-m-d') : null,
                'notes' => $stage?->notes ?? null,
                'evaluator' => $stage?->conductedByUser->name ?? null,
                'next_stage_exists' => $nextStageExists,
                'next_stage_scheduled_date' => $nextStageScheduledDate,
                'can_edit_result' => $canEditResult,
                'can_reset' => $canReset,
                'previous_stage_date' => $previousStageDate,
                'is_edited' => $isEdited,
            ];

            // The next stage is only unlocked if the current stage has been passed.
            // If it failed or is still in progress, subsequent stages remain locked.
            if ($currentStageStatus !== 'completed') {
                $previousStagePassed = false;
            }
        }

        return $timeline;
    }

    /**
     * =========================
     * HELPER (VIEW FRIENDLY)
     * =========================
     */

    public function getStageLabelAttribute(): string
    {
        return match ($this->current_stage) {
            'APPLIED' => 'Applied',
            'PSIKOTEST' => 'Psikotest',
            'HC_INTERVIEW' => 'HC Interview',
            'FAILED_AT_PSIKOTEST' => 'Failed (Psikotest)',
            'FAILED_AT_HC_INTERVIEW' => 'Failed (HC Interview)',
            'HIRED' => 'Hired',
            default => 'On Process',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->final_status) {
            'FAILED' => 'red',
            'HIRED' => 'green',
            'WAITING_HC_INTERVIEW' => 'yellow',
            'CANCELLED' => 'gray',
            default => 'blue',
        };
    }

    /**
     * Format stage name for display
     * Converts 'hc_interview' to 'HC Interview', etc.
     */
    public static function formatStageName(string $stageName): string
    {
        $stageMap = [
            'psikotes' => 'Psikotest',
            'hc_interview' => 'HC Interview',
            'user_interview' => 'User Interview',
            'interview_bod' => 'Interview BOD',
            'offering_letter' => 'Offering Letter',
            'mcu' => 'MCU',
            'hiring' => 'Hiring',
        ];

        return $stageMap[strtolower($stageName)] ?? Str::title(str_replace('_', ' ', $stageName));
    }
}
