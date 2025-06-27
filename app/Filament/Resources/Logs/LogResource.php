<?php

namespace App\Filament\Resources\Logs;

use App\Filament\Resources\Logs\Pages\CreateLog;
use App\Filament\Resources\Logs\Pages\EditLog;
use App\Filament\Resources\Logs\Pages\ListLogs;
use App\Filament\Resources\Logs\Pages\ViewLog;
use App\Filament\Resources\Logs\Schemas\LogForm;
use App\Filament\Resources\Logs\Schemas\LogInfolist;
use App\Filament\Resources\Logs\Tables\LogsTable;
use App\Models\Log;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LogResource extends Resource
{
    protected static ?string $model = Log::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BugAnt;

    protected static ?string $navigationLabel = 'ACTIVIDAD DE SISTEMA';

    public static function form(Schema $schema): Schema
    {
        return LogForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LogsTable::configure($table);
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
            'index' => ListLogs::route('/'),
            'create' => CreateLog::route('/create'),
            'view' => ViewLog::route('/{record}'),
            'edit' => EditLog::route('/{record}/edit'),
        ];
    }
}