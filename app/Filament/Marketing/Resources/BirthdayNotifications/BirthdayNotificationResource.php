<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications;

use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\CreateBirthdayNotification;
use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\EditBirthdayNotification;
use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\ListBirthdayNotifications;
use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\ViewBirthdayNotification;
use App\Filament\Marketing\Resources\BirthdayNotifications\Schemas\BirthdayNotificationForm;
use App\Filament\Marketing\Resources\BirthdayNotifications\Schemas\BirthdayNotificationInfolist;
use App\Filament\Marketing\Resources\BirthdayNotifications\Tables\BirthdayNotificationsTable;
use App\Models\BirthdayNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BirthdayNotificationResource extends Resource
{
    protected static ?string $model = BirthdayNotification::class;

    protected static string|BackedEnum|null $navigationIcon = 'bi-cake2-fill';

    protected static ?string $navigationLabel = 'Notificaciones Cumpleaños';

    protected static string | UnitEnum | null $navigationGroup = 'Marketing';
    
    public static function form(Schema $schema): Schema
    {
        return BirthdayNotificationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BirthdayNotificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BirthdayNotificationsTable::configure($table);
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
            'index' => ListBirthdayNotifications::route('/'),
            'create' => CreateBirthdayNotification::route('/create'),
            'view' => ViewBirthdayNotification::route('/{record}'),
            'edit' => EditBirthdayNotification::route('/{record}/edit'),
        ];
    }
}