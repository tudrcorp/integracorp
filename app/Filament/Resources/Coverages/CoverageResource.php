<?php

namespace App\Filament\Resources\Coverages;

use UnitEnum;
use BackedEnum;
use App\Models\Coverage;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\Coverages\Pages\EditCoverage;
use App\Filament\Resources\Coverages\Pages\ViewCoverage;
use App\Filament\Resources\Coverages\Pages\ListCoverages;
use App\Filament\Resources\Coverages\Pages\CreateCoverage;
use App\Filament\Resources\Coverages\Schemas\CoverageForm;
use App\Filament\Resources\Coverages\Tables\CoveragesTable;
use App\Filament\Resources\Coverages\Schemas\CoverageInfolist;

class CoverageResource extends Resource
{
    protected static ?string $model = Coverage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentCurrencyDollar;

    protected static string | UnitEnum | null $navigationGroup = 'TDEC';

    protected static ?string $navigationLabel = 'Coberturas';

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

    public static function canAccess(): bool
    {
        // Deshabilitado temporalmente por mantenimiento
        if (Auth::user()->is_superAdmin) {
            return true;
        }
        return false;
    }
}