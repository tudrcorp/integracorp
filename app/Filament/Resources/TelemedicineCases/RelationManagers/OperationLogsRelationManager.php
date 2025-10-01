<?php

namespace App\Filament\Resources\TelemedicineCases\RelationManagers;

use App\Models\Bitacora;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\TelemedicineCases\TelemedicineCaseResource;
use BackedEnum;

class OperationLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'operationLogs';

    protected static ?string $title = 'Bitácora de Operaciones';

    protected static string|BackedEnum|null $icon = 'healthicons-f-phone';
    
    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}