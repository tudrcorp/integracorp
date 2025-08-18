<?php

namespace App\Filament\Resources\TelemedicineExamenLists;

use App\Filament\Resources\TelemedicineExamenLists\Pages\CreateTelemedicineExamenList;
use App\Filament\Resources\TelemedicineExamenLists\Pages\EditTelemedicineExamenList;
use App\Filament\Resources\TelemedicineExamenLists\Pages\ListTelemedicineExamenLists;
use App\Filament\Resources\TelemedicineExamenLists\Pages\ViewTelemedicineExamenList;
use App\Filament\Resources\TelemedicineExamenLists\Schemas\TelemedicineExamenListForm;
use App\Filament\Resources\TelemedicineExamenLists\Schemas\TelemedicineExamenListInfolist;
use App\Filament\Resources\TelemedicineExamenLists\Tables\TelemedicineExamenListsTable;
use App\Models\TelemedicineExamenList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelemedicineExamenListResource extends Resource
{
    protected static ?string $model = TelemedicineExamenList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Exámenes';

    protected static string | UnitEnum | null $navigationGroup = 'TELEMEDICINA';

    protected static ?string $navigationLabel = 'Lista de Exámenes';

    public static function form(Schema $schema): Schema
    {
        return TelemedicineExamenListForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicineExamenListInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineExamenListsTable::configure($table);
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
            'index' => ListTelemedicineExamenLists::route('/'),
            'create' => CreateTelemedicineExamenList::route('/create'),
            'view' => ViewTelemedicineExamenList::route('/{record}'),
            'edit' => EditTelemedicineExamenList::route('/{record}/edit'),
        ];
    }
}