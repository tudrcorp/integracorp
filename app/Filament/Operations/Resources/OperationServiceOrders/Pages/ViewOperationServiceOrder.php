<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Pages;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Mail\OperationServiceOrderPdfMail;
use App\Models\OperationDocumentList;
use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderCoordinationSync;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Operations\OperationServiceOrderViewActions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
                    ->visible(fn (): bool => OperationServiceOrderViewActions::canFinalize($this->getRecord())),
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
        return [
            Repeater::make('documents')
                ->label('Documentos')
                ->defaultItems(1)
                ->addActionLabel('Agregar documento')
                ->reorderable()
                ->minItems(1)
                ->schema([
                    Select::make('document_type_ids')
                        ->label('Tipo(s) de documento')
                        ->helperText('Seleccione uno o varios tipos según la información contenida en el archivo.')
                        ->options(fn (): array => OperationDocumentList::query()
                            ->orderBy('name', 'asc')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->multiple()
                        ->required(),
                    FileUpload::make('document_file')
                        ->label('Archivo')
                        ->directory(fn () => 'operation-service-orders/'.$this->getRecord()->id.'/documents')
                        ->preserveFilenames()
                        ->required()
                        ->maxSize(10240),
                ])
                ->columns(1)
                ->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $arguments
     */
    private function processOrderDocumentsUpload(array $data, array $arguments): void
    {
        $newDocuments = $this->buildUploadedOrderDocumentsFromForm($data);

        if ($newDocuments === []) {
            Notification::make()
                ->warning()
                ->title('Sin documentos válidos')
                ->body('Debe cargar al menos un documento con archivo y tipos seleccionados.')
                ->send();

            return;
        }

        $record = $this->getRecord();
        $existingDocuments = is_array($record->uploaded_documents)
            ? $record->uploaded_documents
            : [];

        $record->update([
            'uploaded_documents' => array_values(array_merge($existingDocuments, $newDocuments)),
        ]);

        $shouldFinalize = filter_var($arguments['finalize'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($shouldFinalize) {
            if (! OperationServiceOrderViewActions::canFinalize($record)) {
                Notification::make()
                    ->warning()
                    ->title('No se puede finalizar')
                    ->body('Los documentos se guardaron, pero la orden ya no admite finalización.')
                    ->send();

                return;
            }

            $this->record = OperationServiceOrderCoordinationSync::finalizeOrder($record->fresh() ?? $record);

            Notification::make()
                ->success()
                ->title('Orden finalizada')
                ->body('Se guardaron los documentos y la orden #'.($record->order_number ?: $record->getKey()).' quedó en estatus FINALIZADO.')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Documentos cargados')
            ->body(count($newDocuments) > 1
                ? 'Se cargaron '.count($newDocuments).' documentos en la orden.'
                : 'Se cargó 1 documento en la orden.')
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{document_name: string, file_path: string, document_type_ids: list<int>, document_types: list<string>, uploaded_at: string}>
     */
    private function buildUploadedOrderDocumentsFromForm(array $data): array
    {
        /** @var array<int, string> $documentTypeNames */
        $documentTypeNames = OperationDocumentList::query()
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();

        return collect($data['documents'] ?? [])
            ->map(function (mixed $item) use ($documentTypeNames): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $documentFile = trim((string) ($item['document_file'] ?? ''));

                if ($documentFile === '') {
                    return null;
                }

                $documentName = trim((string) pathinfo($documentFile, PATHINFO_FILENAME));

                if ($documentName === '') {
                    $documentName = basename($documentFile);
                }

                $rawTypeIds = $item['document_type_ids'] ?? [];

                $typeIds = collect(is_array($rawTypeIds) ? $rawTypeIds : [])
                    ->map(static fn (mixed $value, mixed $key): int => is_numeric($value)
                        ? (int) $value
                        : (is_numeric($key) ? (int) $key : 0))
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $typeNames = collect($typeIds)
                    ->map(static fn (int $id): string => $documentTypeNames[$id] ?? '')
                    ->filter(static fn (string $value): bool => $value !== '')
                    ->values()
                    ->all();

                return [
                    'document_name' => $documentName,
                    'file_path' => $documentFile,
                    'document_type_ids' => $typeIds,
                    'document_types' => $typeNames,
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function cancelServiceOrderAction(): Action
    {
        return OperationServiceOrderViewActions::makeCancelAction();
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
