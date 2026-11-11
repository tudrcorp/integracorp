<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\RelationManagers;

use Filament\Tables\Table;

/**
 * La misma gestión de afiliados de la edición (acciones, permisos, recálculos y
 * auditoría idénticos) para la pantalla «Ver afiliados», sin el refresco
 * automático cada 5 s: en poblaciones grandes ese refresco reconsulta sin parar.
 */
class CorporateAffiliatesQuickRelationManager extends CorporateAffiliatesRelationManager
{
    public function table(Table $table): Table
    {
        return parent::table($table)->poll(null);
    }
}
