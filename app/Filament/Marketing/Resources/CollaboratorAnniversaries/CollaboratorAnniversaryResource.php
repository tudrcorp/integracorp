<?php

namespace App\Filament\Marketing\Resources\CollaboratorAnniversaries;

use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Pages\CreateCollaboratorAnniversary;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Pages\EditCollaboratorAnniversary;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Pages\ListCollaboratorAnniversaries;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Schemas\CollaboratorAnniversaryForm;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\Tables\CollaboratorAnniversariesTable;
use App\Models\CollaboratorAnniversary;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CollaboratorAnniversaryResource extends Resource
{
    protected static ?string $model = CollaboratorAnniversary::class;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACION/RRHH';

    protected static ?string $navigationLabel = 'Aniversarios';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return 'NEW';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return CollaboratorAnniversaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CollaboratorAnniversariesTable::configure($table);
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
            'index' => ListCollaboratorAnniversaries::route('/'),
            'create' => CreateCollaboratorAnniversary::route('/create'),
            'edit' => EditCollaboratorAnniversary::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'colaborador-aniversario')->first();

        // si es superadmin, retornar true
        if (in_array('SUPERADMIN', Auth::user()->departament)) {
            return true;
        }

        if (in_array($module, Auth::user()->departament)) {
            if (UserPermission::where('user_id', Auth::user()->id)->where('permission_id', $permission->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
