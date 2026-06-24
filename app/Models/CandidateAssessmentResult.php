<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateAssessmentResult extends Model
{
    protected $table = 'candidate_assessment_results';

    protected $guarded = [];

    protected $casts = [
        'test_date' => 'date',
        'imported_at' => 'datetime',
        'raw_identity_snapshot' => 'array',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'applicant_id', 'applicant_id');
    }
}
