<?php

namespace App\Filament\Business\Resources\Benefits;

use App\Filament\Business\Resources\Benefits\Pages\CreateBenefit;
use App\Filament\Business\Resources\Benefits\Pages\EditBenefit;
use App\Filament\Business\Resources\Benefits\Pages\ListBenefits;
use App\Filament\Business\Resources\Benefits\Pages\ViewBenefit;
use App\Filament\Business\Resources\Benefits\Schemas\BenefitForm;
use App\Filament\Business\Resources\Benefits\Schemas\BenefitInfolist;
use App\Filament\Business\Resources\Benefits\Tables\BenefitsTable;
use App\Models\Benefit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BenefitResource extends Resource
{
    protected static ?string $model = Benefit::class;

    protected static ?string $navigationLabel = 'Beneficios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
}