<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Traza y punto de restauración de la eliminación de una afiliación (individual o corporativa).
 *
 * `snapshot` guarda exactamente lo que se cambió (afiliados ocultados, cuotas anuladas con su
 * ajuste, pacientes desvinculados con sus datos de antes y de después, casos cerrados ocultados)
 * para que la restauración devuelva todo a como estaba. `summary` y `restoration_summary` son las
 * frases que vio el analista.
 *
 * @see \App\Support\Affiliations\Deletion\AffiliationDeletionService
 */
class AffiliationDeletion extends Model
{
    public const STATUS_DELETED = 'ELIMINADA';

    public const STATUS_RESTORED = 'RESTAURADA';

    protected $table = 'affiliation_deletions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'affiliation_type',
        'affiliation_id',
        'affiliation_code',
        'affiliation_name',
        'status',
        'reason',
        'snapshot',
        'summary',
        'deleted_by',
        'deleted_by_name',
        'deleted_at_moment',
        'restoration_reason',
        'restoration_summary',
        'restored_by',
        'restored_by_name',
        'restored_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'summary' => 'array',
            'restoration_summary' => 'array',
            'deleted_at_moment' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }
}
