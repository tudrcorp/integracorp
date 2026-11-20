<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\QuoteDesigns\Pages;

use App\Filament\Business\Resources\QuoteDesigns\QuoteDesignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListQuoteDesigns extends ListRecords
{
    protected static string $resource = QuoteDesignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo diseño')
                ->icon(Heroicon::Plus),
        ];
    }
}
