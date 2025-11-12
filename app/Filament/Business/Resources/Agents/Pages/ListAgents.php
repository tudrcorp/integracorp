<?php

namespace App\Filament\Business\Resources\Agents\Pages;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Resources\Pages\ListRecords;
use App\Http\Controllers\NotificationController;
use App\Filament\Business\Resources\Agents\AgentResource;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Gestión de Agentes';  

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear agente')
                ->icon('heroicon-s-user-plus')
                ->color('success'),
        ];
    }
}