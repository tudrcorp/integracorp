<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TdgCalendarDay extends Model
{
    protected $fillable = [
        'calendar_date',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'calendar_date' => 'date',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function officeAssignments(): HasMany
    {
        return $this->hasMany(TdgCalendarOfficeAssignment::class);
    }

    public function guardAssignments(): HasMany
    {
        return $this->hasMany(TdgCalendarGuardAssignment::class);
    }

    public function departmentAssignments(): HasMany
    {
        return $this->hasMany(TdgCalendarDepartmentAssignment::class);
    }
}
