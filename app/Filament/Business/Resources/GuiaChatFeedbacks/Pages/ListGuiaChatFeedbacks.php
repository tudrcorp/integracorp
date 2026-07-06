<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\GuiaChatFeedbacks\Pages;

use App\Filament\Business\Resources\GuiaChatFeedbacks\GuiaChatFeedbackResource;
use App\Filament\Business\Resources\GuiaChatFeedbacks\Tables\GuiaChatFeedbacksTable;
use App\Support\GuiaChat\GuiaChatFeedbackType;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListGuiaChatFeedbacks extends ListRecords
{
    protected static string $resource = GuiaChatFeedbackResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge(GuiaChatFeedbacksTable::countByType())
                ->badgeColor('gray'),
            'suggestions' => Tab::make('Sugerencias')
                ->badge(GuiaChatFeedbacksTable::countByType(GuiaChatFeedbackType::ServiceSuggestion))
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(
                    'type',
                    GuiaChatFeedbackType::ServiceSuggestion->value,
                )),
            'guia_chat' => Tab::make('Fallas GUIA-CHAT')
                ->badge(GuiaChatFeedbacksTable::countByType(GuiaChatFeedbackType::GuiaChatBug))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(
                    'type',
                    GuiaChatFeedbackType::GuiaChatBug->value,
                )),
            'integracorp' => Tab::make('Fallas INTEGRACORP')
                ->badge(GuiaChatFeedbacksTable::countByType(GuiaChatFeedbackType::IntegracorpBug))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(
                    'type',
                    GuiaChatFeedbackType::IntegracorpBug->value,
                )),
        ];
    }
}
