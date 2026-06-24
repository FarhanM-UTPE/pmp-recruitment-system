<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateAssessmentResult;

class CandidateAssessmentScoreController extends Controller
{
    /**
     * Get latest assessment result for a candidate by applicant id.
     */
    public function latestForCandidate(Candidate $candidate): ?CandidateAssessmentResult
    {
        if (empty($candidate->applicant_id)) {
            return null;
        }

        return CandidateAssessmentResult::query()
            ->where('applicant_id', $candidate->applicant_id)
            ->orderByDesc('test_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Determine if a result has at least one displayable score.
     */
    public function hasDisplayableScore(?CandidateAssessmentResult $assessment): bool
    {
        if (!$assessment) {
            return false;
        }

        $scoreColumns = [
            'action_score',
            'achievement_orientation_score',
            'assertiveness_score',
            'conscientiousness_score',
            'extraversion_score',
            'flexibility_score',
            'idea_score',
            'impact_and_influence_score',
            'persistence_score',
            'personal_motivation_score',
            'teamwork_score',
            'working_autonomously_score',
            'reading_comprehension_score',
            'quantitative_reasoning_score',
            'inductive_reasoning_score',
            'working_memory_score',
            'perceptual_speed_score',
            'deductive_reasoning_score',
            'visualization_score',
            'competency_aj_score',
            'competency_dc_score',
            'competency_is_score',
            'competency_pda_score',
            'competency_tw_score',
        ];

        foreach ($scoreColumns as $column) {
            if ($assessment->{$column} !== null) {
                return true;
            }
        }

        return false;
    }
}
