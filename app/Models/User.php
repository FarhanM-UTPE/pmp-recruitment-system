<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $role
 * @property \App\Models\Department|null $department
 * @property bool $status
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $department_id
 * @property-read mixed $primary_role
 * @property-read mixed $role_badge_class
 * @property-read mixed $role_display
 * @property-read string $role_display_name
 * @property-read mixed $status_badge_class
 * @property-read string $status_display
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDepartment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * Preferred role order for UI display.
     * Highest priority appears first.
     *
     * @var array<int, string>
     */
    private const ROLE_DISPLAY_PRIORITY = [
        'executive',
        'dic_approver',
        'hcd_div_head',
        'hcd_dept_head',
        'pic_recruitment',
        'division_head',
        'kepala departemen',
        'team_hc_2',
        'team_hc',
        'admin',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'approval_display_name',
        'division_name',
        'email',
        'nrp',
        'password',
        'department_id',
        'accessible_department_ids',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'accessible_department_ids' => 'array',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute(): string
    {
        return $this->status ? 'Aktif' : 'Tidak Aktif';
    }

    /**
     * Scope query to only active users
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get the user's role display name.
     */
    public function getRoleDisplayNameAttribute(): string
    {
        $primaryRole = $this->primary_role;

        $roleDisplay = [
            'executive' => 'Executive',
            'dic_approver' => 'DIC Approver',
            'hcd_div_head' => 'HCD Div. Head',
            'hcd_dept_head' => 'HCD Dept. Head',
            'pic_recruitment' => 'PIC Recruitment',
            'division_head' => 'Division Head',
            'kepala departemen' => 'Kepala Departemen',
            'team_hc' => 'Team HC',
            'team_hc_2' => 'Team HC 2',
            'admin' => 'Administrator',
        ];

        if (isset($roleDisplay[$primaryRole])) {
            return $roleDisplay[$primaryRole];
        }

        if ($this->hasRole('division_head')) {
            return 'Division Head';
        }
        if ($this->hasRole('kepala departemen')) {
            return 'Kepala Departemen';
        }
        if ($this->hasRole('team_hc')) {
            return 'Team HC';
        }
        if ($this->hasRole('team_hc_2')) {
            return 'Team HC 2';
        }
        if ($this->hasRole('admin')) {
            return 'Administrator';
        }

        // Fallback to the first role name if no specific display name is defined
        return ucfirst(str_replace('_', ' ', $this->getRoleNames()->first() ?? 'N/A'));
    }

    /**
     * Get the user's primary role name
     */
    public function getPrimaryRoleAttribute()
    {
        $roles = $this->getRoleNames();

        foreach (self::ROLE_DISPLAY_PRIORITY as $roleName) {
            if ($roles->contains($roleName)) {
                return $roleName;
            }
        }

        return $roles->first() ?? 'N/A';
    }

    /**
     * Get formatted role name
     */
    public function getRoleDisplayAttribute()
    {
        $primaryRole = $this->primary_role;

        $roleNames = [
            'admin' => 'Administrator',
            'team_hc' => 'Team HC',
            'team_hc_2' => 'Team HC 2',
            'kepala departemen' => 'Kepala Departemen',
            'division_head' => 'Division Head',
        ];

        return $roleNames[$primaryRole] ?? ucfirst(str_replace('_', ' ', $primaryRole));
    }

    /**
     * Get role badge class for display
     */
    public function getRoleBadgeClassAttribute()
    {
        $primaryRole = $this->primary_role;

        $badgeClasses = [
            'admin' => 'bg-red-100 text-red-800',
            'team_hc' => 'bg-blue-100 text-blue-800',
            'team_hc_2' => 'bg-indigo-100 text-indigo-800',
            'kepala departemen' => 'bg-green-100 text-green-800',
            'division_head' => 'bg-amber-100 text-amber-800',
        ];

        return $badgeClasses[$primaryRole] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        return $this->status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    }

    /**
     * Name to be shown in approval/signature matrix.
     */
    public function getApprovalSignerNameAttribute(): string
    {
        return trim((string) ($this->approval_display_name ?: $this->name));
    }

    /**
     * Return all department IDs accessible by this user.
     * Falls back to single department_id for legacy accounts.
     *
     * @return array<int>
     */
    public function getAccessibleDepartmentIds(): array
    {
        $ids = collect($this->accessible_department_ids ?? [])
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty() && $this->department_id) {
            return [(int) $this->department_id];
        }

        return $ids->all();
    }

    /**
     * Check whether user can access a department.
     */
    public function hasDepartmentAccess(int $departmentId): bool
    {
        return in_array($departmentId, $this->getAccessibleDepartmentIds(), true);
    }
}