<?php

namespace App\Filament\Resources\TelemedicineStudiesLists;

use App\Filament\Resources\TelemedicineStudiesLists\Pages\CreateTelemedicineStudiesList;
use App\Filament\Resources\TelemedicineStudiesLists\Pages\EditTelemedicineStudiesList;
use App\Filament\Resources\TelemedicineStudiesLists\Pages\ListTelemedicineStudiesLists;
use App\Filament\Resources\TelemedicineStudiesLists\Pages\ViewTelemedicineStudiesList;
use App\Filament\Resources\TelemedicineStudiesLists\Schemas\TelemedicineStudiesListForm;
use App\Filament\Resources\TelemedicineStudiesLists\Schemas\TelemedicineStudiesListInfolist;
use App\Filament\Resources\TelemedicineStudiesLists\Tables\TelemedicineStudiesListsTable;
use App\Models\TelemedicineStudiesList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelemedicineStudiesListResource extends Resource
{
    protected static ?string $model = TelemedicineStudiesList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Estudios';

    protected static string | UnitEnum | null $navigationGroup = 'TELEMEDICINA';

    protected static ?string $navigationLabel = 'Lista de Estudios';

    public static function form(Schema $schema): Schema
    {
        return TelemedicineStudiesListForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicineStudiesListInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineStudiesListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineStudiesLists::route('/'),
            'create' => CreateTelemedicineStudiesList::route('/create'),
            'view' => ViewTelemedicineStudiesList::route('/{record}'),
            'edit' => EditTelemedicineStudiesList::route('/{record}/edit'),
        ];
    }
}