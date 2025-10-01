<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\EditTelemedicineCase;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\ViewTelemedicineCase;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\ListTelemedicineCases;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\CreateTelemedicineCase;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Schemas\TelemedicineCaseForm;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Tables\TelemedicineCasesTable;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Schemas\TelemedicineCaseInfolist;
use App\Filament\Telemedicina\Resources\TelemedicineCases\RelationManagers\ConsultationsRelationManager;

class TelemedicineCaseResource extends Resource
{
    protected static ?string $model = TelemedicineCase::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-call-centre';

    protected static ?string $pluralLabel = 'Gestión de Casos de Telemedicína';

    protected static ?string $navigationLabel = 'Casos de Telemedicina';

    protected static ?int $navigationSort = 3;

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
            ConsultationsRelationManager::class
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