<?php

namespace App\Filament\Administration\Resources\CommissionPayrolls;

use App\Filament\Administration\Resources\CommissionPayrolls\Pages\CreateCommissionPayroll;
use App\Filament\Administration\Resources\CommissionPayrolls\Pages\EditCommissionPayroll;
use App\Filament\Administration\Resources\CommissionPayrolls\Pages\ListCommissionPayrolls;
use App\Filament\Administration\Resources\CommissionPayrolls\Schemas\CommissionPayrollForm;
use App\Filament\Administration\Resources\CommissionPayrolls\Tables\CommissionPayrollsTable;
use App\Models\CommissionPayroll;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CommissionPayrollResource extends Resource
{
    protected static ?string $model = CommissionPayroll::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | UnitEnum | null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Reporte de Comisiones';

    public static function form(Schema $schema): Schema
    {
        return CommissionPayrollForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommissionPayrollsTable::configure($table);
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
            'index' => ListCommissionPayrolls::route('/'),
            'create' => CreateCommissionPayroll::route('/create'),
            'edit' => EditCommissionPayroll::route('/{record}/edit'),
        ];
    }
}