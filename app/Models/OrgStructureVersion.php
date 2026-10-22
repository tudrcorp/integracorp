<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrgStructureVersionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una versión del organigrama de la empresa: la vigente, un borrador en estudio o
 * una versión archivada del historial.
 *
 * @property int $id
 * @property string $name
 * @property OrgStructureVersionStatus $status
 * @property int|null $based_on_version_id
 * @property int|null $current_marker
 * @property int $lock_version
 * @property string|null $notes
 * @property string|null $review_notes
 * @property int|null $submitted_by
 * @property int|null $reviewed_by
 * @property int|null $published_by
 * @property int|null $created_by
 * @property int|null $updated_by
 *
 * @see \App\Support\OrgStructure\OrgStructureEditor
 * @see \App\Support\OrgStructure\OrgStructurePublisher
 */
class OrgStructureVersion extends Model
{
    use HasFactory;

    protected $table = 'org_structure_versions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'status',
        'based_on_version_id',
        'current_marker',
        'lock_version',
        'notes',
        'review_notes',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'published_by',
        'published_at',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrgStructureVersionStatus::class,
            'lock_version' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrgPosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(OrgPosition::class, 'version_id');
    }

    /**
     * @return HasMany<OrgPositionAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(OrgPositionAssignment::class, 'version_id');
    }

    /**
     * @return HasMany<OrgStructureEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrgStructureEvent::class, 'version_id');
    }

    /**
     * @return BelongsTo<OrgStructureVersion, $this>
     */
    public function basedOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'based_on_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @param  Builder<OrgStructureVersion>  $query
     * @return Builder<OrgStructureVersion>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('current_marker', 1);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
