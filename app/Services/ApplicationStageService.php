<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ApplicationStageService
{
    private array $stageConfig;

    public function __construct()
    {
        // This is now the single source of truth for the recruitment process flow.
        $this->stageConfig = [
            'psikotes' => ['order' => 0, 'display' => 'Psikotest', 'next' => 'hc_interview'],
            'hc_interview' => ['order' => 1, 'display' => 'HC Interview', 'next' => 'user_interview'],
            'user_interview' => ['order' => 2, 'display' => 'User Interview', 'next' => 'interview_bod'],
            'interview_bod' => ['order' => 3, 'display' => 'Interview BOD', 'next' => 'offering_letter'],
            'offering_letter' => ['order' => 4, 'display' => 'Offering Letter', 'next' => 'mcu'],
            'mcu' => ['order' => 5, 'display' => 'MCU', 'next' => 'hiring'],
            'hiring' => ['order' => 6, 'display' => 'Hiring', 'next' => null],
        ];
    }

    public function processStageUpdate(Application $application, array $validatedData): Application
    {
        $now = now();
        $stageKey = $validatedData['stage'];
        $updateDateOnly = isset($validatedData['update_date_only']) && $validatedData['update_date_only'] === true;
        
        // Use provided stage_date or default to now
        $stageDate = isset($validatedData['stage_date']) && $validatedData['stage_date'] 
            ? \Carbon\Carbon::parse($validatedData['stage_date']) 
            : $now;

        // If updating date only, skip validation and only update scheduled_date
        if ($updateDateOnly) {
            $existingStage = $application->stages()->where('stage_name', $stageKey)->first();
            if (!$existingStage) {
                throw ValidationException::withMessages(['stage' => 'Stage tidak ditemukan.']);
            }
            
            // Only update scheduled_date, keep existing result and other data
            $existingStage->update([
                'scheduled_date' => $stageDate,
            ]);
            
            return $application;
        }

        $this->validateStageTransition($application, $stageKey);

        // Update current stage
        $result = strtoupper($validatedData['result']);
        $stageData = [
            'stage_name' => $stageKey,
            'status' => $result,
            'scheduled_date' => $stageDate,
            'notes' => $validatedData['notes'] ?? null,
            'conducted_by_user_id' => Auth::id(),
        ];

        $application->stages()->updateOrCreate(['stage_name' => $stageKey], $stageData);

        // Handle post-update logic
        $nextStageKey = $this->stageConfig[$stageKey]['next'] ?? null;

        // "LULUS", "DISARANKAN", "DITERIMA", "HIRED" are all considered passing results
        if (in_array($result, ['LULUS', 'DISARANKAN', 'DITERIMA', 'HIRED'])) {
            if ($nextStageKey) {
                $this->prepareNextStage($application, $nextStageKey, $validatedData);
            }
        } 
        // "TIDAK LULUS", "DITOLAK", "GAGAL", "TIDAK DIHIRING", "TIDAK DISARANKAN" are all considered failing results
        elseif (in_array($result, ['TIDAK LULUS', 'DITOLAK', 'GAGAL', 'TIDAK DIHIRING', 'TIDAK DISARANKAN'])) {
            $this->resetFutureStages($application, $stageKey);
        }
        // "DIPERTIMBANGKAN" - does not advance to next stage, but does not fail the application either
        
        if ($nextStageKey && isset($validatedData['next_stage_date'])) {
            $this->createCalendarEvent($application, $nextStageKey, $validatedData['next_stage_date']);
        }

        $this->updateOverallApplicationStatus($application, $stageKey, $result);

        $application->candidate->save();
        $application->save();

        return $application;
    }

    public function resetStage(Application $application, string $stageKey): void
    {
        $stage = $application->stages()->where('stage_name', $stageKey)->first();
        if (!$stage) {
            throw ValidationException::withMessages(['stage' => 'Stage tidak ditemukan.']);
        }

        // Delete the stage
        $stage->delete();

        // Also delete future stages if any
        $this->resetFutureStages($application, $stageKey);

        // Check for "Move Position" revert scenario
        // If we just reset the stage that was passed during move (like interview_bod) 
        // OR if this application was created via move and now has no stages left 
        // (meaning it's just the 'pending' next stage created by the system)
        if ($application->stages()->count() === 0) {
            // Find a previous application that was set to 'PINDAH'
            $previousApplication = $application->candidate->applications()
                ->where('id', '!=', $application->id)
                ->where('overall_status', 'PINDAH')
                ->latest()
                ->first();

            if ($previousApplication) {
                // Delete this current (new) application
                $candidate = $application->candidate;
                $application->delete();

                // Restore previous application
                $previousApplication->update(['overall_status' => 'PROSES']);
                
                // Restore candidate's previous department/type from previous vacancy
                if ($previousApplication->vacancy) {
                    $candidate->department_id = $previousApplication->vacancy->department_id;
                    // Try to restore airsys_internal if possible from old vacancy
                    // This is complex as it depends on mpp_year, but we'll revert to 
                    // a reasonable state or just keep current (better than nothing)
                    $mpp = $previousApplication->vacancy->mppSubmissions()
                        ->where('year', $previousApplication->mpp_year)
                        ->where('proposal_status', 'approved')
                        ->first();
                    if ($mpp) {
                        $candidate->airsys_internal = ($mpp->pivot->vacancy_status === 'OSPKWT') ? 'Yes' : 'No';
                    }
                    $candidate->save();
                }
                
                return; // Revert complete
            }
        }

        // After deleting, recreate this stage as MENUNGGU 
        // This ensures the stage name is preserved in the list view
        $application->stages()->create([
            'stage_name' => $stageKey,
            'status' => 'MENUNGGU',
            'scheduled_date' => now(),
            'conducted_by_user_id' => Auth::id(),
        ]);

        // Re-evaluate overall status for normal reset
        $application->overall_status = 'PROSES';
        $application->candidate->status = 'active';

        $application->save();
        $application->candidate->save();
    }

    public function copyStages(Application $fromApplication, Application $toApplication): void
    {
        // Sort stages by order from stageConfig to ensure sequential processing
        $stages = $fromApplication->stages->sortBy(function ($stage) {
            return $this->stageConfig[$stage->stage_name]['order'] ?? 999;
        });

        foreach ($stages as $stage) {
            $result = strtoupper($stage->status);
            
            // Create the stage record for the new application
            $toApplication->stages()->updateOrCreate(
                ['stage_name' => $stage->stage_name],
                [
                    'status' => $result,
                    'scheduled_date' => $stage->scheduled_date,
                    'conducted_by_user_id' => $stage->conducted_by_user_id,
                    'notes' => $stage->notes,
                ]
            );

            // Trigger next stage creation if the current stage was passed
            $nextStageKey = $this->stageConfig[$stage->stage_name]['next'] ?? null;
            if ($nextStageKey && in_array($result, ['LULUS', 'DISARANKAN', 'DITERIMA', 'HIRED'])) {
                // Only create if it doesn't already exist from the previous fromApplication stages
                $existsInFrom = $fromApplication->stages->where('stage_name', $nextStageKey)->first();
                if (!$existsInFrom) {
                    $this->prepareNextStage($toApplication, $nextStageKey, []);
                }
            }

            $this->updateOverallApplicationStatus($toApplication, $stage->stage_name, $result);
        }
        
        $toApplication->save();
    }

    private function getPreviousStageKey(string $currentStageKey): ?string
    {
        if (!isset($this->stageConfig[$currentStageKey])) {
            return null;
        }

        $currentStageOrder = $this->stageConfig[$currentStageKey]['order'];

        // Get previous stage key
        if ($currentStageOrder > 0) {
            foreach ($this->stageConfig as $key => $config) {
                if ($config['order'] === $currentStageOrder - 1) {
                    return $key;
                }
            }
        }

        return null;
    }

    private function validateStageTransition(Application $application, string $currentStageKey): void
    {
        if (!isset($this->stageConfig[$currentStageKey])) {
            throw ValidationException::withMessages(['stage' => 'Tahapan ini tidak valid.']);
        }

        $currentStageOrder = $this->stageConfig[$currentStageKey]['order'];

        // Check if previous stage was passed
        if ($currentStageOrder > 0) {
            $previousStageKey = $this->getPreviousStageKey($currentStageKey);
            
            if ($previousStageKey) {
                $previousStage = $application->stages()->where('stage_name', $previousStageKey)->first();
                if (!$previousStage || !in_array(strtoupper($previousStage->status), ['LULUS', 'DISARANKAN', 'DITERIMA', 'HIRED'])) {
                    throw ValidationException::withMessages([
                        'stage' => 'Tidak dapat mengubah stage ini. Selesaikan tahap sebelumnya terlebih dahulu.'
                    ]);
                }
            }
        }
    }

    private function prepareNextStage(Application $application, string $nextStageKey, array $validatedData): void
    {
        $this->resetFutureStages($application, $nextStageKey, true);

        // Create or update the next stage
        $nextStageData = [
            'stage_name' => $nextStageKey,
            'status' => 'MENUNGGU',
            'scheduled_date' => $validatedData['next_stage_date'] ?? null,
            'notes' => 'Otomatis dibuat setelah tahap sebelumnya lulus.',
            'conducted_by' => null,
        ];
        $application->stages()->updateOrCreate(['stage_name' => $nextStageKey], $nextStageData);
    }

    private function resetFutureStages(Application $application, string $currentStageKey, bool $inclusive = false): void
    {
        $currentOrder = $this->stageConfig[$currentStageKey]['order'];
        
        $stagesToDelete = collect($this->stageConfig)->filter(function ($config) use ($currentOrder, $inclusive) {
            return $inclusive ? $config['order'] >= $currentOrder : $config['order'] > $currentOrder;
        })->keys();

        if ($stagesToDelete->isNotEmpty()) {
            $application->stages()->whereIn('stage_name', $stagesToDelete)->delete();
        }
    }

    private function createCalendarEvent(Application $application, string $stageName, string $date): void
    {
        $title = $this->stageConfig[$stageName]['display'] ?? $stageName;

        Event::updateOrCreate(
            ['candidate_id' => $application->candidate_id, 'stage' => $stageName],
            [
                'title' => $title,
                'description' => "Jadwal {$title} untuk kandidat {$application->candidate->nama}",
                'date' => $date,
                'time' => '09:00', // Default time
                'status' => 'active',
                'created_by' => Auth::id(),
            ]
        );
    }

    private function updateOverallApplicationStatus(Application $application, string $stageKey, string $result): void
    {
        // DITOLAK/TIDAK LULUS at any stage means rejection
        if (in_array($result, ['GAGAL', 'TIDAK LULUS', 'DITOLAK', 'TIDAK DIHIRING', 'TIDAK DISARANKAN'])) {
            $application->overall_status = 'DITOLAK';
            // Check if all other applications are also rejected before marking candidate as inactive
            $otherApplications = $application->candidate->applications()->where('id', '!=', $application->id)->get();
            $allOthersRejected = $otherApplications->every(fn($app) => $app->overall_status === 'DITOLAK' || $app->overall_status === 'CANCEL');
            if ($allOthersRejected) {
                $application->candidate->status = 'inactive';
            }
        } 
        // If last stage (hiring) and LULUS/DITERIMA/HIRED -> LULUS (fully passed)
        elseif (($this->stageConfig[$stageKey]['next'] === null) && in_array($result, ['LULUS', 'DITERIMA', 'HIRED'])) {
            $application->overall_status = 'LULUS';
            if ($application->vacancy) {
                $application->vacancy->increment('accepted_count');
            }

            // --- NEW LOGIC: Cancel other applications ---
            $candidateId = $application->candidate_id;
            Application::where('candidate_id', $candidateId)
                ->where('id', '!=', $application->id)
                ->update(['overall_status' => 'CANCEL']);
            
            // Set candidate status to inactive since they are hired
            $application->candidate->status = 'inactive';
        } 
        // All other cases -> PROSES (still in process, including DIPERTIMBANGKAN)
        else {
            $application->overall_status = 'PROSES';
            $application->candidate->status = 'active';
        }
    }
}


