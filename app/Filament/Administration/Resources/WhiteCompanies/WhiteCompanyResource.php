<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\WhiteCompanies;

use App\Filament\Administration\Resources\WhiteCompanies\Pages\CreateWhiteCompany;
use App\Filament\Administration\Resources\WhiteCompanies\Pages\EditWhiteCompany;
use App\Filament\Administration\Resources\WhiteCompanies\Pages\ListWhiteCompanies;
use App\Filament\Business\Resources\WhiteCompanies\RelationManagers\AssignedPlansRelationManager;
use App\Filament\Business\Resources\WhiteCompanies\RelationManagers\NegotiatedFeesRelationManager;
use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyForm;
use App\Filament\Business\Resources\WhiteCompanies\Tables\WhiteCompaniesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\WhiteCompany;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Empresas aliadas dentro del panel de Administración.
 *
 * Formulario, tabla y relation managers se reutilizan de Negocios, dueño del
 * módulo, para no mantener dos copias de un recurso en evolución. El namespace
 * propio es lo que hace que el permiso resuelva a ADMINISTRACION.
 */
class WhiteCompanyResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = WhiteCompany::class;

    protected static ?string $navigationLabel = 'Empresas Aliadas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?string $recordTitleAttribute = 'name';

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
            // Primero se asigna el plan, después se le pactan las netas.
            AssignedPlansRelationManager::class,
            NegotiatedFeesRelationManager::class,
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
