<?php

namespace App\Filament\Resources\Fees;

use UnitEnum;
use BackedEnum;
use App\Models\Fee;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\Fees\Pages\EditFee;
use App\Filament\Resources\Fees\Pages\ViewFee;
use App\Filament\Resources\Fees\Pages\ListFees;
use App\Filament\Resources\Fees\Pages\CreateFee;
use App\Filament\Resources\Fees\Schemas\FeeForm;
use App\Filament\Resources\Fees\Tables\FeesTable;
use App\Filament\Resources\Fees\Schemas\FeeInfolist;

class FeeResource extends Resource
{
    protected static ?string $model = Fee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookOpen;

    protected static string | UnitEnum | null $navigationGroup = 'TDEC';

    protected static ?string $navigationLabel = 'Tarifas';

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

    public static function canAccess(): bool
    {
        // Deshabilitado temporalmente por mantenimiento
        if (Auth::user()->is_superAdmin) {
            return true;
        }
        return false;
    }
}