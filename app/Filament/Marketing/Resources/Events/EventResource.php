<?php

namespace App\Filament\Marketing\Resources\Events;

use App\Filament\Marketing\Resources\Events\Pages\CreateEvent;
use App\Filament\Marketing\Resources\Events\Pages\EditEvent;
use App\Filament\Marketing\Resources\Events\Pages\ListEvents;
use App\Filament\Marketing\Resources\Events\Pages\ViewEvent;
use App\Filament\Marketing\Resources\Events\Schemas\EventForm;
use App\Filament\Marketing\Resources\Events\Schemas\EventInfolist;
use App\Filament\Marketing\Resources\Events\Tables\EventsTable;
use App\Filament\Resources\Events\RelationManagers\GuestsRelationManager;
use App\Models\Event;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-ticket';

    protected static ?string $navigationLabel = 'Eventos';

    protected static string|UnitEnum|null $navigationGroup = 'MARKETING';

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GuestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'view' => ViewEvent::route('/{record}'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'eventos')->first();

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
