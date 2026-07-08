<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MPPSubmissionApprovalStage extends Model
{
    use HasFactory;

    protected $table = 'mpp_submission_approval_stages';

    protected $fillable = [
        'mpp_submission_id',
        'stage_index',
        'role',
        'required_roles',
        'name',
        'approver_user_id',
        'decision',
        'digitally_signed',
        'signature_label',
        'signed_at',
    ];

    protected $casts = [
        'required_roles' => 'array',
        'digitally_signed' => 'boolean',
        'signed_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MPPSubmission::class, 'mpp_submission_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
