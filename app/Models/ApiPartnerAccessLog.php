<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una petición al API de aliados: quién, desde dónde, qué pidió y qué se respondió.
 * Solo se escribe; no se edita.
 */
class ApiPartnerAccessLog extends Model
{
    public const OUTCOME_OK = 'OK';

    public const OUTCOME_MISSING_TOKEN = 'SIN_TOKEN';

    public const OUTCOME_INVALID_TOKEN = 'TOKEN_INVALIDO';

    public const OUTCOME_INACTIVE = 'ALIADO_INACTIVO';

    public const OUTCOME_EXPIRED = 'ALIADO_VENCIDO';

    public const OUTCOME_FORBIDDEN = 'SIN_PERMISO';

    public const OUTCOME_INSECURE = 'SIN_HTTPS';

    public const OUTCOME_THROTTLED = 'LIMITE_EXCEDIDO';

    public const OUTCOME_ERROR = 'ERROR';

    public const UPDATED_AT = null;

    protected $table = 'api_partner_access_logs';

    protected $fillable = [
        'api_partner_id',
        'method',
        'path',
        'ip',
        'user_agent',
        'status_code',
        'outcome',
        'query',
        'records_count',
        'duration_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query' => 'array',
            'status_code' => 'integer',
            'records_count' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function apiPartner(): BelongsTo
    {
        return $this->belongsTo(ApiPartner::class);
    }

    /**
     * @return array<string, string>
     */
    public static function outcomeLabels(): array
    {
        return [
            self::OUTCOME_OK => 'Respondida',
            self::OUTCOME_MISSING_TOKEN => 'Sin token',
            self::OUTCOME_INVALID_TOKEN => 'Token inválido',
            self::OUTCOME_INACTIVE => 'Aliado inactivo',
            self::OUTCOME_EXPIRED => 'Acceso vencido',
            self::OUTCOME_FORBIDDEN => 'Sin permiso',
            self::OUTCOME_INSECURE => 'Sin HTTPS',
            self::OUTCOME_THROTTLED => 'Límite excedido',
            self::OUTCOME_ERROR => 'Error',
        ];
    }
}
