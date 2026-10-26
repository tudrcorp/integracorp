<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Deletion;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\Renovation;
use App\Models\RenovationCorporate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Lo que cambia entre eliminar una afiliación individual y una corporativa: modelos, llaves y textos.
 */
final class AffiliationDeletionSubject
{
    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_CORPORATE = 'corporativa';

    private function __construct(
        public readonly string $type,
        public readonly Affiliation|AffiliationCorporate $affiliation,
    ) {}

    public static function for(Model $affiliation): self
    {
        return match (true) {
            $affiliation instanceof Affiliation => new self(self::TYPE_INDIVIDUAL, $affiliation),
            $affiliation instanceof AffiliationCorporate => new self(self::TYPE_CORPORATE, $affiliation),
            default => throw new InvalidArgumentException('El registro no es una afiliación.'),
        };
    }

    /**
     * @return class-string<Affiliation|AffiliationCorporate>
     */
    public static function modelClassFor(string $type): string
    {
        return match ($type) {
            self::TYPE_INDIVIDUAL => Affiliation::class,
            self::TYPE_CORPORATE => AffiliationCorporate::class,
            default => throw new InvalidArgumentException('Tipo de afiliación desconocido: '.$type),
        };
    }

    public function isCorporate(): bool
    {
        return $this->type === self::TYPE_CORPORATE;
    }

    public function id(): int
    {
        return (int) $this->affiliation->getKey();
    }

    public function code(): string
    {
        return (string) $this->affiliation->code;
    }

    public function name(): string
    {
        $name = $this->isCorporate()
            ? (string) $this->affiliation->name_corporate
            : (string) ($this->affiliation->full_name_ti ?: $this->affiliation->full_name_payer);

        return trim($name) !== '' ? trim($name) : 'Sin nombre';
    }

    /**
     * «la afiliación corporativa TDEC-COR-00057 (EMPRESA)».
     */
    public function label(): string
    {
        return 'la afiliación '.$this->type.' '.$this->code().' ('.$this->name().')';
    }

    /**
     * Afiliados de la afiliación. Con `$withTrashed`, también los ocultos (para restaurar).
     *
     * @return Builder<Affiliate|AffiliateCorporate>
     */
    public function affiliatesQuery(bool $withTrashed = false): Builder
    {
        $query = $this->isCorporate()
            ? AffiliateCorporate::query()->where('affiliation_corporate_id', $this->id())
            : Affiliate::query()->where('affiliation_id', $this->id());

        return $withTrashed ? $query->withTrashed() : $query;
    }

    /**
     * @return Builder<PaidMembership|PaidMembershipCorporate>
     */
    public function paymentsQuery(): Builder
    {
        return $this->isCorporate()
            ? PaidMembershipCorporate::query()->where('affiliation_corporate_id', $this->id())
            : PaidMembership::query()->where('affiliation_id', $this->id());
    }

    /**
     * @return Builder<Renovation|RenovationCorporate>
     */
    public function renovationsQuery(): Builder
    {
        return $this->isCorporate()
            ? RenovationCorporate::query()->where('affiliation_corporate_id', $this->id())
            : Renovation::query()->where('affiliation_id', $this->id());
    }

    public static function affiliateName(Affiliate|AffiliateCorporate $affiliate): string
    {
        if ($affiliate instanceof AffiliateCorporate) {
            $first = trim((string) $affiliate->first_name);
            $last = trim((string) $affiliate->last_name, ' .');

            return trim($last === '' || $first === $last ? $first : $first.' '.$last) ?: 'Afiliado #'.$affiliate->getKey();
        }

        return trim((string) $affiliate->full_name) ?: 'Afiliado #'.$affiliate->getKey();
    }
}
