<?php

namespace App\Filament\Administration\Resources\Sales\Tables;

use App\Filament\Administration\Resources\Sales\SaleResource;
use App\Filament\Exports\SaleExporter;
use App\Http\Controllers\LogController;
use App\Http\Controllers\SaleController;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Sale;
use App\Support\Affiliation\AffiliationDocumentAffiliatesCount;
use App\Support\Filament\Administration\SaleReciboPagoEmailRecipients;
use App\Support\Filament\Administration\SaleReciboPagoTestDeliveryForm;
use App\Support\Filament\Administration\SaleReciboPagoWhatsAppRecipients;
use App\Support\SecurityAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Throwable;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('VENTAS')
            ->description('Registro de pagos(ventas) de afiliaciones activas')
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Sale $record): string => SaleResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('created_at')
                    ->sortable()
                    ->label('Fecha')
                    ->dateTime()
                    ->badge()
                    ->color('verde')
                    ->icon('heroicon-s-calendar-days')
                    ->searchable(),
                TextColumn::make('invoice_number')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-s-document-text')
                    ->label('Nro. Recibo de Pago')
                    ->searchable()
                    ->extraAttributes([
                        'class' => 'cursor-pointer',
                    ])
                    ->tooltip('Clic para ver detalle de la venta'),
                TextColumn::make('affiliation_code')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-s-user-group')
                    ->label('Afiliación')
                    ->searchable(),
                TextColumn::make('type')
                    ->sortable()
                    ->label('Tipo')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'AFILIACION INDIVIDUAL' => 'primary',
                            'AFILIACION CORPORATIVA' => 'verdeOpaco',
                        };
                    })
                    ->searchable(),
                TextColumn::make('agency.name_corporative')
                    ->sortable()
                    ->label('Agencia')
                    ->badge()
                    ->color('verde')
                    ->icon('heroicon-s-building-library')
                    ->searchable()
                    ->extraAttributes([
                        'class' => 'cursor-pointer',
                    ])
                    ->tooltip('Clic para ver ficha de la agencia')
                    ->action(
                        Action::make('viewAgencyProfile')
                            ->label('Ver ficha de agencia')
                            ->icon('heroicon-o-building-office-2')
                            ->color('info')
                            ->modalWidth(Width::ThreeExtraLarge)
                            ->modalHeading(fn (Sale $record): string => 'Agencia · '.($record->agency?->name_corporative ?? 'Sin información'))
                            ->modalDescription('Información principal para validación rápida de la gestión comercial.')
                            ->modalContent(function (Sale $record): ViewContract {
                                $agency = $record->agency?->loadMissing(['country', 'state', 'city']);

                                return view('filament.administration.sales.modals.agency-profile-modal', [
                                    'record' => $record,
                                    'agency' => $agency,
                                ]);
                            })
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                    ),
                TextColumn::make('agent.name')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-m-user')
                    ->label('Agente')
                    ->numeric()
                    ->searchable()
                    ->extraAttributes([
                        'class' => 'cursor-pointer',
                    ])
                    ->tooltip('Clic para ver ficha del agente')
                    ->action(
                        Action::make('viewAgentProfile')
                            ->label('Ver ficha de agente')
                            ->icon('heroicon-o-user-circle')
                            ->color('info')
                            ->modalWidth(Width::ThreeExtraLarge)
                            ->modalHeading(fn (Sale $record): string => 'Agente · '.($record->agent?->name ?? 'Sin información'))
                            ->modalDescription('Resumen del agente asociado a la venta para consulta operativa.')
                            ->modalContent(function (Sale $record): ViewContract {
                                $agent = $record->agent?->loadMissing(['agency', 'country', 'state', 'city', 'typeAgent']);

                                return view('filament.administration.sales.modals.agent-profile-modal', [
                                    'record' => $record,
                                    'agent' => $agent,
                                ]);
                            })
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                    ),
                TextColumn::make('plan.description')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-s-cube')
                    ->color('verde')
                    ->label('Plan')
                    ->numeric()
                    ->searchable(),
                TextColumn::make('coverage.price')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-s-cube')
                    ->color('verde')
                    ->label('Cobertura')
                    ->suffix('US$')
                    ->numeric()
                    ->searchable(),
                TextColumn::make('affiliate_full_name')
                    ->sortable()
                    ->label('Afiliado')
                    ->searchable(),
                TextColumn::make('affiliate_ci_rif')
                    ->sortable()
                    ->label('CI/RIF')
                    ->searchable(),
                TextColumn::make('affiliate_phone')
                    ->sortable()
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('affiliate_email')
                    ->sortable()
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->sortable()
                    ->label('Aprobado por')
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->sortable()
                    ->label('Frecuencia')
                    ->badge()
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->sortable()
                    ->label('Forma de pago')
                    ->badge()
                    ->description(function (Sale $record) {
                        return $record->reference_payment != null ? 'REF#: '.$record->reference_payment : 'N/A';
                    })
                    ->searchable(),
                TextColumn::make('payment_method_usd')
                    ->sortable()
                    ->label('Pago multiple')
                    ->prefix('US$: ')
                    ->searchable()
                    ->description(function ($record) {
                        return $record->payment_method_ves != 'N/A' ? 'VES: '.$record->payment_method_ves : 'VES: N/A';
                    })
                    ->searchable(),

                // TextColumn::make('pay_amount_usd')
                //     ->label('Pago registrado')
                //     ->sortable()
                //     ->searchable()
                //     ->suffix(' US$')
                //     ->description(function ($record) {
                //         return $record->pay_amount_ves != 'N/A' ? number_format($record->pay_amount_ves, 2, ',', '.') . ' VES' : 'N/A';
                //     }),

                TextColumn::make('bank_usd')
                    ->sortable()
                    ->searchable()
                    ->label('Banco')
                    ->prefix('US$: ')
                    ->description(function ($record) {
                        return $record->bank_ves != 'N/A' ? 'VES: '.$record->bank_ves : 'VES: N/A';
                    }),
                TextColumn::make('invoice_generated')
                    ->label('Nro. de Factura')
                    ->prefix('#')
                    ->sortable()
                    ->badge()
                    ->alignCenter()
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->sortable()
                    ->label('Monto Total')
                    ->money('USD')
                    ->summarize(Sum::make()
                        ->label(('Total de Venta'))
                        ->money('USD'))
                    ->alignCenter()
                    ->searchable(),
                TextColumn::make('pay_amount_usd')
                    ->sortable()
                    ->label('Pagos(US$)')
                    ->money('USD')
                    ->summarize(Sum::make()
                        ->label(('Total Pagos (US$)'))
                        ->money('USD'))
                    ->alignCenter()
                    ->searchable(),
                TextColumn::make('pay_amount_ves')
                    ->sortable()
                    ->label('Pagos(VES)')
                    ->money('VES')
                    ->summarize(Sum::make()
                        ->label(('Total Pagos (VES)'))
                        ->money('VES'))
                    ->alignCenter()
                    ->searchable(),

            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                SelectFilter::make('payment_frequency')
                    ->native(false)
                    ->options([
                        'ANUAL' => 'ANUAL',
                        'SEMESTRAL' => 'SEMESTRAL',
                        'TRIMESTRAL' => 'TRIMESTRAL',
                        'MENSUAL' => 'MENSUAL',
                    ])
                    ->label('Frecuencia de Pago'),
                SelectFilter::make('plan_id')
                    ->native(false)
                    ->relationship('plan', 'description')
                    ->label('Planes'),
                SelectFilter::make('payment_method')
                    ->native(false)
                    ->options([
                        'EFECTIVO US$' => 'EFECTIVO US$',
                        'ZELLE' => 'ZELLE',
                        'PAGO MOVIL VES' => 'PAGO MOVIL VES',
                        'TRANSFERENCIA VES' => 'TRANSFERENCIA VES',
                    ])
                    ->label('Metodo de Pago'),
                SelectFilter::make('bank')
                    ->native(false)
                    ->options([
                        'CHASE BANK' => 'CHASE BANK',
                        'BANK OF AMERICA' => 'BANK OF AMERICA',
                        'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
                        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                        'BANCAMIGA - VES' => 'BANCAMIGA - VES',
                        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
                        'BANCO DE VENEZUELA - VES' => 'BANCO DE VENEZUELA - VES',
                    ])
                    ->label('Banco'),

            ])
            ->recordActions([
                ActionGroup::make([
                    self::downloadPdfAction(),
                    self::regeneratePdfAction(),
                    self::printInvoiceAction(),
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::deleteBulkSalesAction(),
                    self::exportBulkSalesAction(),
                ]),
            ]);
    }

    public static function reciboPagoPdfPath(Sale $record): string
    {
        return public_path('storage/reciboDePago/RDP-'.$record->invoice_number.'.pdf');
    }

    public static function reciboPagoPreviewUrl(Sale $record): ?string
    {
        $path = self::reciboPagoPdfPath($record);

        if (! is_file($path)) {
            return null;
        }

        return asset('storage/reciboDePago/RDP-'.$record->invoice_number.'.pdf').'?t='.filemtime($path);
    }

    public static function deleteReciboPagoPdfIfExists(Sale $record): bool
    {
        $path = self::reciboPagoPdfPath($record);

        if (! is_file($path)) {
            return false;
        }

        return unlink($path);
    }

    public static function downloadPdfAction(): Action
    {
        return Action::make('download_pdf')
            ->label('Descargar PDF')
            ->icon('heroicon-s-arrow-down-on-square-stack')
            ->color('verde')
            ->modalHeading('Recibo de pago')
            ->modalDescription('Vista previa del recibo guardado. Use el botón «Descargar PDF» para guardar el archivo.')
            ->modalIcon('heroicon-o-document-arrow-down')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (Sale $record): ViewContract => View::make('filament.administration.sales.recibo-pago-view-modal', [
                'sale' => $record,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (Sale $record): array => [
                Action::make('download_recibo_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn (): mixed => self::downloadReciboPagoPdfResponse($record)),
            ])
            ->action(function (Sale $record): void {
                self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_PREVIEW_OPENED', 'administration.sales.preview-pdf', $record, [
                    'file_exists' => is_file(self::reciboPagoPdfPath($record)),
                ]);
            });
    }

    private static function downloadReciboPagoPdfResponse(Sale $record): mixed
    {
        self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_DOWNLOAD_ATTEMPTED', 'administration.sales.download-pdf', $record);

        try {
            $path = self::reciboPagoPdfPath($record);

            if (! is_file($path)) {
                self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_DOWNLOAD_FAILED', 'administration.sales.download-pdf', $record, [
                    'reason' => 'file_not_found',
                ]);

                Notification::make()
                    ->title('¡ERROR!')
                    ->body('No existe el PDF del recibo. Regenérelo primero.')
                    ->danger()
                    ->send();

                return null;
            }

            self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_DOWNLOADED', 'administration.sales.download-pdf', $record, [
                'file_path' => $path,
            ]);

            LogController::log(Auth::user()->id, 'Descarga de documento', 'Modulo Ventas', 'DESCARGAR');

            return response()->download($path);
        } catch (Throwable $th) {
            self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_DOWNLOAD_FAILED', 'administration.sales.download-pdf', $record, [
                'error_message' => $th->getMessage(),
                'error_class' => $th::class,
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
            ]);

            LogController::log(Auth::user()->id, 'EXCEPTION', 'administration.sales.download-pdf', $th->getMessage());

            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-s-x-circle')
                ->iconColor('danger')
                ->danger()
                ->send();

            return null;
        }
    }

    public static function regeneratePdfAction(): Action
    {
        return Action::make('regenate_pdf')
            ->label('Regenerar PDF')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('warning')
            ->modalHeading('Recibo de pago')
            ->modalDescription('Indique el periodo de vigencia, genere la vista previa del PDF y confirme antes de descargar.')
            ->modalIcon('heroicon-o-document-arrow-down')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (Sale $record): ViewContract => View::make('filament.administration.sales.recibo-pago-preview-modal', [
                'sale' => $record,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->action(fn () => null);
    }

    /**
     * @param  array{desde?: string|null, hasta?: string|null}  $vigencia
     */
    public static function runRegenerateReciboPago(Sale $record, array $vigencia): bool
    {
        self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_REGENERATE_ATTEMPTED', 'administration.sales.regenerate-pdf', $record, [
            'desde' => $vigencia['desde'] ?? null,
            'hasta' => $vigencia['hasta'] ?? null,
        ]);

        try {
            $payload = self::buildReciboPagoRegenerationPayload($record, $vigencia);

            if ($payload === null) {
                self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_REGENERATE_FAILED', 'administration.sales.regenerate-pdf', $record, [
                    'reason' => 'unsupported_sale_type',
                ]);

                return false;
            }

            $hadExistingPdf = self::deleteReciboPagoPdfIfExists($record);

            if ($hadExistingPdf) {
                self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_EXISTING_REMOVED', 'administration.sales.regenerate-pdf', $record, [
                    'file_path' => self::reciboPagoPdfPath($record),
                ]);
            }

            $regenerar = $record->type === 'AFILIACION CORPORATIVA'
                ? SaleController::regenerateAvisoDePagoCorporate($payload)
                : SaleController::regenerateAvisoDePago($payload);

            if ($regenerar) {
                self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_REGENERATED', 'administration.sales.regenerate-pdf', $record);

                return true;
            }

            self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_REGENERATE_FAILED', 'administration.sales.regenerate-pdf', $record, [
                'reason' => 'controller_returned_false',
            ]);

            return false;
        } catch (Throwable $th) {
            self::auditSaleAction('AUDIT_ADMIN_SALES_PDF_REGENERATE_FAILED', 'administration.sales.regenerate-pdf', $record, [
                'error_message' => $th->getMessage(),
                'error_class' => $th::class,
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
            ]);

            LogController::log(Auth::user()->id, 'EXCEPTION', 'administration.sales.regenerate-pdf', $th->getMessage());

            return false;
        }
    }

    /**
     * @param  array{desde?: string|null, hasta?: string|null}  $vigencia
     * @return array<string, mixed>|null
     */
    public static function buildReciboPagoRegenerationPayload(Sale $record, array $vigencia): ?array
    {
        /** @var Sale|null $sale */
        $sale = Sale::query()->with(['plan', 'coverage'])->find($record->id);

        if (! $sale) {
            return null;
        }

        $desde = self::formatVigenciaDateForPdf($vigencia['desde'] ?? null);
        $hasta = self::formatVigenciaDateForPdf($vigencia['hasta'] ?? null);

        if ($record->type === 'AFILIACION INDIVIDUAL') {
            $afiliacion = Affiliation::query()
                ->where('code', $sale->affiliation_code)
                ->withCount('affiliates')
                ->with('paid_memberships')
                ->first();

            return [
                'invoice_number' => $sale->invoice_number,
                'emission_date' => $sale->created_at->format('d/m/Y'),
                'payment_method' => $sale->payment_method,
                'reference' => $record->reference_payment,
                'full_name_ti' => $sale->affiliate_full_name,
                'ci_rif_ti' => $sale->affiliate_ci_rif,
                'address_ti' => $afiliacion?->adress_ti,
                'phone_ti' => $afiliacion?->phone_ti,
                'email_ti' => $afiliacion?->email_ti,
                'total_amount' => $sale->total_amount,
                'plan' => $sale->plan?->description,
                'coverage' => $sale->coverage?->price,
                'frequency' => $sale->payment_frequency,
                'affiliates_count' => AffiliationDocumentAffiliatesCount::forIndividual($afiliacion),
                'desde' => $desde,
                'hasta' => $hasta,
            ];
        }

        if ($record->type === 'AFILIACION CORPORATIVA') {
            $afiliacion = AffiliationCorporate::query()
                ->where('code', $sale->affiliation_code)
                ->withCount('corporateAffiliates')
                ->with(['paid_membership_corporates', 'affiliationCorporatePlans'])
                ->first();

            return [
                'invoice_number' => $sale->invoice_number,
                'emission_date' => $sale->created_at->format('d/m/Y'),
                'payment_method' => $sale->payment_method,
                'reference' => $record->reference_payment,
                'full_name_ti' => $sale->affiliate_full_name,
                'ci_rif_ti' => $afiliacion?->rif,
                'address_ti' => $afiliacion?->address,
                'phone_ti' => $afiliacion?->phone,
                'email_ti' => $afiliacion?->email,
                'total_amount' => $sale->total_amount,
                'plan' => $afiliacion?->affiliationCorporatePlans?->toArray() ?? [],
                'coverage' => $sale->coverage?->price,
                'frequency' => $sale->payment_frequency,
                'affiliates_count' => AffiliationDocumentAffiliatesCount::forCorporate($afiliacion),
                'desde' => $desde,
                'hasta' => $hasta,
            ];
        }

        return null;
    }

    private static function formatVigenciaDateForPdf(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (str_contains((string) $value, '/')) {
                return (string) $value;
            }

            return Carbon::parse($value)->format('d/m/Y');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    public static function sendReciboPagoDeliveryAction(): Action
    {
        return Action::make('send_recibo_pago')
            ->label('Enviar recibo')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->modalHeading('Enviar recibo de pago')
            ->modalDescription('Elija el canal de envío, revise los destinatarios o active el modo prueba para validar el servicio.')
            ->modalIcon('heroicon-o-paper-airplane')
            ->modalWidth(Width::FiveExtraLarge)
            ->form(fn (Sale $record): array => SaleReciboPagoTestDeliveryForm::unifiedActionSchema($record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cancelar')
            ->disabled(fn (Sale $record): bool => ! self::hasReciboPagoPdf($record))
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('confirm_recibo_email', arguments: ['channel' => 'email'])
                    ->label('Confirmar envío por correo')
                    ->icon('heroicon-o-envelope')
                    ->color('primary'),
                $action->makeModalSubmitAction('confirm_recibo_whatsapp', arguments: ['channel' => 'whatsapp'])
                    ->label('Confirmar envío por WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success'),
            ])
            ->action(function (Sale $record, array $data, array $arguments): void {
                if (($arguments['channel'] ?? null) === 'email') {
                    self::sendReciboPagoEmail($record, SaleReciboPagoTestDeliveryForm::normalizeEmailFormData($data));

                    return;
                }

                if (($arguments['channel'] ?? null) === 'whatsapp') {
                    self::sendReciboPagoWhatsApp($record, SaleReciboPagoTestDeliveryForm::normalizeWhatsAppFormData($data));
                }
            });
    }

    public static function sendReciboPagoEmailAction(): Action
    {
        return Action::make('send_recibo_pago_email')
            ->label('Enviar recibo por correo')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->modalHeading('Enviar recibo de pago por correo')
            ->modalDescription('Revise los destinatarios o active el modo prueba para validar el servicio de correo.')
            ->modalIcon('heroicon-o-envelope')
            ->modalWidth(Width::TwoExtraLarge)
            ->form(fn (Sale $record): array => SaleReciboPagoTestDeliveryForm::emailActionSchema($record))
            ->modalSubmitActionLabel('Confirmar envío')
            ->modalCancelActionLabel('Cancelar')
            ->disabled(fn (Sale $record): bool => ! self::hasReciboPagoPdf($record))
            ->action(function (Sale $record, array $data): void {
                self::sendReciboPagoEmail($record, $data);
            });
    }

    public static function canSendReciboPagoEmail(Sale $record, bool $testMode = false): bool
    {
        if (! self::hasReciboPagoPdf($record)) {
            return false;
        }

        if ($testMode) {
            return true;
        }

        return SaleReciboPagoEmailRecipients::resolve($record)['has_recipients'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function sendReciboPagoEmail(Sale $record, array $data = []): void
    {
        $testMode = SaleReciboPagoTestDeliveryForm::isTestModeFromData($data);
        $recipients = SaleReciboPagoEmailRecipients::resolve($record);

        self::auditSaleAction('AUDIT_ADMIN_SALES_RECEIPT_EMAIL_ATTEMPTED', 'administration.sales.send-receipt-email', $record, [
            'test_mode' => $testMode,
            'test_email' => $testMode ? ($data['test_email'] ?? null) : null,
            'recipient_emails' => $recipients['emails'],
            'cc_emails' => $testMode ? [] : $recipients['cc_emails'],
            'has_recipients' => $recipients['has_recipients'],
            'has_pdf' => $recipients['has_pdf'],
        ]);

        try {
            SaleReciboPagoEmailRecipients::send(
                $record,
                testMode: $testMode,
                testEmail: $testMode ? (string) ($data['test_email'] ?? '') : null,
            );

            self::auditSaleAction('AUDIT_ADMIN_SALES_RECEIPT_EMAIL_SENT', 'administration.sales.send-receipt-email', $record, [
                'test_mode' => $testMode,
                'recipient_emails' => $testMode
                    ? array_filter([(string) ($data['test_email'] ?? '')])
                    : $recipients['emails'],
                'cc_emails' => $testMode ? [] : $recipients['cc_emails'],
            ]);

            LogController::log(Auth::user()->id, 'Envío de recibo de pago por correo', 'Modulo Ventas', 'ENVIAR');

            Notification::make()
                ->title($testMode ? 'Correo de prueba enviado' : 'Correo enviado')
                ->body($testMode
                    ? 'El recibo de pago fue enviado al correo de prueba indicado.'
                    : 'El recibo de pago fue enviado al agente, a la agencia y en copia al equipo interno.')
                ->success()
                ->send();
        } catch (Throwable $th) {
            self::auditSaleAction('AUDIT_ADMIN_SALES_RECEIPT_EMAIL_FAILED', 'administration.sales.send-receipt-email', $record, [
                'error_message' => $th->getMessage(),
                'error_class' => $th::class,
            ]);

            LogController::log(Auth::user()->id, 'EXCEPTION', 'administration.sales.send-receipt-email', $th->getMessage());

            Notification::make()
                ->title('No se pudo enviar el correo')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function sendReciboPagoWhatsAppAction(): Action
    {
        return Action::make('send_recibo_pago_whatsapp')
            ->label('Enviar recibo por WhatsApp')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('success')
            ->modalHeading('Enviar recibo de pago por WhatsApp')
            ->modalDescription('Revise los destinatarios o active el modo prueba para validar el servicio de WhatsApp.')
            ->modalIcon('heroicon-o-chat-bubble-left-right')
            ->modalWidth(Width::TwoExtraLarge)
            ->form(fn (Sale $record): array => SaleReciboPagoTestDeliveryForm::whatsAppActionSchema($record))
            ->modalSubmitActionLabel('Confirmar envío')
            ->modalCancelActionLabel('Cancelar')
            ->disabled(fn (Sale $record): bool => ! self::hasReciboPagoPdf($record))
            ->action(function (Sale $record, array $data): void {
                self::sendReciboPagoWhatsApp($record, $data);
            });
    }

    public static function canSendReciboPagoWhatsApp(Sale $record, bool $testMode = false): bool
    {
        if (! self::hasReciboPagoPdf($record)) {
            return false;
        }

        if ($testMode) {
            return true;
        }

        return SaleReciboPagoWhatsAppRecipients::resolve($record)['has_recipients'];
    }

    public static function hasReciboPagoPdf(Sale $record): bool
    {
        return is_file(self::reciboPagoPdfPath($record));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function sendReciboPagoWhatsApp(Sale $record, array $data = []): void
    {
        $testMode = SaleReciboPagoTestDeliveryForm::isTestModeFromData($data);
        $recipients = SaleReciboPagoWhatsAppRecipients::resolve($record);

        self::auditSaleAction('AUDIT_ADMIN_SALES_RECEIPT_WHATSAPP_ATTEMPTED', 'administration.sales.send-receipt-whatsapp', $record, [
            'test_mode' => $testMode,
            'test_phone' => $testMode ? ($data['test_phone'] ?? null) : null,
            'targets' => collect($recipients['targets'])->map(fn (array $target): array => [
                'role' => $target['role'],
                'name' => $target['name'],
                'phone' => $target['phone'],
            ])->all(),
            'has_recipients' => $recipients['has_recipients'],
            'has_pdf' => $recipients['has_pdf'],
        ]);

        try {
            $report = SaleReciboPagoWhatsAppRecipients::send(
                $record,
                testMode: $testMode,
                testPhone: $testMode ? (string) ($data['test_phone'] ?? '') : null,
            );

            self::auditSaleAction('AUDIT_ADMIN_SALES_RECEIPT_WHATSAPP_SENT', 'administration.sales.send-receipt-whatsapp', $record, [
                'test_mode' => $testMode,
                'dispatched' => $report['dispatched'],
                'failed' => $report['failed'],
                'failures' => $report['failures'],
            ]);

            LogController::log(Auth::user()->id, 'Envío de recibo de pago por WhatsApp', 'Modulo Ventas', 'ENVIAR');

            if ($testMode) {
                Notification::make()
                    ->title('WhatsApp de prueba enviado')
                    ->body('El recibo de pago fue enviado al teléfono de prueba indicado.')
                    ->success()
                    ->send();

                return;
            }

            $body = 'El recibo fue enviado por WhatsApp a '.$report['dispatched'].' destinatario(s).';

            if ($report['failed'] > 0) {
                $body .= ' '.$report['failed'].' envío(s) no se completaron.';
            }

            Notification::make()
                ->title($report['failed'] > 0 ? 'Envío parcial' : 'WhatsApp enviado')
                ->body($body)
                ->color($report['failed'] > 0 ? 'warning' : 'success')
                ->send();
        } catch (Throwable $th) {
            self::auditSaleAction('AUDIT_ADMIN_SALES_RECEIPT_WHATSAPP_FAILED', 'administration.sales.send-receipt-whatsapp', $record, [
                'error_message' => $th->getMessage(),
                'error_class' => $th::class,
            ]);

            LogController::log(Auth::user()->id, 'EXCEPTION', 'administration.sales.send-receipt-whatsapp', $th->getMessage());

            Notification::make()
                ->title('No se pudo enviar por WhatsApp')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function printInvoiceAction(): Action
    {
        return Action::make('print_invoice')
            ->label('Generar Factura')
            ->icon('heroicon-s-printer')
            ->color('info')
            ->modalWidth(Width::TwoExtraLarge)
            ->form(fn (Sale $record): array => $record->invoice_generated != null ? [] : [
                Section::make('Informacion de la Factura')
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('Nro. de Factura')
                            ->required(),
                        DatePicker::make('date')
                            ->label('Fecha de Factura')
                            ->required()
                            ->format('d/m/Y'),
                        TextInput::make('tasa_bcv')
                            ->label('Tasa BCV')
                            ->numeric()
                            ->required()
                            ->hidden(fn (Sale $record) => $record->pay_amount_usd == 0.00),
                    ])->columns(function (Sale $record) {
                        if ($record->pay_amount_usd == 0.00) {
                            return 2;
                        }

                        return 3;
                    }),
            ])
            ->action(function (Sale $record, array $data) {
                self::auditSaleAction('AUDIT_ADMIN_SALES_INVOICE_GENERATION_ATTEMPTED', 'administration.sales.generate-invoice', $record, [
                    'invoice_number' => $data['invoice_number'] ?? null,
                    'date' => $data['date'] ?? null,
                ]);

                try {
                    if ($record->invoice_generated != null) {
                        self::auditSaleAction('AUDIT_ADMIN_SALES_INVOICE_DOWNLOADED', 'administration.sales.generate-invoice', $record, [
                            'generated_invoice_number' => $record->invoice_generated,
                            'source' => 'existing',
                        ]);

                        return response()->download(public_path('storage/facturas/FACT-'.$record->invoice_generated.'.pdf'));
                    }

                    $sale = Sale::query()->with(['plan', 'coverage'])->find($record->id);
                    $afiliacion = Affiliation::query()->where('code', $sale?->affiliation_code)->with('paid_memberships')->first();

                    if (isset($data['tasa_bcv'])) {
                        $calculo = $data['tasa_bcv'] * ($sale?->pay_amount_usd ?? 0);
                    } else {
                        $calculo = $sale?->pay_amount_ves ?? 0;
                    }

                    if ($record->type === 'AFILIACION INDIVIDUAL') {
                        $data_factura = [
                            'invoice_number' => $data['invoice_number'],
                            'emission_date' => $data['date'],
                            'payment_method' => $sale?->payment_method,
                            'reference' => $record->reference_payment,
                            'full_name_ti' => $sale?->affiliate_full_name,
                            'ci_rif_ti' => $sale?->affiliate_ci_rif,
                            'address_ti' => $afiliacion?->adress_ti,
                            'phone_ti' => $afiliacion?->phone_ti,
                            'email_ti' => $afiliacion?->email_ti,
                            'total_amount' => $calculo,
                            'plan' => $sale?->plan?->description,
                            'coverage' => $sale?->coverage->price ?? null,
                            'frequency' => $sale?->payment_frequency,
                        ];
                    }

                    if ($record->type === 'AFILIACION CORPORATIVA') {
                        $afiliacion = AffiliationCorporate::query()
                            ->where('code', $sale->affiliation_code)
                            ->with(['paid_membership_corporates', 'affiliationCorporatePlans'])
                            ->first();
                        // dd($afiliacion);
                        $data_factura = [
                            'invoice_number' => $data['invoice_number'],
                            'emission_date' => $data['date'],
                            'payment_method' => $sale?->payment_method,
                            'reference' => $record->reference_payment,
                            'full_name_ti' => $afiliacion?->name_corporate,
                            'ci_rif_ti' => $afiliacion?->rif,
                            'address_ti' => $afiliacion?->adress,
                            'phone_ti' => $afiliacion?->phone,
                            'email_ti' => $afiliacion?->email,
                            'total_amount' => $calculo,
                            'plan' => $afiliacion?->affiliationCorporatePlans?->toArray() ?? [],
                            'coverage' => $sale?->coverage->price ?? null,
                            'frequency' => $sale?->payment_frequency,
                        ];
                        // dd($data_factura);
                    }

                    ini_set('memory_limit', '2048M');

                    $name_pdf = 'FACT-'.$data['invoice_number'].'.pdf';

                    if ($record->type === 'AFILIACION CORPORATIVA') {
                        $pdf = Pdf::loadView('documents.factura-corporativa', compact('data_factura'));
                    } else {
                        $pdf = Pdf::loadView('documents.factura', compact('data_factura'));
                    }

                    $pdf->save(public_path('storage/facturas/'.$name_pdf));

                    $record->invoice_generated = $data['invoice_number'];
                    $record->save();

                    self::auditSaleAction('AUDIT_ADMIN_SALES_INVOICE_GENERATED', 'administration.sales.generate-invoice', $record, [
                        'generated_invoice_number' => $data['invoice_number'],
                        'file_name' => $name_pdf,
                    ]);

                    return response()->download(public_path('storage/facturas/'.$name_pdf));
                } catch (Throwable $th) {
                    self::auditSaleAction('AUDIT_ADMIN_SALES_INVOICE_GENERATION_FAILED', 'administration.sales.generate-invoice', $record, [
                        'error_message' => $th->getMessage(),
                        'error_class' => $th::class,
                        'error_file' => $th->getFile(),
                        'error_line' => $th->getLine(),
                    ]);

                    Log::info($th->getMessage());
                    Notification::make()
                        ->title('ERROR')
                        ->body($th->getMessage())
                        ->icon('heroicon-s-x-circle')
                        ->iconColor('danger')
                        ->danger()
                        ->send();

                    return null;
                }
            });
    }

    private static function deleteBulkSalesAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->deselectRecordsAfterCompletion()
            ->requiresConfirmation()
            ->color('danger')
            ->icon('heroicon-m-trash')
            ->label('Eliminar Registro(s)')
            ->modalHeading('ELIMINAR REGISTRO DE VENTA(S)')
            ->modalDescription('Esta accion eliminara los registros de venta seleccionados, asi como sus respectivas facturas y comisiones.')
            ->action(function (Collection $records) {
                $recordIds = $records->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

                SecurityAudit::log('AUDIT_ADMIN_SALES_BULK_DELETE_ATTEMPTED', 'administration.sales.bulk-delete', [
                    'panel' => 'administration',
                    'records_count' => count($recordIds),
                    'record_ids' => $recordIds,
                ], Auth::user());

                try {
                    foreach ($records as $record) {
                        $record->paidMembershipIndividual()->delete();
                        $record->paidMembershipCorporate()->delete();
                        $record->commission()->delete();
                        $record->collections()->delete();
                        $record->delete();
                    }

                    SecurityAudit::log('AUDIT_ADMIN_SALES_BULK_DELETED', 'administration.sales.bulk-delete', [
                        'panel' => 'administration',
                        'records_count' => count($recordIds),
                        'record_ids' => $recordIds,
                    ], Auth::user());

                    Notification::make()
                        ->title('¡ELIMINADO CON EXITO!')
                        ->body('Los registros de venta se han eliminado exitosamente.')
                        ->icon('heroicon-s-check-circle')
                        ->iconColor('success')
                        ->success()
                        ->send();
                } catch (Throwable $th) {
                    SecurityAudit::log('AUDIT_ADMIN_SALES_BULK_DELETE_FAILED', 'administration.sales.bulk-delete', [
                        'panel' => 'administration',
                        'records_count' => count($recordIds),
                        'record_ids' => $recordIds,
                        'error_message' => $th->getMessage(),
                        'error_class' => $th::class,
                        'error_file' => $th->getFile(),
                        'error_line' => $th->getLine(),
                    ], Auth::user());

                    Notification::make()
                        ->title('ERROR')
                        ->body($th->getMessage().' Linea: '.$th->getLine().' Archivo: '.$th->getFile())
                        ->icon('heroicon-s-x-circle')
                        ->iconColor('danger')
                        ->danger()
                        ->send();
                }
            });
    }

    private static function exportBulkSalesAction(): ExportBulkAction
    {
        return ExportBulkAction::make()
            ->exporter(SaleExporter::class)
            ->label('Exportar XLS')
            ->color('warning')
            ->deselectRecordsAfterCompletion()
            ->before(function (): void {
                SecurityAudit::log('AUDIT_ADMIN_SALES_BULK_EXPORT_ATTEMPTED', 'administration.sales.bulk-export', [
                    'panel' => 'administration',
                ], Auth::user());
            })
            ->after(function (): void {
                SecurityAudit::log('AUDIT_ADMIN_SALES_BULK_EXPORTED', 'administration.sales.bulk-export', [
                    'panel' => 'administration',
                ], Auth::user());
            });
    }

    private static function auditSaleAction(string $event, string $route, Sale $record, array $context = []): void
    {
        SecurityAudit::log($event, $route, array_merge([
            'panel' => 'administration',
            'sale_id' => $record->id,
            'invoice_number' => $record->invoice_number,
            'affiliation_code' => $record->affiliation_code,
            'sale_type' => $record->type,
            'payment_method' => $record->payment_method,
        ], $context), Auth::user());
    }
}
