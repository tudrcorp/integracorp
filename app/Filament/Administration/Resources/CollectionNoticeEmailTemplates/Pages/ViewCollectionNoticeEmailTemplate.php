<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\CollectionNoticeEmailTemplates\Pages;

use App\Filament\Administration\Resources\CollectionNoticeEmailTemplates\CollectionNoticeEmailTemplateResource;
use App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplatePageHeader;
use App\Models\DocumentEmailTemplate;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewCollectionNoticeEmailTemplate extends ViewRecord
{
    protected static string $resource = CollectionNoticeEmailTemplateResource::class;

    protected static ?string $title = 'Ficha del copy de correo';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public function getSubheading(): string|Htmlable|null
    {
        /** @var DocumentEmailTemplate $record */
        $record = $this->getRecord();

        return DocumentEmailTemplatePageHeader::forRecord($record, 'Ficha del copy');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(CollectionNoticeEmailTemplateResource::getUrl('index'))
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ], merge: true),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-m-pencil-square')
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ], merge: true),
        ];
    }
}
