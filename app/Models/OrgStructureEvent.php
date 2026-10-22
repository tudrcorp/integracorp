<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de la estructura organizacional: cada cambio con su antes y después.
 * Solo se inserta.
 */
class OrgStructureEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'org_structure_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'version_id',
        'action',
        'position_key',
        'summary',
        'before',
        'after',
        'user_id',
        'user_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrgStructureVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(OrgStructureVersion::class, 'version_id');
    }
}
