<?php

namespace App\Filament\Marketing\Resources\MassNotifications\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Marketing\Resources\Agents\AgentResource;
use App\Filament\Marketing\Resources\Agencies\AgencyResource;
use App\Filament\Marketing\Resources\Affiliations\AffiliationResource;
use App\Filament\Marketing\Resources\MassNotifications\MassNotificationResource;
use App\Filament\Marketing\Resources\AffiliationCorporates\AffiliationCorporateResource;

class DataNotificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'dataNotifications';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Data asociada a la notificación')
            ->columns([
                TextColumn::make('fullName')->label('Full Name'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('phone')->label('Phone'),
            ])
            ->headerActions([
                Action::make('add_agency')
                    ->label('Agencias')
                    ->color('warning')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => AgencyResource::getUrl('index')),
                Action::make('add_agents')
                    ->label('Agentes')
                    ->color('warning')
                    ->icon('heroicon-o-plus')
                    ->url(fn() => AgentResource::getUrl('index')),
                Action::make('add_corporatives')
                    ->label('Corporativos')
                    ->color('success')
                    ->icon('heroicon-o-plus')
                    ->url(fn() => AffiliationCorporateResource::getUrl('index')),
                Action::make('add_individuals')
                    ->label('Individuales')
                    ->color('success')
                    ->icon('heroicon-o-plus')
                    ->url(fn() => AffiliationResource::getUrl('index')),
                    
                
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}