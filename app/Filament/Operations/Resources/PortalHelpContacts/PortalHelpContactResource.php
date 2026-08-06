<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\PortalHelpContacts;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\PortalHelpContacts\Pages\CreatePortalHelpContact;
use App\Filament\Operations\Resources\PortalHelpContacts\Pages\EditPortalHelpContact;
use App\Filament\Operations\Resources\PortalHelpContacts\Pages\ListPortalHelpContacts;
use App\Filament\Operations\Resources\PortalHelpContacts\Schemas\PortalHelpContactForm;
use App\Filament\Operations\Resources\PortalHelpContacts\Tables\PortalHelpContactsTable;
use App\Models\PortalHelpContact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PortalHelpContactResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = PortalHelpContact::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Contactos Ayuda Portal';

    protected static ?string $modelLabel = 'Contacto de Ayuda Portal';

    protected static ?string $pluralModelLabel = 'Contactos de Ayuda Portal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?int $navigationSort = 26;

    public static function form(Schema $schema): Schema
    {
        return PortalHelpContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortalHelpContactsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalHelpContacts::route('/'),
            'create' => CreatePortalHelpContact::route('/create'),
            'edit' => EditPortalHelpContact::route('/{record}/edit'),
        ];
    }
}
