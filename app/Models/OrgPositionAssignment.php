<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrgAssignmentRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quién ocupa un puesto. Al egresar un colaborador la fila no se borra: se cierra
 * con `ends_on` y el puesto queda vacante.
 *
 * @property int $id
 * @property int $version_id
 * @property int $position_id
 * @property int $rrhh_colaborador_id
 * @property OrgAssignmentRole $role
 */
class OrgPositionAssignment extends Model
{
    use HasFactory;

    protected $table = 'org_position_assignments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'version_id',
        'position_id',
        'rrhh_colaborador_id',
        'role',
        'starts_on',
        'ends_on',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => OrgAssignmentRole::class,
            'rrhh_colaborador_id' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<OrgPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(OrgPosition::class, 'position_id');
    }

    /**
     * @return BelongsTo<RrhhColaborador, $this>
     */
    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RrhhColaborador::class, 'rrhh_colaborador_id');
    }

    /**
     * @param  Builder<OrgPositionAssignment>  $query
     * @return Builder<OrgPositionAssignment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ends_on');
    }
}
