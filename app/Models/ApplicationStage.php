<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property string $stage_name
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $scheduled_date
 * @property string|null $notes
 * @property int|null $conducted_by_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $next_stage_date
 * @property-read \App\Models\Application $application
 * @property-read \App\Models\User|null $conductedByUser
 */
class ApplicationStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'stage_name',
        'status',
        'scheduled_date',
        'notes',
        'conducted_by_user_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function conductedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by_user_id');
    }
}