<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\GuiaChatFeedbacks;

use App\Filament\Business\Resources\GuiaChatFeedbacks\Pages\ListGuiaChatFeedbacks;
use App\Filament\Business\Resources\GuiaChatFeedbacks\Pages\ViewGuiaChatFeedback;
use App\Filament\Business\Resources\GuiaChatFeedbacks\Schemas\GuiaChatFeedbackInfolist;
use App\Filament\Business\Resources\GuiaChatFeedbacks\Tables\GuiaChatFeedbacksTable;
use App\Models\GuiaChatFeedback;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class GuiaChatFeedbackResource extends Resource
{
    protected static ?string $model = GuiaChatFeedback::class;

    protected static ?string $slug = 'guia-chat-feedbacks';

    protected static ?string $navigationLabel = 'Guia-Chat';

    protected static ?string $modelLabel = 'registro GUIA-CHAT';

    protected static ?string $pluralModelLabel = 'registros GUIA-CHAT';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 95;

    public static function infolist(Schema $schema): Schema
    {
        return GuiaChatFeedbackInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuiaChatFeedbacksTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->check();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuiaChatFeedbacks::route('/'),
            'view' => ViewGuiaChatFeedback::route('/{record}'),
        ];
    }
}
