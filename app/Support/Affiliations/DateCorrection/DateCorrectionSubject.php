<?php

declare(strict_types=1);

namespace App\Support\Affiliations\DateCorrection;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Qué tablas tocan la corrección de vigencia para cada tipo de afiliación. La regla es la
 * misma; cambian los nombres de las tablas, las llaves y las relaciones de bitácora.
 */
final class DateCorrectionSubject
{
    public const TYPE_CORPORATE = 'CORPORATIVA';

    public const TYPE_INDIVIDUAL = 'INDIVIDUAL';

    private function __construct(
        public readonly string $type,
        public readonly string $affiliationTable,
        public readonly string $paymentsTable,
        public readonly string $paymentsForeignKey,
        public readonly string $renovationsTable,
        public readonly string $renovationsForeignKey,
        public readonly string $historiesTable,
        public readonly string $historiesForeignKey,
        public readonly string $usagesForeignKey,
        public readonly string $statusLogRelation,
        public readonly string $statusLogForeignKey,
        public readonly string $observationsRelation,
        public readonly string $permissionSlug,
        public readonly string $label,
    ) {}

    public static function corporate(): self
    {
        return new self(
            type: self::TYPE_CORPORATE,
            affiliationTable: 'affiliation_corporates',
            paymentsTable: 'paid_membership_corporates',
            paymentsForeignKey: 'affiliation_corporate_id',
            renovationsTable: 'renovation_corporates',
            renovationsForeignKey: 'affiliation_corporate_id',
            historiesTable: 'affiliation_corporate_renovation_histories',
            historiesForeignKey: 'affiliation_corporate_id',
            usagesForeignKey: 'affiliation_corporate_id',
            statusLogRelation: 'status_log_corporate_affiliations',
            statusLogForeignKey: 'affiliation_corporate_id',
            observationsRelation: 'affiliationCorporateObservations',
            permissionSlug: BusinessFilamentActionPermissionRegistry::CORRECT_CORPORATE_EFFECTIVE_DATE,
            label: 'afiliación corporativa',
        );
    }

    public static function individual(): self
    {
        return new self(
            type: self::TYPE_INDIVIDUAL,
            affiliationTable: 'affiliations',
            paymentsTable: 'paid_memberships',
            paymentsForeignKey: 'affiliation_id',
            renovationsTable: 'renovations',
            renovationsForeignKey: 'affiliation_id',
            historiesTable: 'affiliation_renovation_histories',
            historiesForeignKey: 'affiliation_id',
            usagesForeignKey: 'affiliation_id',
            statusLogRelation: 'status_log_affiliations',
            statusLogForeignKey: 'affiliation_id',
            observationsRelation: 'affiliationObservations',
            permissionSlug: BusinessFilamentActionPermissionRegistry::CORRECT_INDIVIDUAL_EFFECTIVE_DATE,
            label: 'afiliación individual',
        );
    }

    public static function for(Model $affiliation): self
    {
        return match (true) {
            $affiliation instanceof AffiliationCorporate => self::corporate(),
            $affiliation instanceof Affiliation => self::individual(),
            default => throw new InvalidArgumentException('Solo se corrige la vigencia de afiliaciones individuales o corporativas.'),
        };
    }

    public static function forType(string $type): self
    {
        return $type === self::TYPE_CORPORATE ? self::corporate() : self::individual();
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return $this->type === self::TYPE_CORPORATE ? AffiliationCorporate::class : Affiliation::class;
    }

    /**
     * Columnas que la corrección puede modificar, por tabla. Nada fuera de esta lista se toca.
     *
     * @return array<string, list<string>>
     */
    public function columns(): array
    {
        return [
            $this->affiliationTable => ['activated_at', 'effective_date'],
            'collections' => ['include_date', 'next_payment_date', 'filter_next_payment_date', 'expiration_date', 'status'],
            'annual_collections' => [
                'next_payment_date', 'filter_next_payment_date', 'expiration_date',
                'month_1', 'month_2', 'month_3', 'month_4', 'month_5', 'month_6',
                'month_7', 'month_8', 'month_9', 'month_10', 'month_11', 'month_12',
            ],
            $this->renovationsTable => ['date_renewal', 'remaining_days', 'status', 'updated_by'],
            $this->paymentsTable => ['prox_payment_date', 'renewal_date'],
        ];
    }

    /**
     * Nombre legible de cada tabla, para los mensajes de conflicto.
     *
     * @return array<string, string>
     */
    public function tableLabels(): array
    {
        return [
            $this->affiliationTable => 'Afiliación',
            'collections' => 'Aviso',
            'annual_collections' => 'Planificación anual',
            $this->renovationsTable => 'Renovación',
            $this->paymentsTable => 'Pago',
        ];
    }
}
