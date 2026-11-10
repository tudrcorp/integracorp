<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemOtpCodeKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Copia consultable de una clave OTP emitida (población corporativa, servicio
 * fuera de cupo y acceso a cupos clínicos), para que un SUPERADMIN pueda
 * dictarla si el WhatsApp o el correo no llegan.
 *
 * La clave se guarda cifrada y la fila se borra a las {@see self::RETENTION_HOURS}
 * horas. No interviene en la validación: esa sigue contra el hash del reto.
 *
 * @property SystemOtpCodeKind $kind
 * @property string $code
 */
class SystemOtpCode extends Model
{
    use MassPrunable;

    public const RETENTION_HOURS = 24;

    public const STATUS_ACTIVE = 'VIGENTE';

    public const STATUS_USED = 'USADA';

    public const STATUS_EXPIRED = 'VENCIDA';

    public const STATUS_REPLACED = 'REEMPLAZADA';

    public const STATUS_CANCELLED = 'ANULADA';

    public const STATUS_LOCKED = 'INTENTOS AGOTADOS';

    public const STATUS_UNDELIVERED = 'NO ENTREGADA';

    protected $fillable = [
        'kind',
        'challenge_type',
        'challenge_id',
        'code',
        'requested_by_user_id',
        'requested_by_name',
        'subject',
        'details',
        'emails_sent',
        'phones_sent',
        'expires_at',
        'used_at',
    ];

    protected $hidden = ['code'];

    protected function casts(): array
    {
        return [
            'kind' => SystemOtpCodeKind::class,
            'code' => 'encrypted',
            'details' => 'array',
            'emails_sent' => 'integer',
            'phones_sent' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function challenge(): MorphTo
    {
        return $this->morphTo('challenge', 'challenge_type', 'challenge_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * Solo lo de las últimas 24 horas, aunque la limpieza programada se atrase.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithinRetention(Builder $query): void
    {
        $query->where($this->qualifyColumn('created_at'), '>', now()->subHours(self::RETENTION_HOURS));
    }

    /**
     * Marca si después de esta clave se emitió otra para el mismo reto (reenvío).
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithReplacedFlag(Builder $query): void
    {
        $table = $this->getTable();

        if ($query->getQuery()->columns === null) {
            $query->select($table.'.*');
        }

        $query->selectRaw(
            'exists (select 1 from '.$table.' as newer where newer.challenge_type = '.$table.'.challenge_type'
            .' and newer.challenge_id = '.$table.'.challenge_id and newer.id > '.$table.'.id) as is_replaced'
        );
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subHours(self::RETENTION_HOURS));
    }

    /**
     * Estado de la clave según su reto. Requiere `challenge` cargado y, para
     * detectar reenvíos, el atributo `is_replaced` ({@see scopeWithReplacedFlag}).
     */
    public function status(): string
    {
        if ($this->used_at !== null) {
            return self::STATUS_USED;
        }

        if ((int) $this->emails_sent === 0 && (int) $this->phones_sent === 0) {
            return self::STATUS_UNDELIVERED;
        }

        if ((bool) ($this->getAttribute('is_replaced') ?? false)) {
            return self::STATUS_REPLACED;
        }

        $challenge = $this->relationLoaded('challenge') ? $this->getRelation('challenge') : $this->challenge;

        if ($challenge === null || $challenge->getAttribute('consumed_at') !== null) {
            return self::STATUS_CANCELLED;
        }

        $maxAttempts = (int) ($challenge->getAttribute('max_attempts') ?? 0);

        if ($maxAttempts > 0 && (int) $challenge->getAttribute('attempt_count') >= $maxAttempts) {
            return self::STATUS_LOCKED;
        }

        if ($this->expires_at === null || $this->expires_at->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_USED => 'info',
            self::STATUS_UNDELIVERED, self::STATUS_LOCKED => 'danger',
            self::STATUS_REPLACED, self::STATUS_CANCELLED => 'gray',
            default => 'warning',
        };
    }

    public static function statusHint(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => 'Todavía sirve: puede dictársela a quien la pidió.',
            self::STATUS_USED => 'Ya se usó para aprobar la operación.',
            self::STATUS_EXPIRED => 'Pasaron los minutos de validez. Quien la pidió debe pulsar «Reenviar clave».',
            self::STATUS_REPLACED => 'Se pidió un reenvío: vale la clave más reciente de la misma solicitud.',
            self::STATUS_CANCELLED => 'Quedó sin efecto: se pidió una solicitud nueva o se canceló.',
            self::STATUS_LOCKED => 'Se agotaron los intentos. Quien la pidió debe solicitar una nueva.',
            self::STATUS_UNDELIVERED => 'No salió ni por correo ni por WhatsApp; el sistema la invalidó.',
            default => '',
        };
    }
}
