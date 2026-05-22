<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TdgCalendarGuardShift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdgCalendarGuardAssignment extends Model
{
    protected $fillable = [
        'tdg_calendar_day_id',
        'guard_shift',
        'rrhh_colaborador_id',
    ];

    protected function casts(): array
    {
        return [
            'guard_shift' => TdgCalendarGuardShift::class,
        ];
    }

    public function calendarDay(): BelongsTo
    {
        return $this->belongsTo(TdgCalendarDay::class, 'tdg_calendar_day_id');
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RrhhColaborador::class, 'rrhh_colaborador_id');
    }
}
