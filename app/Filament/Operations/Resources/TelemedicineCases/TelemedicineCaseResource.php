<?php

namespace App\Filament\Operations\Resources\TelemedicineCases;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Operations\Resources\TelemedicineCases\Pages\EditTelemedicineCase;
use App\Filament\Operations\Resources\TelemedicineCases\Pages\ViewTelemedicineCase;
use App\Filament\Operations\Resources\TelemedicineCases\Pages\ListTelemedicineCases;
use App\Filament\Operations\Resources\TelemedicineCases\Pages\CreateTelemedicineCase;
use App\Filament\Operations\Resources\TelemedicineCases\Schemas\TelemedicineCaseForm;
use App\Filament\Operations\Resources\TelemedicineCases\Tables\TelemedicineCasesTable;
use App\Filament\Operations\Resources\TelemedicineCases\Schemas\TelemedicineCaseInfolist;
use App\Filament\Operations\Resources\TelemedicineCases\RelationManagers\ObservationsRelationManager;
use App\Filament\Operations\Resources\TelemedicineCases\RelationManagers\ConsultationsRelationManager;
use App\Filament\Operations\Resources\TelemedicineCases\RelationManagers\TelemedicineDocumentsRelationManager;
use UnitEnum;

class TelemedicineCaseResource extends Resource
{
    protected static ?string $model = TelemedicineCase::class;

    protected static string | UnitEnum | null $navigationGroup = 'TELEMEDICINA';

    protected static ?string $navigationLabel = 'Gestión de Casos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

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
            ObservationsRelationManager::class,
            TelemedicineDocumentsRelationManager::class,
            
            //
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