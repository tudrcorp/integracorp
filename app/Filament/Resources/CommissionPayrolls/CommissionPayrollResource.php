<?php

namespace App\Filament\Resources\CommissionPayrolls;

use App\Filament\Resources\CommissionPayrolls\Pages\CreateCommissionPayroll;
use App\Filament\Resources\CommissionPayrolls\Pages\EditCommissionPayroll;
use App\Filament\Resources\CommissionPayrolls\Pages\ListCommissionPayrolls;
use App\Filament\Resources\CommissionPayrolls\Pages\ViewCommissionPayroll;
use App\Filament\Resources\CommissionPayrolls\Schemas\CommissionPayrollForm;
use App\Filament\Resources\CommissionPayrolls\Schemas\CommissionPayrollInfolist;
use App\Filament\Resources\CommissionPayrolls\Tables\CommissionPayrollsTable;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Reporte de Comisiones';

    public static function form(Schema $schema): Schema
    {
        return CommissionPayrollForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommissionPayrollInfolist::configure($schema);
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
            'view' => ViewCommissionPayroll::route('/{record}'),
            'edit' => EditCommissionPayroll::route('/{record}/edit'),
        ];
    }
}