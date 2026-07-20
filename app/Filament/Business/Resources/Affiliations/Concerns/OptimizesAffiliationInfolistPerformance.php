<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Concerns;

use App\Filament\Business\Resources\Affiliations\Support\AffiliationInfolistTab;
use Illuminate\Database\Eloquent\Model;

/**
 * Carga ligera al abrir la vista + render de un solo tab vía Livewire.
 * Las relaciones HasMany pesadas solo se hidratan cuando el analista abre ese tab.
 */
trait OptimizesAffiliationInfolistPerformance
{
    public string $affiliationInfolistTab = AffiliationInfolistTab::RESUMEN;

    /**
     * Relaciones BelongsTo baratas (1 fila). Seguras de cargar siempre.
     *
     * @return list<string>
     */
    protected function affiliationLightRelations(): array
    {
        return [
            'agent',
            'individual_quote',
            'plan',
            'coverage',
        ];
    }

    /**
     * Relaciones HasMany / anidadas por tab.
     *
     * @return array<string, list<string>>
     */
    protected function affiliationHeavyRelationsByTab(): array
    {
        return [
            AffiliationInfolistTab::AFILIADOS => [
                'affiliates.businessLine:id,definition',
                'affiliates.businessUnit:id,definition',
            ],
            AffiliationInfolistTab::ALIADO_2 => [
                'affiliates.businessLine:id,definition',
                'affiliates.businessUnit:id,definition',
            ],
            AffiliationInfolistTab::RENOVACIONES => [
                'renovationHistories.plan',
                'renovationHistories.previousPlan',
                'renovationHistories.coverage',
                'renovationHistories.affiliate',
            ],
            AffiliationInfolistTab::EXPEDIENTE => [
                'affiliationDocuments',
            ],
            AffiliationInfolistTab::OBSERVACIONES => [
                'affiliationObservations.createdBy:id,name,email',
            ],
        ];
    }

    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);

        $record->load($this->affiliationLightRelations());
        $record->loadCount('renovationHistories');
        $this->ensureAffiliationInfolistRelationsLoaded($record, $this->affiliationInfolistTab);

        return $record;
    }

    public function updatedAffiliationInfolistTab(?string $value): void
    {
        $tab = is_string($value) && $value !== ''
            ? $value
            : AffiliationInfolistTab::RESUMEN;

        $this->ensureAffiliationInfolistRelationsLoaded($this->getRecord(), $tab);
    }

    protected function ensureAffiliationInfolistRelationsLoaded(Model $record, string $tab): void
    {
        $relations = $this->affiliationHeavyRelationsByTab()[$tab] ?? [];

        if ($relations === []) {
            return;
        }

        $record->loadMissing($relations);
    }
}
