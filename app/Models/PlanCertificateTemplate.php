<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versión del diseño HTML de la hoja del certificado de un plan. Solo una por
 * plan está ACTIVO; las emisiones guardan la versión con que se generaron.
 */
class PlanCertificateTemplate extends Model
{
    public const STATUS_ACTIVE = 'ACTIVO';

    public const STATUS_INACTIVE = 'INACTIVO';

    protected $table = 'plan_certificate_templates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'version',
        'status',
        'html',
        'original_name',
        'original_sha1',
        'removed',
        'page_count',
        'notes',
        'created_by',
        'created_by_name',
        'activated_at',
        'deactivated_at',
        'deactivated_by_name',
        'deactivation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'removed' => 'array',
            'page_count' => 'integer',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
