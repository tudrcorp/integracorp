<?php

namespace App\Filament\Master\Resources\Commissions;

use App\Filament\Master\Resources\Commissions\Pages\CreateCommission;
use App\Filament\Master\Resources\Commissions\Pages\EditCommission;
use App\Filament\Master\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Master\Resources\Commissions\Pages\ViewCommission;
use App\Filament\Master\Resources\Commissions\Schemas\CommissionForm;
use App\Filament\Master\Resources\Commissions\Schemas\CommissionInfolist;
use App\Filament\Master\Resources\Commissions\Tables\CommissionsTable;
use App\Models\Commission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CommissionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommissionInfolist::configure($schema);
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
            'view' => ViewCommission::route('/{record}'),
            'edit' => EditCommission::route('/{record}/edit'),
        ];
    }
}
