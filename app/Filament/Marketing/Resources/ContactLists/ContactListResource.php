<?php

namespace App\Filament\Marketing\Resources\ContactLists;

use App\Filament\Marketing\Resources\ContactLists\Pages\CreateContactList;
use App\Filament\Marketing\Resources\ContactLists\Pages\EditContactList;
use App\Filament\Marketing\Resources\ContactLists\Pages\ListContactLists;
use App\Filament\Marketing\Resources\ContactLists\Pages\ViewContactList;
use App\Filament\Marketing\Resources\ContactLists\Schemas\ContactListForm;
use App\Filament\Marketing\Resources\ContactLists\Schemas\ContactListInfolist;
use App\Filament\Marketing\Resources\ContactLists\Tables\ContactListsTable;
use App\Models\ContactList;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ContactListResource extends Resource
{
    protected static ?string $model = ContactList::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Contactos';

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
        return ContactListForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContactListInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactListsTable::configure($table);
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
            'index' => ListContactLists::route('/'),
            'create' => CreateContactList::route('/create'),
            'view' => ViewContactList::route('/{record}'),
            'edit' => EditContactList::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'contactos')->first();

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
