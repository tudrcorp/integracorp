<?php

namespace App\Filament\Resources\Benefits;

use UnitEnum;
use BackedEnum;
use App\Models\Benefit;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\Benefits\Pages\EditBenefit;
use App\Filament\Resources\Benefits\Pages\ViewBenefit;
use App\Filament\Resources\Benefits\Pages\ListBenefits;
use App\Filament\Resources\Benefits\Pages\CreateBenefit;
use App\Filament\Resources\Benefits\Schemas\BenefitForm;
use App\Filament\Resources\Benefits\Tables\BenefitsTable;
use App\Filament\Resources\Benefits\Schemas\BenefitInfolist;

class BenefitResource extends Resource
{
    protected static ?string $model = Benefit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Share;

    protected static string | UnitEnum | null $navigationGroup = 'TDEC';

    protected static ?string $navigationLabel = 'Beneficios';

    public static function form(Schema $schema): Schema
    {
        return BenefitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BenefitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BenefitsTable::configure($table);
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
            'index' => ListBenefits::route('/'),
            'create' => CreateBenefit::route('/create'),
            'view' => ViewBenefit::route('/{record}'),
            'edit' => EditBenefit::route('/{record}/edit'),
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