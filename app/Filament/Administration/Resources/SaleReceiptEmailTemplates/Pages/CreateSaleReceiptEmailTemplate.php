<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\SaleReceiptEmailTemplates\Pages;

use App\Enums\DocumentEmailType;
use App\Filament\Administration\Resources\SaleReceiptEmailTemplates\SaleReceiptEmailTemplateResource;
use App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplateDestinations;
use App\Filament\Shared\DocumentEmailTemplates\DocumentEmailTemplatePageHeader;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class CreateSaleReceiptEmailTemplate extends CreateRecord
{
    protected static string $resource = SaleReceiptEmailTemplateResource::class;

    protected static ?string $title = 'Nuevo copy de correo';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public function getSubheading(): string|Htmlable|null
    {
        return DocumentEmailTemplatePageHeader::forCreate(SaleReceiptEmailTemplateResource::DOCUMENT);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(SaleReceiptEmailTemplateResource::getUrl('index'))
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ], merge: true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $document = DocumentEmailTemplateDestinations::allowedDocument($data, SaleReceiptEmailTemplateResource::DOCUMENT, null);

        if ($document === null) {
            Notification::make()
                ->title('No puede guardar el copy ahí')
                ->body('No tiene permiso para gestionar copys del documento elegido en «Se usará en».')
                ->danger()
                ->send();

            $this->halt();
        }

        unset($data['variables_help']);
        $data['document'] = $document->value;
        $data['created_by'] = Auth::user()?->name;
        $data['updated_by'] = Auth::user()?->name;

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Copy creado';
    }

    protected function afterCreate(): void
    {
        $this->notifyIfMoved();
    }

    protected function getRedirectUrl(): string
    {
        return DocumentEmailTemplateDestinations::indexUrlFor($this->savedDocument(), SaleReceiptEmailTemplateResource::getUrl('index'));
    }

    private function savedDocument(): DocumentEmailType
    {
        return $this->record->document ?? SaleReceiptEmailTemplateResource::DOCUMENT;
    }

    private function notifyIfMoved(): void
    {
        $message = DocumentEmailTemplateDestinations::savedMessage($this->savedDocument(), SaleReceiptEmailTemplateResource::DOCUMENT);

        if ($message !== null) {
            Notification::make()->title('Copy movido de pantalla')->body($message)->info()->send();
        }
    }
}
