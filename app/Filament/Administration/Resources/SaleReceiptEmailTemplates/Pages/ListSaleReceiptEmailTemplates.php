<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\SaleReceiptEmailTemplates\Pages;

use App\Filament\Administration\Resources\SaleReceiptEmailTemplates\SaleReceiptEmailTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSaleReceiptEmailTemplates extends ListRecords
{
    protected static string $resource = SaleReceiptEmailTemplateResource::class;

    protected static ?string $title = 'Copys de recibos de pago';

    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Agregar copy')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ]),
        ];
    }
}
