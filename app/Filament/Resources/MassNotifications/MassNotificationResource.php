<?php

namespace App\Filament\Resources\MassNotifications;

use App\Filament\Resources\MassNotifications\Pages\CreateMassNotification;
use App\Filament\Resources\MassNotifications\Pages\EditMassNotification;
use App\Filament\Resources\MassNotifications\Pages\ListMassNotifications;
use App\Filament\Resources\MassNotifications\Pages\ViewMassNotification;
use App\Filament\Resources\MassNotifications\Schemas\MassNotificationForm;
use App\Filament\Resources\MassNotifications\Schemas\MassNotificationInfolist;
use App\Filament\Resources\MassNotifications\Tables\MassNotificationsTable;
use App\Models\MassNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MassNotificationResource extends Resource
{
    protected static ?string $model = MassNotification::class;

    protected static string|BackedEnum|null $navigationIcon = 'fluentui-channel-alert-24';

    protected static string | UnitEnum | null $navigationGroup = 'MARKETING';

    protected static ?string $navigationLabel = 'Notificaciones Masivas';
    
    public static function form(Schema $schema): Schema
    {
        return MassNotificationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MassNotificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MassNotificationsTable::configure($table);
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
            'index' => ListMassNotifications::route('/'),
            'create' => CreateMassNotification::route('/create'),
            'view' => ViewMassNotification::route('/{record}'),
            'edit' => EditMassNotification::route('/{record}/edit'),
        ];
    }
}