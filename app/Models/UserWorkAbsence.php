<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\UserActivity\Workday\WorkAbsenceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ausencia justificada (vacaciones, reposo, permiso) entre dos fechas,
 * ambas incluidas: esos días la persona no tiene jornada.
 */
class UserWorkAbsence extends Model
{
    /** @use HasFactory<\Database\Factories\UserWorkAbsenceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'starts_on',
        'ends_on',
        'reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkAbsenceType::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
