<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TdevAgency extends Model
{
    public const LEVEL_TWO = 2;

    public const LEVEL_THREE = 3;

    public const DEFAULT_LANDING_SLOGAN_LINE_1 = 'Seguros de viaje con respaldo y confianza.';

    public const DEFAULT_LANDING_SLOGAN_LINE_2 = 'Protección integral para tu red comercial.';

    protected $fillable = [
        'level',
        'parent_id',
        'name',
        'identification_number',
        'email',
        'anniversary_date',
        'representative_name',
        'representative_birth_date',
        'phone',
        'phone_additional',
        'instagram_username',
        'country_id',
        'state_id',
        'city_id',
        'address',
        'logo',
        'url',
        'landing_slogan_line_1',
        'landing_slogan_line_2',
        'registration_token',
        'agency_registration_token',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (TdevAgency $agency): void {
            if (blank($agency->level)) {
                $agency->level = self::LEVEL_TWO;
            }

            if (blank($agency->registration_token)) {
                $agency->registration_token = (string) Str::uuid();
            }

            if ((int) $agency->level === self::LEVEL_TWO && blank($agency->agency_registration_token)) {
                $agency->agency_registration_token = (string) Str::uuid();
            }

            if ((int) $agency->level === self::LEVEL_TWO) {
                if (blank($agency->landing_slogan_line_1)) {
                    $agency->landing_slogan_line_1 = self::DEFAULT_LANDING_SLOGAN_LINE_1;
                }

                if (blank($agency->landing_slogan_line_2)) {
                    $agency->landing_slogan_line_2 = self::DEFAULT_LANDING_SLOGAN_LINE_2;
                }
            }
        });
    }

    public function resolvedLandingSloganLine1(): string
    {
        return filled($this->landing_slogan_line_1)
            ? (string) $this->landing_slogan_line_1
            : self::DEFAULT_LANDING_SLOGAN_LINE_1;
    }

    public function resolvedLandingSloganLine2(): string
    {
        return filled($this->landing_slogan_line_2)
            ? (string) $this->landing_slogan_line_2
            : self::DEFAULT_LANDING_SLOGAN_LINE_2;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'anniversary_date' => 'date',
            'representative_birth_date' => 'date',
        ];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(TdevAgent::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function childAgencies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function parentAgency(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function isLevelTwo(): bool
    {
        return (int) $this->level === self::LEVEL_TWO;
    }

    public function isLevelThree(): bool
    {
        return (int) $this->level === self::LEVEL_THREE;
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo)) {
            return null;
        }

        return asset('storage/'.$this->logo);
    }

    /**
     * @param  Builder<TdevAgency>  $query
     * @return Builder<TdevAgency>
     */
    public function scopeLevelTwo(Builder $query): Builder
    {
        return $query->where('level', self::LEVEL_TWO);
    }

    /**
     * @param  Builder<TdevAgency>  $query
     * @return Builder<TdevAgency>
     */
    public function scopeLevelThree(Builder $query): Builder
    {
        return $query->where('level', self::LEVEL_THREE);
    }
}
