<?php

namespace App\Filament\Resources\Responsibles;

use App\Filament\Resources\Responsibles\Pages\CreateResponsible;
use App\Filament\Resources\Responsibles\Pages\EditResponsible;
use App\Filament\Resources\Responsibles\Pages\ListResponsibles;
use App\Filament\Resources\Responsibles\Pages\ViewResponsible;
use App\Filament\Resources\Responsibles\Schemas\ResponsibleForm;
use App\Filament\Resources\Responsibles\Schemas\ResponsibleInfolist;
use App\Filament\Resources\Responsibles\Tables\ResponsiblesTable;
use App\Models\Responsible;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResponsibleResource extends Resource
{
    protected static ?string $model = Responsible::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Star;

    protected static ?string $navigationLabel = 'RESPONSABLES';

    public static function form(Schema $schema): Schema
    {
        return ResponsibleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ResponsibleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResponsiblesTable::configure($table);
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
            'index' => ListResponsibles::route('/'),
            'create' => CreateResponsible::route('/create'),
            'view' => ViewResponsible::route('/{record}'),
            'edit' => EditResponsible::route('/{record}/edit'),
        ];
    }
}