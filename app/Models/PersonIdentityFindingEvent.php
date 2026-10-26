<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paso en la vida de un caso de personas duplicadas (bitácora inmutable).
 */
class PersonIdentityFindingEvent extends Model
{
    public const DETECTED = 'DETECTADO';

    public const CORRECTED = 'DATOS_CORREGIDOS';

    public const CORRECTION_REVERTED = 'CORRECCION_REVERTIDA';

    public const DEACTIVATED = 'DADO_DE_BAJA';

    public const DISMISSED = 'NO_ES_DUPLICADO';

    public const REOPENED = 'REABIERTO';

    public const RESOLVED = 'RESUELTO_SOLO';

    protected $table = 'person_identity_finding_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_identity_finding_id',
        'action',
        'note',
        'payload',
        'user_id',
        'user_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            self::DETECTED => 'Detectado',
            self::CORRECTED => 'Datos corregidos',
            self::CORRECTION_REVERTED => 'Corrección revertida',
            self::DEACTIVATED => 'Registro dado de baja',
            self::DISMISSED => 'Marcado como no duplicado',
            self::REOPENED => 'Reabierto',
            self::RESOLVED => 'Dejó de detectarse',
        ];
    }

    /**
     * @return BelongsTo<PersonIdentityFinding, $this>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(PersonIdentityFinding::class, 'person_identity_finding_id');
    }
}
