<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MPPSubmission extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $table = 'mpp_submissions';

    protected $fillable = [
        'created_by_user_id',
        'department_id',
        'year', // Added year to fillable
        'submission_type',
        'form_version',
        'vacancy_id',
        'custom_jabatan_name',
        'golongan',
        'status_pegawai',
        'lokasi_pekerjaan',
        'tanggal_mulai_bekerja',
        'jumlah_diminta',
        'alasan_penambahan_manpower',
        'nama_karyawan_diganti',
        'tanggal_keluar',
        'alasan_penggantian',
        'jumlah_karyawan_ada',
        'kesesuaian_man_power_plan',
        'alasan_kesesuaian',
        'pendidikan_requirements',
        'keahlian_khusus',
        'jenis_kelamin',
        'status_perkawinan',
        'pengalaman_kerja',
        'uraian_jabatan',
        'fasilitas_dibutuhkan',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'status_pegawai' => 'array',
        'tanggal_mulai_bekerja' => 'date',
        'tanggal_keluar' => 'date',
        'pendidikan_requirements' => 'array',
        'uraian_jabatan' => 'array',
        'fasilitas_dibutuhkan' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the user who created the MPP submission
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get selected vacancy for new form submissions.
     */
    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    /**
     * Get all vacancies associated with this MPP
     */
    public function vacancies()
    {
        return $this->belongsToMany(Vacancy::class, 'mpp_submission_vacancy', 'm_p_p_submission_id', 'vacancy_id')
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
     * Get approval history
     */
    public function approvalHistories(): HasMany
    {
        return $this->hasMany(MPPApprovalHistory::class, 'mpp_submission_id', 'id');
    }

    /**
     * Get normalized approval stages for new-form submissions.
     */
    public function approvalStages(): HasMany
    {
        return $this->hasMany(MPPSubmissionApprovalStage::class, 'mpp_submission_id', 'id')
            ->orderBy('stage_index');
    }

    /**
     * Check if MPP is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->status !== self::STATUS_DRAFT;
    }

    /**
     * Check if MPP is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Submit the MPP
     */
    public function submit(User $user): void
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->approvalHistories()->create([
            'user_id' => $user->id,
            'action' => 'submitted',
        ]);
    }

    /**
     * Approve the MPP
     */
    public function approve(User $user, string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $this->approvalHistories()->create([
            'user_id' => $user->id,
            'action' => 'approved',
            'notes' => $notes,
        ]);

        // Notify department heads of vacancies
        $this->notifyDepartmentHeads();
    }

    /**
     * Reject the MPP
     */
    public function reject(User $user, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->approvalHistories()->create([
            'user_id' => $user->id,
            'action' => 'rejected',
            'notes' => $reason,
        ]);
    }

    /**
     * Notify department heads
     */
    private function notifyDepartmentHeads(): void
    {
        // Get department heads for this department
        $departmentHeads = User::where('department_id', $this->department_id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'kepala departemen');
            })
            ->get();

        foreach ($departmentHeads as $head) {
            // Send notification (implement your notification logic here)
            \Log::info("Notifying department head {$head->id} about MPP {$this->id}");
        }
    }
}
