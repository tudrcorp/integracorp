<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApiPartnerAbility;
use App\Support\ApiPartners\ApiPartnerToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Aliado externo con acceso por API a datos de gestión. Ver {@see ApiPartnerToken}.
 *
 * La columna `allowed_ips` existe en la tabla pero no se usa: el acceso es solo por token.
 *
 * @property list<string>|null $abilities
 */
class ApiPartner extends Model
{
    public const STATUS_ACTIVE = 'ACTIVO';

    public const STATUS_INACTIVE = 'INACTIVO';

    public const STATUS_EXPIRED = 'VENCIDO';

    public const STATUS_WITHOUT_TOKEN = 'SIN TOKEN';

    public const MIN_RATE_LIMIT = 1;

    public const MAX_RATE_LIMIT = 600;

    protected $table = 'api_partners';

    protected $fillable = [
        'name',
        'contact_name',
        'contact_email',
        'supplier_id',
        'abilities',
        'rate_limit_per_minute',
        'is_active',
        'expires_at',
        'notes',
    ];

    /** El hash del token nunca sale en arrays/JSON del modelo. */
    protected $hidden = [
        'token_hash',
    ];

    private static ?bool $tablesExist = null;

    private static ?bool $supplierColumnExists = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supplier_id' => 'integer',
            'abilities' => 'array',
            'rate_limit_per_minute' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'token_issued_at' => 'datetime',
            'token_revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ApiPartner $partner): void {
            $partner->abilities = self::normalizeAbilities($partner->abilities);
            $partner->rate_limit_per_minute = max(
                self::MIN_RATE_LIMIT,
                min(self::MAX_RATE_LIMIT, (int) ($partner->rate_limit_per_minute ?: 60)),
            );
        });

        static::creating(function (ApiPartner $partner): void {
            $partner->created_by ??= Auth::id();
            $partner->updated_by ??= Auth::id();
        });

        static::updating(function (ApiPartner $partner): void {
            if (Auth::id() !== null) {
                $partner->updated_by = Auth::id();
            }
        });
    }

    /**
     * Las tablas existen hasta que se aplica la migración; antes, el API y la pantalla se apagan solos.
     */
    public static function tablesExist(): bool
    {
        return self::$tablesExist ??= Schema::hasTable('api_partners') && Schema::hasTable('api_partner_access_logs');
    }

    /**
     * El vínculo con Proveedores jurídicos llega con su propia migración; hasta aplicarla, el campo no se muestra.
     */
    public static function supplierColumnExists(): bool
    {
        return self::$supplierColumnExists ??= self::tablesExist() && Schema::hasColumn('api_partners', 'supplier_id');
    }

    /**
     * @param  array<int|string, mixed>|null  $abilities
     * @return list<string>
     */
    public static function normalizeAbilities(?array $abilities): array
    {
        $valid = array_map(fn (ApiPartnerAbility $ability): string => $ability->value, ApiPartnerAbility::cases());

        return array_values(array_unique(array_filter(
            $abilities ?? [],
            fn (mixed $ability): bool => is_string($ability) && in_array($ability, $valid, true),
        )));
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ApiPartnerAccessLog::class);
    }

    /**
     * Su registro en Proveedores jurídicos. Solo referencia: el acceso al API no depende de él.
     * Incluye proveedores eliminados para no perder de vista con quién se vinculó.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tokenIssuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'token_issued_by');
    }

    public function hasAbility(ApiPartnerAbility $ability): bool
    {
        return in_array($ability->value, $this->abilities ?? [], true);
    }

    public function hasToken(): bool
    {
        return filled($this->token_prefix) && filled($this->token_hash);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function status(): string
    {
        return match (true) {
            ! $this->is_active => self::STATUS_INACTIVE,
            $this->isExpired() => self::STATUS_EXPIRED,
            ! $this->hasToken() => self::STATUS_WITHOUT_TOKEN,
            default => self::STATUS_ACTIVE,
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_WITHOUT_TOKEN => 'warning',
            default => 'danger',
        };
    }

    /**
     * Genera un token nuevo (el anterior deja de servir en el acto) y devuelve el token en claro.
     */
    public function issueToken(): string
    {
        $generated = ApiPartnerToken::generate();

        $this->forceFill([
            'token_prefix' => $generated['prefix'],
            'token_hash' => $generated['hash'],
            'token_issued_at' => now(),
            'token_issued_by' => Auth::id(),
            'token_revoked_at' => null,
        ])->save();

        return $generated['token'];
    }

    public function revokeToken(): void
    {
        $this->forceFill([
            'token_prefix' => null,
            'token_hash' => null,
            'token_revoked_at' => now(),
        ])->save();
    }
}
