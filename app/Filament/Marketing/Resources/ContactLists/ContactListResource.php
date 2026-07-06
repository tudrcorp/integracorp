<?php

namespace App\Filament\Marketing\Resources\ContactLists;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Marketing\Resources\ContactLists\Pages\CreateContactList;
use App\Filament\Marketing\Resources\ContactLists\Pages\EditContactList;
use App\Filament\Marketing\Resources\ContactLists\Pages\ListContactLists;
use App\Filament\Marketing\Resources\ContactLists\Pages\ViewContactList;
use App\Filament\Marketing\Resources\ContactLists\Schemas\ContactListForm;
use App\Filament\Marketing\Resources\ContactLists\Schemas\ContactListInfolist;
use App\Filament\Marketing\Resources\ContactLists\Tables\ContactListsTable;
use App\Models\ContactList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ContactListResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = ContactList::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Contactos';

    protected static ?int $navigationSort = 4;

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
}
