<?php

namespace App\Filament\Marketing\Resources\Helpdesks;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Concerns\AuthorizesHelpdeskTicketCreation;
use App\Filament\Concerns\RegistersHelpdeskUnreadNoteNavigation;
use App\Filament\Marketing\Resources\Helpdesks\Pages\CreateHelpdesk;
use App\Filament\Marketing\Resources\Helpdesks\Pages\EditHelpdesk;
use App\Filament\Marketing\Resources\Helpdesks\Pages\ListHelpdesks;
use App\Filament\Marketing\Resources\Helpdesks\Pages\ViewHelpdesk;
use App\Filament\Marketing\Resources\Helpdesks\Schemas\HelpdeskForm;
use App\Filament\Marketing\Resources\Helpdesks\Schemas\HelpdeskInfolist;
use App\Filament\Marketing\Resources\Helpdesks\Tables\HelpdesksTable;
use App\Models\HelpDesk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class HelpdeskResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use AuthorizesHelpdeskTicketCreation;
    use RegistersHelpdeskUnreadNoteNavigation;

    protected static ?string $model = HelpDesk::class;

    protected static ?string $navigationLabel = 'Helpdesk';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return HelpdeskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HelpdeskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HelpdesksTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['rrhhColaboradores']);
    }

    public static function canEdit(Model $record): bool
    {
        if (! parent::canEdit($record)) {
            return false;
        }

        return static::currentUserIsHelpdeskTicketCreator($record);
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
            'index' => ListHelpdesks::route('/'),
            'create' => CreateHelpdesk::route('/create'),
            'view' => ViewHelpdesk::route('/{record}'),
            'edit' => EditHelpdesk::route('/{record}/edit'),
        ];
    }
}
