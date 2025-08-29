<?php

namespace App\Filament\Resources\CheckAgentAgencies;

use App\Filament\Resources\CheckAgentAgencies\Pages\CreateCheckAgentAgency;
use App\Filament\Resources\CheckAgentAgencies\Pages\EditCheckAgentAgency;
use App\Filament\Resources\CheckAgentAgencies\Pages\ListCheckAgentAgencies;
use App\Filament\Resources\CheckAgentAgencies\Pages\ViewCheckAgentAgency;
use App\Filament\Resources\CheckAgentAgencies\Schemas\CheckAgentAgencyForm;
use App\Filament\Resources\CheckAgentAgencies\Schemas\CheckAgentAgencyInfolist;
use App\Filament\Resources\CheckAgentAgencies\Tables\CheckAgentAgenciesTable;
use App\Models\CheckAgentAgency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CheckAgentAgencyResource extends Resource
{
    protected static ?string $model = CheckAgentAgency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'HISTORICOS';

    protected static ?string $navigationLabel = 'Agentes y Agencias';

    public static function form(Schema $schema): Schema
    {
        return CheckAgentAgencyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CheckAgentAgencyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CheckAgentAgenciesTable::configure($table);
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
            'index' => ListCheckAgentAgencies::route('/'),
            'create' => CreateCheckAgentAgency::route('/create'),
            'view' => ViewCheckAgentAgency::route('/{record}'),
            'edit' => EditCheckAgentAgency::route('/{record}/edit'),
        ];
    }
}