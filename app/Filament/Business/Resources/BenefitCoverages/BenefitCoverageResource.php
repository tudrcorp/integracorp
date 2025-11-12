<?php

namespace App\Filament\Business\Resources\BenefitCoverages;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\BenefitCoverage;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\BenefitCoverages\Pages\EditBenefitCoverage;
use App\Filament\Business\Resources\BenefitCoverages\Pages\ViewBenefitCoverage;
use App\Filament\Business\Resources\BenefitCoverages\Pages\ListBenefitCoverages;
use App\Filament\Business\Resources\BenefitCoverages\Pages\CreateBenefitCoverage;
use App\Filament\Business\Resources\BenefitCoverages\Schemas\BenefitCoverageForm;
use App\Filament\Business\Resources\BenefitCoverages\Tables\BenefitCoveragesTable;
use App\Filament\Business\Resources\BenefitCoverages\Schemas\BenefitCoverageInfolist;

class BenefitCoverageResource extends Resource
{
    protected static ?string $model = BenefitCoverage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?string $navigationLabel = 'Beneficios Y Coberturas';

    public static function form(Schema $schema): Schema
    {
        return BenefitCoverageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BenefitCoverageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BenefitCoveragesTable::configure($table);
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
            'index' => ListBenefitCoverages::route('/'),
            'create' => CreateBenefitCoverage::route('/create'),
            'view' => ViewBenefitCoverage::route('/{record}'),
            'edit' => EditBenefitCoverage::route('/{record}/edit'),
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