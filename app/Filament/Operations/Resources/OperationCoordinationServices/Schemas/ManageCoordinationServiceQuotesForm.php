<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Schemas;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceQuotes;
use App\Models\OperationInventoryUbication;
use App\Models\OperationQuoteGenerator;
use App\Models\TelemedicinePriority;
use App\Support\Operations\CoordinationServiceQuoteManager;
use App\Support\Operations\OperationServiceOrderProviderFormFields;
use App\Support\Operations\OperationServiceOrderUnregisteredProviderFormFields;
use Filament\Forms\Components\CheckboxList;
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
                        CheckboxList::make('selected_pending_quote_ids')
                            ->label('Cotizaciones pendientes')
                            ->options(fn (ManageCoordinationServiceQuotes $livewire): array => CoordinationServiceQuoteManager::pendingQuoteApprovalOptions($livewire->getRecord()))
                            ->descriptions(fn (ManageCoordinationServiceQuotes $livewire): array => CoordinationServiceQuoteManager::pendingQuoteApprovalDescriptions($livewire->getRecord()))
                            ->bulkToggleable()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (ManageCoordinationServiceQuotes $livewire, Get $get, Set $set, mixed $state): void {
                                CoordinationServiceQuoteManager::syncQuoteStatusesFromPendingSelection(
                                    $get,
                                    $set,
                                    $livewire->getRecord()
                                );
                            })
                            ->columns(1)
                            ->helperText('Puede aprobar varias cotizaciones del mismo tipo de servicio en un solo guardado.')
                            ->extraAttributes([
                                'class' => 'fi-coordination-manage-quotes-checkbox-list',
                            ])
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
                                $quote = OperationQuoteGenerator::query()->find((int) $get('quote_id'));

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
                            ->disabled(fn (Get $get): bool => (bool) $get('has_service_order'))
                            ->helperText(fn (Get $get): ?string => (bool) $get('has_service_order')
                                ? 'Esta cotización ya tiene una orden de servicio vinculada.'
                                : 'También puede rechazarla aquí; se quitará de la selección pendiente.')
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ManageCoordinationServiceQuotes $livewire): void {
                                $quoteId = (int) $get('quote_id');

                                CoordinationServiceQuoteManager::syncSelectedPendingQuotesFromStatusChange(
                                    $get,
                                    $set,
                                    $quoteId,
                                    $state
                                );

                                if ($state !== OperationQuoteGenerator::STATUS_APPROVED) {
                                    return;
                                }

                                CoordinationServiceQuoteManager::prefillServiceOrderFormFromQuote(
                                    $set,
                                    $livewire->getRecord(),
                                    $quoteId
                                );
                            }),
                    ])
                    ->columnSpanFull(),
                Section::make('Orden de servicio')
                    ->description('Complete los datos operativos para la cotización aprobada.')
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
                        ...OperationServiceOrderProviderFormFields::selectionComponents(),
                        ...OperationServiceOrderUnregisteredProviderFormFields::inlineRegistrationSchema(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
