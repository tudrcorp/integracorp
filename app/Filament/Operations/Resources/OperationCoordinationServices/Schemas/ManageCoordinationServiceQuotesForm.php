<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Schemas;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceQuotes;
use App\Models\OperationInventoryUbication;
use App\Models\OperationQuoteGenerator;
use App\Models\TelemedicinePriority;
use App\Support\Operations\CoordinationServiceQuoteManager;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class ManageCoordinationServiceQuotesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('page_context')
                    ->hiddenLabel()
                    ->content(fn (ManageCoordinationServiceQuotes $livewire): HtmlString => CoordinationServiceQuoteManager::contextHeader($livewire->getRecord()))
                    ->columnSpanFull(),
                Placeholder::make('quotes_summary')
                    ->label('Resumen de cotizaciones')
                    ->content(fn (ManageCoordinationServiceQuotes $livewire): HtmlString => CoordinationServiceQuoteManager::renderCoordinationQuotesSummary($livewire->getRecord()))
                    ->columnSpanFull(),
                Section::make('Selección para aprobar')
                    ->description('Marque una o varias cotizaciones pendientes. Al seleccionarlas se habilitará el detalle y la orden de servicio.')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->iconColor('success')
                    ->visible(fn (ManageCoordinationServiceQuotes $livewire): bool => CoordinationServiceQuoteManager::hasPendingQuotesForApproval($livewire->getRecord()))
                    ->schema([
                        Hidden::make('selected_pending_quote_ids')
                            ->default([])
                            ->dehydrated()
                            ->columnSpanFull(),
                        View::make('filament.operations.partials.pending-quotes-selection')
                            ->viewData(fn (ManageCoordinationServiceQuotes $livewire): array => [
                                'quotes' => CoordinationServiceQuoteManager::pendingQuotesForApproval($livewire->getRecord()),
                                'selectedIds' => array_map(
                                    intval(...),
                                    (array) ($livewire->data['selected_pending_quote_ids'] ?? [])
                                ),
                            ])
                            ->columnSpanFull(),
                        Placeholder::make('pending_quotes_selection_hint')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString(
                                '<p class="text-xs text-gray-500 dark:text-gray-400">Puede aprobar varias cotizaciones del mismo tipo de servicio en un solo guardado. Use <strong>Editar</strong> para ajustar una cotización antes de seleccionarla.</p>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Repeater::make('quote_statuses')
                    ->label('Detalle y estatus')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->schema([
                        Hidden::make('quote_id'),
                        Hidden::make('has_service_order'),
                        Hidden::make('status_locked'),
                        Placeholder::make('quote_repeater_item_marker')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString('<span class="fi-quote-repeater-item-active" aria-hidden="true"></span>'))
                            ->visible(fn (Get $get, ManageCoordinationServiceQuotes $livewire): bool => CoordinationServiceQuoteManager::shouldShowQuoteInManagementRepeater(
                                $get,
                                $livewire->data['selected_pending_quote_ids'] ?? []
                            ))
                            ->columnSpanFull(),
                        Placeholder::make('quote_preview')
                            ->hiddenLabel()
                            ->visible(fn (Get $get, ManageCoordinationServiceQuotes $livewire): bool => CoordinationServiceQuoteManager::shouldShowQuoteInManagementRepeater(
                                $get,
                                $livewire->data['selected_pending_quote_ids'] ?? []
                            ))
                            ->content(function (Get $get): HtmlString {
                                $quote = OperationQuoteGenerator::query()
                                    ->with('supplier')
                                    ->find((int) $get('quote_id'));

                                if (! $quote instanceof OperationQuoteGenerator) {
                                    return new HtmlString(
                                        '<div class="rounded-xl border border-dashed border-gray-300/80 px-4 py-3 text-sm text-gray-600 dark:border-white/15 dark:text-gray-300">Cotización no disponible.</div>'
                                    );
                                }

                                return CoordinationServiceQuoteManager::renderOperationQuotePreview($quote);
                            })
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Estatus')
                            ->options(OperationQuoteGenerator::statusOptions())
                            ->required()
                            ->native(false)
                            ->live()
                            ->visible(fn (Get $get, ManageCoordinationServiceQuotes $livewire): bool => CoordinationServiceQuoteManager::shouldShowQuoteInManagementRepeater(
                                $get,
                                $livewire->data['selected_pending_quote_ids'] ?? []
                            ))
                            ->disabled(fn (Get $get): bool => (bool) $get('has_service_order') || (bool) $get('status_locked'))
                            ->helperText(fn (Get $get): ?string => match (true) {
                                (bool) $get('status_locked') => 'El estatus de esta cotización ya fue definido y no puede modificarse.',
                                (bool) $get('has_service_order') => 'Esta cotización ya tiene una orden de servicio vinculada.',
                                (string) $get('status') === OperationQuoteGenerator::STATUS_PRIVATE_CARE => 'Atención particular: finaliza la cotización y los ítems sin generar orden de servicio.',
                                default => 'También puede rechazarla o marcarla como atención particular; se quitará de la selección pendiente.',
                            })
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ManageCoordinationServiceQuotes $livewire): void {
                                $quoteId = (int) $get('quote_id');

                                CoordinationServiceQuoteManager::syncSelectedPendingQuotesFromStatusChange(
                                    $get,
                                    $set,
                                    $quoteId,
                                    $state
                                );

                                if ($state !== OperationQuoteGenerator::STATUS_APPROVED) {
                                    $set('is_cash', false);
                                }

                                if ($state === OperationQuoteGenerator::STATUS_PRIVATE_CARE) {
                                    return;
                                }

                                if ($state !== OperationQuoteGenerator::STATUS_APPROVED) {
                                    return;
                                }

                                CoordinationServiceQuoteManager::prefillServiceOrderFormFromQuote(
                                    $set,
                                    $livewire->getRecord(),
                                    $quoteId
                                );
                            }),
                        Checkbox::make('is_cash')
                            ->label('Pago de contado')
                            ->helperText('Debe cancelarse de inmediato. Se notificará a administración por correo y WhatsApp al guardar.')
                            ->default(false)
                            ->dehydrated()
                            ->visible(fn (Get $get, ManageCoordinationServiceQuotes $livewire): bool => (string) $get('status') === OperationQuoteGenerator::STATUS_APPROVED
                                && CoordinationServiceQuoteManager::shouldShowQuoteInManagementRepeater(
                                    $get,
                                    $livewire->data['selected_pending_quote_ids'] ?? []
                                ))
                            ->disabled(fn (Get $get): bool => (bool) $get('has_service_order') || (bool) $get('status_locked')),
                    ])
                    ->columnSpanFull(),
                Section::make('Orden de servicio')
                    ->description('Complete los datos operativos. El proveedor se tomará de la cotización aprobada.')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->iconColor('success')
                    ->visible(fn (Get $get): bool => CoordinationServiceQuoteManager::hasApprovedQuotePendingOrderInForm($get('quote_statuses')))
                    ->schema([
                        Placeholder::make('multi_order_notice')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => CoordinationServiceQuoteManager::multiOrderCreationNotice($get))
                            ->visible(fn (Get $get): bool => count(CoordinationServiceQuoteManager::approvedQuoteIdsPendingOrderInForm($get('quote_statuses'))) > 1)
                            ->columnSpanFull(),
                        Hidden::make('approved_quote_id'),
                        Placeholder::make('approved_quote_notice')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => CoordinationServiceQuoteManager::approvedQuoteOrderNotice((int) $get('approved_quote_id')))
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('order_number')
                                    ->label('Número de orden')
                                    ->required(fn (Get $get): bool => CoordinationServiceQuoteManager::hasApprovedQuotePendingOrderInForm($get('quote_statuses')))
                                    ->prefixIcon(Heroicon::OutlinedHashtag)
                                    ->maxLength(255),
                                Select::make('telemedicine_priority_id')
                                    ->label('Prioridad')
                                    ->options(TelemedicinePriority::query()->orderBy('name', 'asc')->pluck('name', 'id'))
                                    ->required(fn (Get $get): bool => CoordinationServiceQuoteManager::hasApprovedQuotePendingOrderInForm($get('quote_statuses')))
                                    ->prefixIcon(Heroicon::OutlinedBolt)
                                    ->native(false),
                                Select::make('operation_inventory_ubication_id')
                                    ->label('Ubicación inventario (medicamentos)')
                                    ->options(OperationInventoryUbication::query()->where('is_active', true)->orderBy('name', 'asc')->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->prefixIcon(Heroicon::OutlinedMapPin)
                                    ->visible(fn (Get $get): bool => CoordinationServiceQuoteManager::approvedQuoteServiceType((int) $get('approved_quote_id')) === 'MEDICAMENTOS')
                                    ->native(false)
                                    ->columnSpanFull(),
                                TextInput::make('service_order_description')
                                    ->label('Descripción de la orden')
                                    ->required(fn (Get $get): bool => CoordinationServiceQuoteManager::hasApprovedQuotePendingOrderInForm($get('quote_statuses')))
                                    ->prefixIcon(Heroicon::OutlinedDocumentText)
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                Textarea::make('service_order_observations')
                                    ->label('Observaciones de la orden')
                                    ->rows(3)
                                    ->maxLength(2000)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
