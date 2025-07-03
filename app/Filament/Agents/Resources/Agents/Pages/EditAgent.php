<?php

namespace App\Filament\Agents\Resources\Agents\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Agents\Resources\Agents\AgentResource;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    public function getTitle(): string | Htmlable
    {
        $name = $this->record->name;
        return 'Perfil del Agente: ' . $name;
    }

}