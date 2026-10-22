<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrgPositionKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un puesto dentro de una versión del organigrama. El puesto existe aunque nadie
 * lo ocupe (vacante); quién lo ocupa vive en OrgPositionAssignment.
 *
 * @property int $id
 * @property int $version_id
 * @property string $position_key
 * @property int|null $parent_id
 * @property int|null $rrhh_cargo_id
 * @property int|null $rrhh_departamento_id
 * @property string $title
 * @property OrgPositionKind $kind
 * @property int $seats
 * @property int $sort_order
 * @property string|null $color
 */
class OrgPosition extends Model
{
    use HasFactory;

    protected $table = 'org_positions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'version_id',
        'position_key',
        'parent_id',
        'rrhh_cargo_id',
        'rrhh_departamento_id',
        'title',
        'kind',
        'seats',
        'sort_order',
        'color',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => OrgPositionKind::class,
            'seats' => 'integer',
            'sort_order' => 'integer',
            'parent_id' => 'integer',
            'rrhh_cargo_id' => 'integer',
            'rrhh_departamento_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OrgStructureVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(OrgStructureVersion::class, 'version_id');
    }

    /**
     * @return BelongsTo<OrgPosition, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<OrgPosition, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<OrgPositionAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(OrgPositionAssignment::class, 'position_id');
    }

    /**
     * @return HasMany<OrgPositionAssignment, $this>
     */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('ends_on');
    }

    /**
     * @return BelongsTo<RrhhCargo, $this>
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(RrhhCargo::class, 'rrhh_cargo_id');
    }

    /**
     * @return BelongsTo<RrhhDepartamento, $this>
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(RrhhDepartamento::class, 'rrhh_departamento_id');
    }
}
