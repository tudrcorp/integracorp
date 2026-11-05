<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\DataChangeAuditRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Un cambio auditado: qué registro, qué campos (antes → después), quién, cuándo y desde dónde.
 * Solo se escribe (lo llena {@see \App\Observers\DataChangeAuditObserver}); nunca se edita,
 * salvo la marca `reverted_by_audit_id` cuando el cambio se revierte.
 */
class DataChangeAudit extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_DELETED = 'deleted';

    public const EVENT_RESTORED = 'restored';

    public const EVENT_REVERTED = 'reverted';

    /** @var array<string, string> */
    public const EVENT_LABELS = [
        self::EVENT_CREATED => 'Creado',
        self::EVENT_UPDATED => 'Modificado',
        self::EVENT_DELETED => 'Eliminado',
        self::EVENT_RESTORED => 'Restaurado',
        self::EVENT_REVERTED => 'Revertido',
    ];

    /** Retención del historial. */
    public const RETENTION_MONTHS = 24;

    protected $table = 'data_change_audits';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'affects_calculations' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function entityLabel(): string
    {
        return DataChangeAuditRegistry::entityLabel((string) $this->entity);
    }

    public function eventLabel(): string
    {
        return self::EVENT_LABELS[$this->event] ?? (string) $this->event;
    }

    public function revertedBy(): ?self
    {
        return $this->reverted_by_audit_id !== null ? self::query()->find($this->reverted_by_audit_id) : null;
    }
}
