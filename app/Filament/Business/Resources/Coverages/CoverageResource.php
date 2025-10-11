<?php

namespace App\Filament\Business\Resources\Coverages;

use App\Filament\Business\Resources\Coverages\Pages\CreateCoverage;
use App\Filament\Business\Resources\Coverages\Pages\EditCoverage;
use App\Filament\Business\Resources\Coverages\Pages\ListCoverages;
use App\Filament\Business\Resources\Coverages\Pages\ViewCoverage;
use App\Filament\Business\Resources\Coverages\Schemas\CoverageForm;
use App\Filament\Business\Resources\Coverages\Schemas\CoverageInfolist;
use App\Filament\Business\Resources\Coverages\Tables\CoveragesTable;
use App\Models\Coverage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CoverageResource extends Resource
{
    protected static ?string $model = Coverage::class;

    protected static ?string $navigationLabel = 'Coverturas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
}