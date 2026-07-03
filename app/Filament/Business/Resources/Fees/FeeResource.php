<?php

namespace App\Filament\Business\Resources\Fees;

use App\Filament\Business\Resources\Fees\Pages\CreateFee;
use App\Filament\Business\Resources\Fees\Pages\EditFee;
use App\Filament\Business\Resources\Fees\Pages\ListFees;
use App\Filament\Business\Resources\Fees\Pages\ViewFee;
use App\Filament\Business\Resources\Fees\Schemas\FeeForm;
use App\Filament\Business\Resources\Fees\Schemas\FeeInfolist;
use App\Filament\Business\Resources\Fees\Tables\FeesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\Fee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class FeeResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Fee::class;

    protected static ?string $navigationLabel = 'Tarifas y Costos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return FeeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FeeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeesTable::configure($table);
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
            'index' => ListFees::route('/'),
            'create' => CreateFee::route('/create'),
            'view' => ViewFee::route('/{record}'),
            'edit' => EditFee::route('/{record}/edit'),
        ];
    }
}
