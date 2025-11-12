<?php

namespace App\Filament\Business\Resources\Coverages;

use UnitEnum;
use BackedEnum;
use App\Models\Coverage;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\Coverages\Pages\EditCoverage;
use App\Filament\Business\Resources\Coverages\Pages\ViewCoverage;
use App\Filament\Business\Resources\Coverages\Pages\ListCoverages;
use App\Filament\Business\Resources\Coverages\Pages\CreateCoverage;
use App\Filament\Business\Resources\Coverages\Schemas\CoverageForm;
use App\Filament\Business\Resources\Coverages\Tables\CoveragesTable;
use App\Filament\Business\Resources\Coverages\Schemas\CoverageInfolist;

class CoverageResource extends Resource
{
    protected static ?string $model = Coverage::class;

    protected static ?string $navigationLabel = 'Coverturas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return CoverageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CoverageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoveragesTable::configure($table);
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
            'index' => ListCoverages::route('/'),
            'create' => CreateCoverage::route('/create'),
            'view' => ViewCoverage::route('/{record}'),
            'edit' => EditCoverage::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        //Solo el Administrador General del Modulo de Business puede acceder a este recurso
        if (Auth::user()->is_business_admin) {
            return true;
        }
        return false;
    }
}