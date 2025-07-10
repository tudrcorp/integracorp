<?php

namespace App\Filament\Resources\TypeServices;

use App\Filament\Resources\TypeServices\Pages\CreateTypeService;
use App\Filament\Resources\TypeServices\Pages\EditTypeService;
use App\Filament\Resources\TypeServices\Pages\ListTypeServices;
use App\Filament\Resources\TypeServices\Pages\ViewTypeService;
use App\Filament\Resources\TypeServices\Schemas\TypeServiceForm;
use App\Filament\Resources\TypeServices\Schemas\TypeServiceInfolist;
use App\Filament\Resources\TypeServices\Tables\TypeServicesTable;
use App\Models\TypeService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TypeServiceResource extends Resource
{
    protected static ?string $model = TypeService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ViewColumns;

    protected static string | UnitEnum | null $navigationGroup = 'TDEC';

    protected static ?string $navigationLabel = 'Tipos de Servicios';

    public static function form(Schema $schema): Schema
    {
        return TypeServiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TypeServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TypeServicesTable::configure($table);
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
            'index' => ListTypeServices::route('/'),
            'create' => CreateTypeService::route('/create'),
            'view' => ViewTypeService::route('/{record}'),
            'edit' => EditTypeService::route('/{record}/edit'),
        ];
    }
}