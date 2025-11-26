<?php

namespace App\Filament\Business\Resources\WhiteCompanies;

use App\Filament\Business\Resources\WhiteCompanies\Pages\CreateWhiteCompany;
use App\Filament\Business\Resources\WhiteCompanies\Pages\EditWhiteCompany;
use App\Filament\Business\Resources\WhiteCompanies\Pages\ListWhiteCompanies;
use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyForm;
use App\Filament\Business\Resources\WhiteCompanies\Tables\WhiteCompaniesTable;
use App\Models\WhiteCompany;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WhiteCompanyResource extends Resource
{
    protected static ?string $model = WhiteCompany::class;

    protected static ?string $navigationLabel = 'Empresas (White-Label)';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string | UnitEnum | null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    public static function form(Schema $schema): Schema
    {
        return WhiteCompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhiteCompaniesTable::configure($table);
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
            'index' => ListWhiteCompanies::route('/'),
            'create' => CreateWhiteCompany::route('/create'),
            'edit' => EditWhiteCompany::route('/{record}/edit'),
        ];
    }
}