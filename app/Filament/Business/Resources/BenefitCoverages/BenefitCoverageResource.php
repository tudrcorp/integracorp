<?php

namespace App\Filament\Business\Resources\BenefitCoverages;

use App\Filament\Business\Resources\BenefitCoverages\Pages\CreateBenefitCoverage;
use App\Filament\Business\Resources\BenefitCoverages\Pages\EditBenefitCoverage;
use App\Filament\Business\Resources\BenefitCoverages\Pages\ListBenefitCoverages;
use App\Filament\Business\Resources\BenefitCoverages\Pages\ViewBenefitCoverage;
use App\Filament\Business\Resources\BenefitCoverages\Schemas\BenefitCoverageForm;
use App\Filament\Business\Resources\BenefitCoverages\Schemas\BenefitCoverageInfolist;
use App\Filament\Business\Resources\BenefitCoverages\Tables\BenefitCoveragesTable;
use App\Models\BenefitCoverage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BenefitCoverageResource extends Resource
{
    protected static ?string $model = BenefitCoverage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
}
