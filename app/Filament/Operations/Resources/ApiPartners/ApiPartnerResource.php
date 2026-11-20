<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\ApiPartners\Pages\CreateApiPartner;
use App\Filament\Operations\Resources\ApiPartners\Pages\EditApiPartner;
use App\Filament\Operations\Resources\ApiPartners\Pages\ListApiPartners;
use App\Filament\Operations\Resources\ApiPartners\Pages\ViewApiPartner;
use App\Filament\Operations\Resources\ApiPartners\RelationManagers\AccessLogsRelationManager;
use App\Filament\Operations\Resources\ApiPartners\Schemas\ApiPartnerForm;
use App\Filament\Operations\Resources\ApiPartners\Schemas\ApiPartnerInfolist;
use App\Filament\Operations\Resources\ApiPartners\Tables\ApiPartnersTable;
use App\Models\ApiPartner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Aliados externos con acceso al API de IntegraCorp: credenciales, IPs, permisos y bitácora.
 * Solo SUPERADMIN (registrado en DepartmentNavigationPermissionRegistry::SUPER_ADMIN_ONLY).
 */
class ApiPartnerResource extends Resource
{
    use AuthorizesDepartmentNavigation {
        canAccess as departmentCanAccess;
    }

    protected static ?string $model = ApiPartner::class;

    protected static ?string $navigationLabel = 'Aliados con acceso API';

    protected static ?string $modelLabel = 'aliado API';

    protected static ?string $pluralModelLabel = 'aliados API';

    protected static ?string $slug = 'api-partners';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return ApiPartner::tablesExist() && static::departmentCanAccess();
    }

    public static function canDelete($record): bool
    {
        // Se desactiva, no se borra: la bitácora de accesos debe seguir apuntando al aliado.
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ApiPartnerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ApiPartnerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiPartnersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AccessLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiPartners::route('/'),
            'create' => CreateApiPartner::route('/create'),
            'view' => ViewApiPartner::route('/{record}'),
            'edit' => EditApiPartner::route('/{record}/edit'),
        ];
    }
}
