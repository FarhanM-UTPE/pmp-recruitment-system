<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vacancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'department_id',
        'is_active',
        'accepted_count',
    ];

    protected $casts = [
        'accepted_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function manpowerRequestFiles(): HasMany
    {
        return $this->hasMany(ManpowerRequestFile::class);
    }

    public function proposalHistories(): HasMany
    {
        return $this->hasMany(VacancyProposalHistory::class);
    }

    public function mppSubmissions()
    {
        return $this->belongsToMany(MPPSubmission::class, 'mpp_submission_vacancy', 'vacancy_id', 'm_p_p_submission_id')
            ->withPivot([
                'vacancy_status',
                'needed_count',
                'proposal_status',
                'rejection_reason',
                'proposed_needed_count',
                'proposed_by_user_id',
            ])
            ->withTimestamps();
    }

    /**
     * New-form MPP submissions linked directly through mpp_submissions.vacancy_id.
     */
    public function directMppSubmissions(): HasMany
    {
        return $this->hasMany(MPPSubmission::class, 'vacancy_id', 'id');
    }

    public function vacancyDocuments(): HasMany
    {
        return $this->hasMany(VacancyDocument::class);
    }

    /**
     * Get the latest document of a specific type
     */
    public function getDocument(string $type): ?VacancyDocument
    {
        return $this->vacancyDocuments()
            ->where('document_type', $type)
            ->latest()
            ->first();
    }
}
