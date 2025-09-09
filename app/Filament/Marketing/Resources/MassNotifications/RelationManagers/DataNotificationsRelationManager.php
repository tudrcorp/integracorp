<?php

namespace App\Filament\Marketing\Resources\MassNotifications\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Marketing\Resources\MassNotifications\MassNotificationResource;

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
                CreateAction::make(),
            ]);
    }
}