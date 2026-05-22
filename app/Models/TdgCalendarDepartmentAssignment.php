<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TdgCalendarDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdgCalendarDepartmentAssignment extends Model
{
    protected $fillable = [
        'tdg_calendar_day_id',
        'department',
    ];

    protected function casts(): array
    {
        return [
            'department' => TdgCalendarDepartment::class,
        ];
    }

    public function calendarDay(): BelongsTo
    {
        return $this->belongsTo(TdgCalendarDay::class, 'tdg_calendar_day_id');
    }
}
