<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class RrhhColaborador extends Model
{
    protected $table = 'rrhh_colaboradors';

    protected $fillable = [
        'fullName',
        'departmento_id',
        'cargo_id',
        'cedula',
        'sexo',
        'fechaNacimiento',
        'fechaIngreso',
        'telefono',
        'telefonoCorporativo',
        'emailCorporativo',
        'emailAlternativo',
        'emailPersonal',
        'direccion',
        'nroHijos',
        'nroHijoDependiente',
        'tallaCamisa',
        'banck_id',
        'nroCta',
        'codigoCta',
        'tipoCta',
        'status',
        'created_by',
        'updated_by',
        'avatar',
        'funciones',
        'sueldo',
        'user_id',
        'documents',
        'age',
        'birth_date',
    ];

    protected $casts = [
        'documents' => 'array',
        'birth_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (RrhhColaborador $model): void {
            if ($model->birth_date !== null && $model->birth_date !== '') {
                $normalizedBirthDate = self::normalizeBirthDateInput($model->birth_date);

                if ($normalizedBirthDate === null) {
                    return;
                }

                $model->birth_date = $normalizedBirthDate;
                $model->age = self::completedYearsFromBirthDate($normalizedBirthDate);
                $model->fechaNacimiento = Carbon::createFromFormat('Y-m-d', $normalizedBirthDate)->format('d/m/Y');

                return;
            }

            if ($model->isDirty('birth_date')) {
                $model->age = null;
            }
        });
    }

    /**
     * Normaliza entrada de fecha de nacimiento a Y-m-d para la columna date.
     */
    public static function normalizeBirthDateInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $normalized)) {
            return Carbon::parse($normalized)->format('Y-m-d');
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $normalized)->format('Y-m-d');
        } catch (\Throwable) {
            try {
                return Carbon::parse($normalized)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * Años cumplidos respecto a la fecha actual (0 si la fecha es futura).
     */
    public static function completedYearsFromBirthDate(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $normalized = $value instanceof CarbonInterface
                ? $value->format('Y-m-d')
                : (string) $value;

            $date = Carbon::parse($normalized);

            return max(0, $date->age);
        } catch (\Throwable) {
            return null;
        }
    }

    public function departamento()
    {
        return $this->belongsTo(RrhhDepartamento::class, 'departmento_id');
    }

    public function cargo()
    {
        return $this->belongsTo(RrhhCargo::class, 'cargo_id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class);
    }

    public function prospect_agent_tasks()
    {
        return $this->hasMany(ProspectAgentTask::class);
    }

    public function prospect_agent_observations()
    {
        return $this->hasMany(ProspectAgentObservation::class);
    }

    public function helpDesks(): BelongsToMany
    {
        return $this->belongsToMany(HelpDesk::class, 'help_desk_rrhh_colaborador')
            ->withTimestamps();
    }
}
