<?php

declare(strict_types=1);

namespace App\Support\Business\CapitationPlans;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\CompanyAssociate;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Los tres tipos de afiliado que pueden llevar un ajuste de capitación, con su clave estable.
 */
final class CapitationSubject
{
    public const AFFILIATE = 'affiliate';

    public const AFFILIATE_CORPORATE = 'affiliate_corporate';

    public const COMPANY_ASSOCIATE = 'company_associate';

    public static function type(Model $record): string
    {
        return match (true) {
            $record instanceof Affiliate => self::AFFILIATE,
            $record instanceof AffiliateCorporate => self::AFFILIATE_CORPORATE,
            $record instanceof CompanyAssociate => self::COMPANY_ASSOCIATE,
            default => throw new InvalidArgumentException('Tipo de afiliado no soportado: '.$record::class),
        };
    }

    public static function key(Model $record): string
    {
        return self::type($record).':'.$record->getKey();
    }

    /**
     * Relaciones que el cálculo necesita, para precargarlas en lote.
     *
     * @return list<string>
     */
    public static function relationsFor(string $type): array
    {
        return match ($type) {
            self::AFFILIATE => ['affiliation'],
            self::AFFILIATE_CORPORATE => ['affiliationCorporate'],
            self::COMPANY_ASSOCIATE => ['company.planGenerator:id,plan_id,catalog_plan_id'],
            default => [],
        };
    }

    public static function label(Model $record): string
    {
        $name = match (true) {
            $record instanceof AffiliateCorporate => trim($record->first_name.' '.$record->last_name),
            default => (string) ($record->getAttribute('full_name') ?? ''),
        };

        return $name !== '' ? $name : 'Afiliado #'.$record->getKey();
    }

    public static function resolve(Model $record, bool $applyOverride = true): CapitationCostResult
    {
        return match (true) {
            $record instanceof Affiliate => AffiliateCapitationCost::forAffiliate($record, applyOverride: $applyOverride),
            $record instanceof AffiliateCorporate => AffiliateCapitationCost::forAffiliateCorporate($record, applyOverride: $applyOverride),
            $record instanceof CompanyAssociate => AffiliateCapitationCost::forCompanyAssociate($record, applyOverride: $applyOverride),
            default => throw new InvalidArgumentException('Tipo de afiliado no soportado: '.$record::class),
        };
    }
}
