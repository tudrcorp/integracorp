<?php

namespace App\Filament\Business\Resources\AgeRanges;

use UnitEnum;
use BackedEnum;
use App\Models\AgeRange;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\AgeRanges\Pages\EditAgeRange;
use App\Filament\Business\Resources\AgeRanges\Pages\ViewAgeRange;
use App\Filament\Business\Resources\AgeRanges\Pages\ListAgeRanges;
use App\Filament\Business\Resources\AgeRanges\Pages\CreateAgeRange;
use App\Filament\Business\Resources\AgeRanges\Schemas\AgeRangeForm;
use App\Filament\Business\Resources\AgeRanges\Tables\AgeRangesTable;
use App\Filament\Business\Resources\AgeRanges\Schemas\AgeRangeInfolist;

class AgeRangeResource extends Resource
{
    protected static ?string $model = AgeRange::class;

    protected static ?string $navigationLabel = 'Rango de Edades';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-list-bullet';

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return AgeRangeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AgeRangeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgeRangesTable::configure($table);
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
            'index'     => ListAgeRanges::route('/'),
            'create'    => CreateAgeRange::route('/create'),
            'view'      => ViewAgeRange::route('/{record}'),
            'edit'      => EditAgeRange::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        //Solo el Administrador General del Modulo de Business puede acceder a este recurso
        if(Auth::user()->is_business_admin){
            return true;
        }
        return false;
        
    }
}