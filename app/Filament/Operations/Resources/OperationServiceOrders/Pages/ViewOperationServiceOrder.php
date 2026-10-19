<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Pages;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Mail\OperationServiceOrderPdfMail;
use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\Operations\OperationServiceOrderDocuments;
use App\Support\Operations\OperationServiceOrderFinalizationException;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Operations\OperationServiceOrderViewActions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Throwable;

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

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray '.self::IOS_BUTTON_TAIL;

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger '.self::IOS_BUTTON_TAIL;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        OperationServiceOrderValidity::expireIfNeeded($this->getRecord(), Auth::user()?->name ?? 'system');
        $this->record = $this->getRecord()->fresh();
    }

    public function getPageClasses(): array
    {
        return [
            'fi-resource-view-record-page',
            'fi-resource-'.str_replace('/', '-', static::$resource::getSlug(Filament::getCurrentOrDefaultPanel())),
            'fi-resource-record-'.$this->getRecord()->getKey(),
            'operation-service-order-view-page',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ])
                ->url(OperationServiceOrderResource::getUrl()),

            // $this->configureOrderDocumentsModalAction(
            //     Action::make('finalize_service_order')
            //         ->label('Finalizar orden')
            //         ->icon('heroicon-o-check-badge')
            //         ->color('danger')
            //         ->button()
            //         ->modalIcon('heroicon-o-check-badge')
            //         ->modalIconColor('danger')
            //         ->extraAttributes([
            //             'x-on:click.stop' => '',
            //             'class' => self::IOS_DANGER_BUTTON_CLASS,
            //         ])
            //         ->visible(fn (): bool => OperationServiceOrderViewActions::canFinalize($this->getRecord())),
            // ),

            ActionGroup::make([
                Action::make('preview_quote_pdf')
                    ->label('Cotización PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->modalHeading('Vista previa de cotización')
                    ->modalDescription('Visualiza el PDF de la cotización asociada sin salir de la orden.')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalIcon('heroicon-o-eye')
                    ->modalContent(function (): ViewContract {
                        $pdfPath = (string) ($this->getRecord()->associated_quote_pdf_path ?? '');
                        $previewUrl = URL::to(Storage::url($pdfPath));

                        return View::make('filament.operations.operation-service-orders.pdf-preview', [
                            'pdfPreviewUrl' => $previewUrl,
                            'pdfDownloadUrl' => $previewUrl,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->visible(fn (): bool => filled($this->getRecord()->associated_quote_pdf_path))
                    ->action(fn () => null),

                Action::make('preview_operation_pdf')
                    ->label('Orden de servicio PDF')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('info')
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
            ])
                ->label('PDFs y envío')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->button()
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => self::IOS_INFO_BUTTON_CLASS,
                ]),

            $this->configureOrderDocumentsModalAction(
                Action::make('upload_order_documents')
                    ->label('Cargar documentos')
                    ->icon('heroicon-o-paper-clip')
                    ->color('warning')
                    ->button()
                    ->extraAttributes([
                        'x-on:click.stop' => '',
                        'class' => self::IOS_INFO_BUTTON_CLASS,
                    ]),
            ),
        ];
    }

    private function configureOrderDocumentsModalAction(Action $action): Action
    {
        return $action
            ->modalHeading('Cargar documentos de la orden')
            ->modalDescription('Agregue uno o varios documentos. Cada archivo puede incluir uno o varios tipos de documento.')
            ->modalWidth(Width::FourExtraLarge)
            ->form($this->orderDocumentsUploadForm())
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelActionLabel('Cancelar')
            ->extraModalFooterActions(fn (Action $parent): array => [
                $parent->makeModalSubmitAction('save_and_finalize_order_documents', arguments: ['finalize' => true])
                    ->label('Guardar y Finalizar orden')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (): bool => OperationServiceOrderViewActions::canFinalize($this->getRecord())
                        && OperationServiceOrderDocuments::userCanFinalize()),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->processOrderDocumentsUpload($data, $arguments);
            });
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private function orderDocumentsUploadForm(): array
    {
        return OperationServiceOrderDocuments::formSchema(
            fn (): string => OperationServiceOrderDocuments::directory($this->getRecord()),
        );
    }

    /**
     * Guarda y, si se pidió, finaliza por {@see OperationServiceOrderDocuments}:
     * la misma vía que la ficha del proveedor, con bitácora y auditoría.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $arguments
     */
    private function processOrderDocumentsUpload(array $data, array $arguments): void
    {
        $newDocuments = OperationServiceOrderDocuments::fromForm($data);
        $record = $this->getRecord();
        $orderLabel = OperationServiceOrderDocuments::orderLabel($record);
        $user = Auth::user();

        try {
            if (filter_var($arguments['finalize'] ?? false, FILTER_VALIDATE_BOOLEAN) && $user instanceof User) {
                $result = OperationServiceOrderDocuments::finalize($record, $newDocuments, $user, OperationServiceOrderDocuments::ORIGIN_ORDER_VIEW);
                $this->record = $result['order'];

                Notification::make()
                    ->success()
                    ->title('Orden finalizada')
                    ->body('Se guardaron los documentos y la orden '.$orderLabel.' quedó en estatus FINALIZADO.'
                        .($result['logged_to_case'] ? ' Quedó registrado en la bitácora del caso.' : '')
                        .$result['follow_up_suffix'])
                    ->send();

                return;
            }

            $result = OperationServiceOrderDocuments::attach($record, $newDocuments);
            $this->record = $result['order'];
        } catch (OperationServiceOrderFinalizationException $exception) {
            Notification::make()
                ->warning()
                ->title('No se pudo completar')
                ->body($exception->getMessage())
                ->persistent()
                ->send();

            return;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title('No se guardaron los documentos')
                ->body('Ocurrió un error y no se aplicó ningún cambio en la orden. Intente nuevamente; si persiste, avise a soporte.')
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Documentos cargados')
            ->body((count($newDocuments) > 1
                ? 'Se cargaron '.count($newDocuments).' documentos en la orden.'
                : 'Se cargó 1 documento en la orden.').$result['follow_up_suffix'])
            ->send();
    }

    public function cancelServiceOrderAction(): Action
    {
        return OperationServiceOrderViewActions::makeCancelAction();
    }

    public function voidServiceOrderForRegenerationAction(): Action
    {
        return OperationServiceOrderViewActions::makeVoidForRegenerationAction();
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $operationServiceOrder = $this->getRecord();
        $operationServiceOrder->loadMissing('operationCoordinationService');

        $status = strtoupper((string) ($operationServiceOrder->status ?? ''));
        $badgeStyle = $this->badgeStyleForStatus($status);
        $vigenciaHtml = $this->renderVigenciaHeaderPill($operationServiceOrder);

        return new \Illuminate\Support\HtmlString(
            '<div class="flex flex-col gap-2 py-2">'
                .'<span class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">'
                .'Orden de servicio · '.e($operationServiceOrder->order_number)
                .'</span>'
                .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-3xl">'
                .e($operationServiceOrder->service_type)
                .'</span>'
                .'<div class="flex flex-wrap items-center gap-2">'
                .'<span style="'
                .'background-color: '.$badgeStyle['bg'].'; '
                .'color: #ffffff; '
                .'padding: 4px 12px; '
                .'border-radius: 50px; '
                .'font-size: 0.75rem; '
                .'font-weight: 700; '
                .'display: inline-flex; '
                .'align-items: center; '
                .'gap: 6px; '
                .'box-shadow: '.$badgeStyle['shadow'].'; '
                .'border: 1px solid rgba(255, 255, 255, 0.2);">'
                .'<span style="font-size: 10px;">●</span> '.e($status ?: 'Sin estado')
                .'</span>'
                .$vigenciaHtml
                .'</div>'
                .'</div>'
        );
    }

    private function renderVigenciaHeaderPill(OperationServiceOrder $order): string
    {
        if (! OperationServiceOrderValidity::shouldHighlightVigencia($order)) {
            return '';
        }

        $shortLabel = OperationServiceOrderValidity::vigenciaShortLabel($order) ?? '';
        $tone = OperationServiceOrderValidity::vigenciaTone($order) ?? 'info';

        $style = match ($tone) {
            'danger' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 14px rgba(255, 59, 48, 0.4)',
            ],
            'warning' => [
                'bg' => '#ff9500',
                'shadow' => '0 4px 14px rgba(255, 149, 0, 0.38)',
            ],
            default => [
                'bg' => '#0ea5e9',
                'shadow' => '0 4px 14px rgba(14, 165, 233, 0.35)',
            ],
        };

        return '<span style="'
            .'background-color: '.$style['bg'].'; '
            .'color: #ffffff; '
            .'padding: 4px 12px; '
            .'border-radius: 50px; '
            .'font-size: 0.75rem; '
            .'font-weight: 700; '
            .'display: inline-flex; '
            .'align-items: center; '
            .'gap: 6px; '
            .'box-shadow: '.$style['shadow'].'; '
            .'border: 1px solid rgba(255, 255, 255, 0.22);">'
            .'<span style="font-size: 10px;">⏱</span> '.e($shortLabel)
            .'</span>';
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
            'CADUCADA' => [
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
