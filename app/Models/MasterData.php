<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterData extends Model
{
    use HasFactory;

    protected $table = 'master_data';

    protected $fillable = [
        'type',
        'year',
        'department_id',
        'key',
        'label',
        'value',
        'meta',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'meta' => 'array',
        'active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
