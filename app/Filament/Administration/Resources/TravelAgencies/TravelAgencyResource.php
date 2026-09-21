<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\TravelAgencies;

use App\Filament\Administration\Resources\TravelAgencies\Pages\CreateTravelAgency;
use App\Filament\Administration\Resources\TravelAgencies\Pages\EditTravelAgency;
use App\Filament\Administration\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Administration\Resources\TravelAgencies\Pages\ViewTravelAgency;
use App\Filament\Business\Resources\TravelAgencies\Schemas\TravelAgencyForm;
use App\Filament\Business\Resources\TravelAgencies\Schemas\TravelAgencyInfolist;
use App\Filament\Business\Resources\TravelAgencies\Tables\TravelAgenciesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\TravelAgency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Agencias de viaje dentro del panel de Administración.
 *
 * El formulario, el infolist y la tabla se reutilizan de Negocios, dueño del
 * módulo: así una sola definición sirve a los dos paneles. Lo que sí es propio es
 * el namespace, porque de él se deriva el módulo de permisos (ADMINISTRACION),
 * y las páginas, para que las rutas queden dentro de este panel.
 */
class TravelAgencyResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = TravelAgency::class;

    protected static ?string $navigationLabel = 'Agencias De Viaje';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TravelAgencyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TravelAgencyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TravelAgenciesTable::configure($table);
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
            'index' => ListTravelAgencies::route('/'),
            'create' => CreateTravelAgency::route('/create'),
            'view' => ViewTravelAgency::route('/{record}'),
            'edit' => EditTravelAgency::route('/{record}/edit'),
        ];
    }
}
