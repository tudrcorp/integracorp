<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PersonIdentityFindingKind;
use App\Enums\PersonIdentitySource;
use App\Support\Affiliations\PersonIdentity\PersonIdentityRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Caso del detector de personas duplicadas: dos registros que son la misma persona, o un registro
 * con el nombre cargado dos veces.
 *
 * Estados: ABIERTO → CORREGIDO (el analista corrigió o dio de baja desde el detector, con nota) |
 * NO_ES_DUPLICADO (el analista lo revisó, con nota; mientras los datos no cambien tampoco bloquea
 * el guardado) | RESUELTO (dejó de detectarse sin tocarlo aquí).
 *
 * @see \App\Support\Affiliations\PersonIdentity\PersonIdentityScanner
 * @see \App\Support\Affiliations\PersonIdentity\PersonIdentityResolver
 */
class PersonIdentityFinding extends Model
{
    public const STATUS_OPEN = 'ABIERTO';

    public const STATUS_CORRECTED = 'CORREGIDO';

    public const STATUS_DISMISSED = 'NO_ES_DUPLICADO';

    public const STATUS_RESOLVED = 'RESUELTO';

    protected $table = 'person_identity_findings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'severity',
        'status',
        'pair_key',
        'record_a_type',
        'record_a_id',
        'record_b_type',
        'record_b_id',
        'person_name',
        'details',
        'fingerprint',
        'dismissed_fingerprint',
        'first_detected_at',
        'last_detected_at',
        'resolved_at',
        'resolved_by',
        'resolved_by_name',
        'resolution_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Abierto',
            self::STATUS_CORRECTED => 'Corregido',
            self::STATUS_DISMISSED => 'No es duplicado',
            self::STATUS_RESOLVED => 'Resuelto solo',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_OPEN => 'danger',
            self::STATUS_CORRECTED => 'success',
            self::STATUS_DISMISSED => 'gray',
            default => 'info',
        };
    }

    /**
     * Clave única del caso: el tipo y los dos registros en orden fijo (A|B y B|A son el mismo caso).
     */
    public static function pairKey(PersonIdentityFindingKind $kind, PersonIdentityRecord $a, ?PersonIdentityRecord $b = null): string
    {
        $references = array_filter([$a->reference(), $b?->reference()]);
        sort($references, SORT_STRING);

        return $kind->value.'|'.implode('|', $references);
    }

    /**
     * Huella de los datos que el analista revisó. Si cambia nombre, cédula o nacimiento de cualquiera
     * de los dos, el caso marcado como «no es duplicado» se vuelve a abrir.
     */
    public static function fingerprintFor(PersonIdentityFindingKind $kind, PersonIdentityRecord $a, ?PersonIdentityRecord $b = null): string
    {
        $identities = [];

        foreach (array_filter([$a, $b]) as $record) {
            $identities[$record->reference()] = $record->reference().'='.$record->name.'#'.$record->document.'#'.$record->birthDate;
        }

        ksort($identities, SORT_STRING);

        return sha1($kind->value.'|'.implode('|', $identities));
    }

    public function kindEnum(): ?PersonIdentityFindingKind
    {
        return PersonIdentityFindingKind::tryFrom((string) $this->kind);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? (string) $this->status;
    }

    public function sourceA(): ?PersonIdentitySource
    {
        return PersonIdentitySource::tryFrom((string) $this->record_a_type);
    }

    public function sourceB(): ?PersonIdentitySource
    {
        return PersonIdentitySource::tryFrom((string) $this->record_b_type);
    }

    /**
     * Foto guardada de un lado del caso (`a` o `b`).
     *
     * @return array<string, mixed>
     */
    public function side(string $side): array
    {
        return (array) ($this->details[$side] ?? []);
    }

    /**
     * @return HasMany<PersonIdentityFindingEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PersonIdentityFindingEvent::class)->orderByDesc('id');
    }
}
