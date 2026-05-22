<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TdgCalendarOffice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdgCalendarOfficeAssignment extends Model
{
    protected $fillable = [
        'tdg_calendar_day_id',
        'office',
        'rrhh_colaborador_id',
    ];

    protected function casts(): array
    {
        return [
            'office' => TdgCalendarOffice::class,
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
