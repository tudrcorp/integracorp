<?php

namespace App\Filament\Marketing\Resources\Events;

use BackedEnum;
use App\Models\Event;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Marketing\Resources\Events\Pages\EditEvent;
use App\Filament\Marketing\Resources\Events\Pages\ViewEvent;
use App\Filament\Marketing\Resources\Events\Pages\ListEvents;
use App\Filament\Marketing\Resources\Events\Pages\CreateEvent;
use App\Filament\Marketing\Resources\Events\Schemas\EventForm;
use App\Filament\Marketing\Resources\Events\Tables\EventsTable;
use App\Filament\Marketing\Resources\Events\Schemas\EventInfolist;
use App\Filament\Resources\Events\RelationManagers\GuestsRelationManager;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = 'fontisto-ticket-alt';

    protected static ?string $navigationLabel = 'Eventos';

    protected static string | UnitEnum | null $navigationGroup = 'Marketing';

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
            GuestsRelationManager::class
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
}