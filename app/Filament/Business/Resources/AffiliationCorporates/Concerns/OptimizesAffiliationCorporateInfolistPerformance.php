<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Concerns;

use App\Filament\Business\Resources\AffiliationCorporates\Support\AffiliationCorporateInfolistTab;
use Illuminate\Database\Eloquent\Model;

/**
 * Carga ligera al abrir la vista + render de un solo tab vía Livewire.
 * Las relaciones HasMany pesadas solo se hidratan cuando el analista abre ese tab.
 */
trait OptimizesAffiliationCorporateInfolistPerformance
{
    public string $affiliationCorporateInfolistTab = AffiliationCorporateInfolistTab::AFILIACION;

    /**
     * Relaciones BelongsTo baratas (1 fila). Seguras de cargar siempre.
     *
     * @return list<string>
     */
    protected function affiliationCorporateLightRelations(): array
    {
        return [
            'agent',
            'agency',
            'country',
            'state',
            'city',
            'region',
            'corporate_quote',
            'accountManager',
            'businessUnit',
            'businessLine',
        ];
    }

    /**
     * Relaciones HasMany / anidadas por tab. Nunca incluye corporateAffiliates.
     *
     * @return array<string, list<string>>
     */
    protected function affiliationCorporateHeavyRelationsByTab(): array
    {
        return [
            AffiliationCorporateInfolistTab::PLANES => [
                'affiliationCorporatePlans.plan',
                'affiliationCorporatePlans.coverage',
                'affiliationCorporatePlans.ageRange',
            ],
            AffiliationCorporateInfolistTab::EXPEDIENTE => [
                'affiliationCorporateDocuments',
            ],
            AffiliationCorporateInfolistTab::OBSERVACIONES => [
                'affiliationCorporateObservations.createdBy:id,name,email',
            ],
        ];
    }

    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);

        $record->load($this->affiliationCorporateLightRelations());
        $this->ensureAffiliationCorporateInfolistRelationsLoaded($record, $this->affiliationCorporateInfolistTab);

        return $record;
    }

    public function updatedAffiliationCorporateInfolistTab(?string $value): void
    {
        $tab = is_string($value) && $value !== ''
            ? $value
            : AffiliationCorporateInfolistTab::AFILIACION;

        $this->ensureAffiliationCorporateInfolistRelationsLoaded($this->getRecord(), $tab);
    }

    protected function ensureAffiliationCorporateInfolistRelationsLoaded(Model $record, string $tab): void
    {
        $relations = $this->affiliationCorporateHeavyRelationsByTab()[$tab] ?? [];

        if ($relations === []) {
            return;
        }

        $missing = array_values(array_filter(
            $relations,
            function (string $relation) use ($record): bool {
                $root = explode('.', $relation)[0];

                return ! $record->relationLoaded($root);
            },
        ));

        if ($missing === []) {
            return;
        }

        $record->load($missing);
    }
}
