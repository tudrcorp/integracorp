<?php

namespace App\Filament\Business\Resources\Benefits;

use UnitEnum;
use BackedEnum;
use App\Models\Benefit;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\Benefits\Pages\EditBenefit;
use App\Filament\Business\Resources\Benefits\Pages\ViewBenefit;
use App\Filament\Business\Resources\Benefits\Pages\ListBenefits;
use App\Filament\Business\Resources\Benefits\Pages\CreateBenefit;
use App\Filament\Business\Resources\Benefits\Schemas\BenefitForm;
use App\Filament\Business\Resources\Benefits\Tables\BenefitsTable;
use App\Filament\Business\Resources\Benefits\Schemas\BenefitInfolist;

class BenefitResource extends Resource
{
    protected static ?string $model = Benefit::class;

    protected static ?string $navigationLabel = 'Beneficios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

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

    public static function shouldRegisterNavigation(): bool
    {
        //Solo el Administrador General del Modulo de Business puede acceder a este recurso
        if (Auth::user()->is_business_admin) {
            return true;
        }
        return false;
    }
}