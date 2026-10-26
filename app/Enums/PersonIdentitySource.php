<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\CompanyAssociate;
use Illuminate\Database\Eloquent\Model;

/**
 * Las tres poblaciones donde puede estar registrada una persona. Una persona no puede estar
 * vigente en más de una a la vez (ni dos veces en la misma).
 *
 * @see \App\Support\Affiliations\PersonIdentity\PersonIdentityGuard
 */
enum PersonIdentitySource: string
{
    case Individual = 'INDIVIDUAL';
    case Corporate = 'CORPORATIVO';
    case Associate = 'NUEVOS_NEGOCIOS';

    public static function fromModel(Model $model): self
    {
        return match (true) {
            $model instanceof Affiliate => self::Individual,
            $model instanceof AffiliateCorporate => self::Corporate,
            $model instanceof CompanyAssociate => self::Associate,
            default => throw new \InvalidArgumentException('Modelo sin identidad de persona: '.$model::class),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Afiliado individual',
            self::Corporate => 'Afiliado corporativo',
            self::Associate => 'Asociado de Nuevos Negocios',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Corporate => 'Corporativo',
            self::Associate => 'Nuevos Negocios',
        };
    }

    /**
     * @return class-string<Affiliate|AffiliateCorporate|CompanyAssociate>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Individual => Affiliate::class,
            self::Corporate => AffiliateCorporate::class,
            self::Associate => CompanyAssociate::class,
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::Individual => 'affiliates',
            self::Corporate => 'affiliate_corporates',
            self::Associate => 'company_associates',
        };
    }

    public function documentColumn(): string
    {
        return $this === self::Associate ? 'identity_card' : 'nro_identificacion';
    }

    /**
     * Columnas que, al cambiar, obligan a volver a comprobar que la persona no esté duplicada.
     *
     * @return list<string>
     */
    public function watchedColumns(): array
    {
        return match ($this) {
            self::Individual => ['full_name', 'nro_identificacion', 'birth_date', 'status', 'affiliation_id'],
            self::Corporate => ['first_name', 'last_name', 'nro_identificacion', 'birth_date', 'status', 'affiliation_corporate_id'],
            self::Associate => ['full_name', 'identity_card', 'birth_date', 'status', 'flight_date', 'date_init', 'date_end'],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->shortLabel();
        }

        return $options;
    }
}
