<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Pages;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Mail\OperationServiceOrderPdfMail;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class ViewOperationServiceOrder extends ViewRecord
{
    protected static string $resource = OperationServiceOrderResource::class;

    protected static ?string $title = 'Ficha Técnica de la Orden de Servicio';

    /**
     * Misma estructura que {@see \App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers::TICKET_BUTTON_CLASS};
     * la clase theme (aviso-btn-ios-*, ticket-btn-ios-gray) va acorde a ->color().
     */
    private const IOS_BUTTON_TAIL = 'shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_INFO_BUTTON_CLASS = 'aviso-btn-ios-info '.self::IOS_BUTTON_TAIL;

    private const IOS_SUCCESS_BUTTON_CLASS = 'aviso-btn-ios-success '.self::IOS_BUTTON_TAIL;

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray '.self::IOS_BUTTON_TAIL;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview_operation_pdf')
                ->label('Vista previa PDF')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->button()
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => self::IOS_INFO_BUTTON_CLASS,
                ])
                ->modalHeading('Orden de servicio en PDF')
                ->modalDescription('Visualice el documento corporativo antes de descargarlo o compartirlo.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon('heroicon-o-eye')
                ->modalContent(fn (): ViewContract => View::make('filament.operations.operation-service-orders.pdf-preview', [
                    'pdfPreviewUrl' => route('operations.operation-service-orders.pdf.preview', ['operationServiceOrder' => $this->getRecord()]),
                    'pdfDownloadUrl' => route('operations.operation-service-orders.pdf', ['operationServiceOrder' => $this->getRecord()]),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null),

            Action::make('email_operation_pdf')
                ->label('Enviar por correo')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->button()
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ])
                ->form([
                    TextInput::make('email')
                        ->label('Correo del destinatario')
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    Mail::to($data['email'])->send(new OperationServiceOrderPdfMail($this->getRecord()));

                    Notification::make()
                        ->success()
                        ->title('Correo enviado')
                        ->body('Se adjuntó el PDF de la orden de servicio al mensaje.')
                        ->send();
                }),

            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ])
                ->url(OperationServiceOrderResource::getUrl()),
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $operationServiceOrder = $this->getRecord();
        $operationServiceOrder->loadMissing('operationCoordinationService');

        $fullName = $operationServiceOrder->operationCoordinationService?->patient
            ?? $operationServiceOrder->operationCoordinationService?->holder
            ?? 'Sin Nombre';
        $status = strtoupper((string) ($operationServiceOrder->status ?? ''));
        $badgeStyle = $this->badgeStyleForStatus($status);

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">'
                .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white mb-2">'
                .'ORDEN DE SERVICIO: '.e($operationServiceOrder->order_number)
                .'</span>'
                .'<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white mb-2">'
                .'Servicio: '.e($operationServiceOrder->service_type)
                .'</span>'
                .'<div style="display: flex; align-items: center; margin-top: 8px;">'
                .'<span style="'
                .'background-color: '.$badgeStyle['bg'].'; '
                .'color: #ffffff; '
                .'padding: 6px 16px; '
                .'border-radius: 50px; '
                .'font-size: 0.8rem; '
                .'font-weight: 700; '
                .'display: inline-flex; '
                .'align-items: center; '
                .'gap: 6px; '
                .'box-shadow: '.$badgeStyle['shadow'].'; '
                .'border: 1px solid rgba(255, 255, 255, 0.2);">'
                .'<span style="font-size: 10px;">●</span> '.e($status ?: 'Sin estado')
                .'</span>'
                .'</div>'
                .'</div>'
        );
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private function badgeStyleForStatus(string $status): array
    {
        return match ($status) {
            'EN GESTION' => [
                'bg' => '#ffc107',
                'shadow' => '0 4px 12px rgba(255, 193, 7, 0.35)',
            ],
            'CANCELADA' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            'FINALIZADO' => [
                'bg' => '#28cd41',
                'shadow' => '0 4px 12px rgba(40, 205, 65, 0.35)',
            ],
            'PENDIENTE' => [
                'bg' => '#ffcc00',
                'shadow' => '0 4px 12px rgba(255, 204, 0, 0.35)',
            ],
            'PENDIENTE POR RESULTADOS' => [
                'bg' => '#ffcc00',
                'shadow' => '0 4px 12px rgba(255, 204, 0, 0.35)',
            ],
            'NOVEDAD ADMON' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            default => [
                'bg' => '#8e8e93',
                'shadow' => '0 4px 12px rgba(142, 142, 147, 0.35)',
            ],
        };
    }
}
