<?php

namespace App\Filament\Administration\Resources\Commissions;

use App\Filament\Administration\Resources\Commissions\Pages\CreateCommission;
use App\Filament\Administration\Resources\Commissions\Pages\EditCommission;
use App\Filament\Administration\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Administration\Resources\Commissions\Schemas\CommissionForm;
use App\Filament\Administration\Resources\Commissions\Tables\CommissionsTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\Commission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CommissionResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Commission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Detallado de Comisiones';

    public static function form(Schema $schema): Schema
    {
        return CommissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommissionsTable::configure($table);
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
            'index' => ListCommissions::route('/'),
            'create' => CreateCommission::route('/create'),
            'edit' => EditCommission::route('/{record}/edit'),
        ];
    }
}
