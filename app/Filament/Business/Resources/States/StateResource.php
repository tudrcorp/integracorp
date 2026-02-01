<?php

namespace App\Filament\Business\Resources\States;

use UnitEnum;
use BackedEnum;
use App\Models\State;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\States\Pages\EditState;
use App\Filament\Business\Resources\States\Pages\ViewState;
use App\Filament\Business\Resources\States\Pages\ListStates;
use App\Filament\Business\Resources\States\Pages\CreateState;
use App\Filament\Business\Resources\States\Schemas\StateForm;
use App\Filament\Business\Resources\States\Tables\StatesTable;
use App\Filament\Business\Resources\States\Schemas\StateInfolist;

class StateResource extends Resource
{
    protected static ?string $model = State::class;

    protected static ?string $navigationLabel = 'Estados';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return StateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StatesTable::configure($table);
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
            'index' => ListStates::route('/'),
            'create' => CreateState::route('/create'),
            'view' => ViewState::route('/{record}'),
            'edit' => EditState::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        //Solo el Administrador General del Modulo de Business puede acceder a este recurso
        if (in_array('SUPERADMIN', auth()->user()->departament)) {
            return true;
        }
        return false;
    }
}
