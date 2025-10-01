<?php

namespace App\Filament\Resources\TelemedicineCases;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\TelemedicineCases\Pages\EditTelemedicineCase;
use App\Filament\Resources\TelemedicineCases\Pages\ViewTelemedicineCase;
use App\Filament\Resources\TelemedicineCases\Pages\ListTelemedicineCases;
use App\Filament\Resources\TelemedicineCases\Pages\CreateTelemedicineCase;
use App\Filament\Resources\TelemedicineCases\Schemas\TelemedicineCaseForm;
use App\Filament\Resources\TelemedicineCases\Tables\TelemedicineCasesTable;
use App\Filament\Resources\TelemedicineCases\Schemas\TelemedicineCaseInfolist;
use App\Filament\Resources\TelemedicineCases\RelationManagers\ConsultationsRelationManager;
use App\Filament\Resources\TelemedicineCases\RelationManagers\OperationLogsRelationManager;

class TelemedicineCaseResource extends Resource
{
    protected static ?string $model = TelemedicineCase::class;

    protected static string | UnitEnum | null $navigationGroup = 'TELEMEDICINA';

    protected static ?string $navigationLabel = 'Gestión de Casos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TelemedicineCaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicineCaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineCasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ConsultationsRelationManager::class,
            OperationLogsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineCases::route('/'),
            'create' => CreateTelemedicineCase::route('/create'),
            'view' => ViewTelemedicineCase::route('/{record}'),
            'edit' => EditTelemedicineCase::route('/{record}/edit'),
        ];
    }
}