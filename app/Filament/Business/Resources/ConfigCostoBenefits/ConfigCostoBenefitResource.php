<?php

namespace App\Filament\Business\Resources\ConfigCostoBenefits;

use App\Filament\Business\Resources\ConfigCostoBenefits\Pages\CreateConfigCostoBenefit;
use App\Filament\Business\Resources\ConfigCostoBenefits\Pages\EditConfigCostoBenefit;
use App\Filament\Business\Resources\ConfigCostoBenefits\Pages\ListConfigCostoBenefits;
use App\Filament\Business\Resources\ConfigCostoBenefits\Schemas\ConfigCostoBenefitForm;
use App\Filament\Business\Resources\ConfigCostoBenefits\Tables\ConfigCostoBenefitsTable;
use App\Models\ConfigCostoBenefit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ConfigCostoBenefitResource extends Resource
{
    protected static ?string $model = ConfigCostoBenefit::class;

    protected static ?string $navigationLabel = 'Porcentajes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return ConfigCostoBenefitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigCostoBenefitsTable::configure($table);
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
            'index' => ListConfigCostoBenefits::route('/'),
            'create' => CreateConfigCostoBenefit::route('/create'),
            'edit' => EditConfigCostoBenefit::route('/{record}/edit'),
        ];
    }
}
