<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProspectAgent extends CreateRecord
{
    protected static string $resource = ProspectAgentResource::class;

    protected static ?string $title = 'Formulario de Prospectos';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
