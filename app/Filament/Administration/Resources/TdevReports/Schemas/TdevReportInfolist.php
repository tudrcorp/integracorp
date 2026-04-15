<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\TdevReports\Schemas;

use App\Enums\FormaPago;
use App\Enums\StatusComision;
use App\Enums\StatusPago;
use App\Enums\StatusVaucher;
use App\Filament\Administration\Resources\TdevReports\Actions\TdevReportPaymentModalActions;
use App\Models\TdevReport;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class TdevReportInfolist
{
    /**
     * Tarjeta exterior estilo iOS (grupo con vidrio y sombra suave).
     */
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/80 bg-white/75 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.14)] backdrop-blur-xl dark:border-white/10 dark:bg-gray-950/60 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.5)]';

    /**
     * Panel interior tipo “inset” iOS (lista de ajustes / detalle).
     */
    private const IOS_INNER_CLASS = 'rounded-2xl border border-slate-200/60 bg-white/60 p-4 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.65)] backdrop-blur-md dark:border-white/10 dark:bg-white/[0.04] dark:shadow-none sm:p-5';

    /**
     * Resalta la tarjeta de pago del voucher (borde y fondo esmeralda suave).
     */
    private const HIGHLIGHT_PAYMENT_SECTION = 'rounded-[1.5rem] border-2 border-emerald-500/35 bg-gradient-to-br from-emerald-50/95 via-white/80 to-white/75 shadow-[0_14px_44px_-14px_rgba(16,185,129,0.28)] ring-1 ring-emerald-500/15 backdrop-blur-xl dark:border-emerald-400/35 dark:from-emerald-950/45 dark:via-gray-950/55 dark:to-gray-950/60 dark:shadow-[0_14px_44px_-14px_rgba(16,185,129,0.15)] dark:ring-emerald-400/10';

    private const HIGHLIGHT_PAYMENT_INNER = 'rounded-2xl border border-emerald-200/70 bg-emerald-50/35 p-4 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.6)] backdrop-blur-md dark:border-emerald-500/25 dark:bg-emerald-950/25 dark:shadow-none sm:p-5';

    /**
     * Resalta la tarjeta de comisiones (borde y fondo ámbar suave).
     */
    private const HIGHLIGHT_COMMISSION_SECTION = 'rounded-[1.5rem] border-2 border-amber-500/35 bg-gradient-to-br from-amber-50/95 via-white/80 to-white/75 shadow-[0_14px_44px_-14px_rgba(245,158,11,0.28)] ring-1 ring-amber-500/15 backdrop-blur-xl dark:border-amber-400/35 dark:from-amber-950/40 dark:via-gray-950/55 dark:to-gray-950/60 dark:shadow-[0_14px_44px_-14px_rgba(245,158,11,0.15)] dark:ring-amber-400/10';

    private const HIGHLIGHT_COMMISSION_INNER = 'rounded-2xl border border-amber-200/70 bg-amber-50/35 p-4 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.6)] backdrop-blur-md dark:border-amber-500/25 dark:bg-amber-950/25 dark:shadow-none sm:p-5';

    private static function commissionStatusColor(?string $state): string
    {
        return match (mb_strtoupper((string) $state)) {
            'PAGADA', 'LIQUIDADA' => 'success',
            'PENDIENTE' => 'warning',
            default => 'gray',
        };
    }

    private static function commissionStatusColorFromState(mixed $state): string
    {
        if ($state instanceof StatusComision) {
            return $state->filamentColor();
        }

        if (is_string($state) && $state !== '') {
            $resolved = StatusComision::tryFrom(mb_strtoupper(trim($state)));
            if ($resolved !== null) {
                return $resolved->filamentColor();
            }
        }

        return self::commissionStatusColor(is_string($state) ? $state : '');
    }

    private static function reportStatusColor(?string $state): string
    {
        return match (mb_strtoupper((string) $state)) {
            'OK', 'PROCESADO', 'VALIDO', 'VÁLIDO' => 'success',
            'ERROR', 'RECHAZADO' => 'danger',
            'PENDIENTE' => 'warning',
            default => 'gray',
        };
    }

    private static function formatPercentOrDash(mixed $state, int $decimals = 4): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        return number_format((float) $state, $decimals, ',', '.').' %';
    }

    private static function formatUsdOrDash(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        return Number::currency((float) $state, 'USD', locale: 'es');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Resumen del movimiento')
                    ->description(function (TdevReport $record): string {
                        $at = $record->created_at?->format('d/m/Y H:i') ?? '—';

                        return 'Registro #'.$record->getKey().' · Importado el '.$at;
                    })
                    ->icon(Heroicon::OutlinedTicket)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                    ->schema([
                                        TextEntry::make('fecha')
                                            ->label('Fecha')
                                            ->icon(Heroicon::OutlinedCalendarDays)
                                            ->weight('medium')
                                            ->placeholder('—'),
                                        TextEntry::make('vaucher')
                                            ->label('Voucher')
                                            ->icon(Heroicon::OutlinedQrCode)
                                            ->fontFamily(FontFamily::Mono)
                                            ->copyable()
                                            ->weight('semibold')
                                            ->color('primary')
                                            ->placeholder('—'),
                                        TextEntry::make('status_report')
                                            ->label('Estatus del reporte')
                                            ->icon(Heroicon::OutlinedSignal)
                                            ->badge()
                                            ->color(fn (?string $state): string => self::reportStatusColor($state))
                                            ->placeholder('—'),
                                        TextEntry::make('agencia')
                                            ->label('Agencia')
                                            ->icon(Heroicon::OutlinedBuildingOffice2)
                                            ->placeholder('—'),
                                        TextEntry::make('agente_emisor')
                                            ->label('Agente')
                                            ->icon(Heroicon::OutlinedUserCircle)
                                            ->placeholder('—'),
                                        TextEntry::make('nivel')
                                            ->label('Subagente')
                                            ->icon(Heroicon::OutlinedUsers)
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Viaje y pasajero')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('salida')
                                    ->label('Salida')
                                    ->icon(Heroicon::OutlinedArrowRightCircle)
                                    ->placeholder('—'),
                                TextEntry::make('regreso')
                                    ->label('Regreso')
                                    ->icon(Heroicon::OutlinedArrowLeftCircle)
                                    ->placeholder('—'),
                                TextEntry::make('pasajero')
                                    ->label('Pasajero')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('nro_documento')
                                    ->label('Nº documento')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Plan y voucher')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('categoria_del_plan')
                                    ->label('Categoría del plan')
                                    ->icon(Heroicon::OutlinedTag)
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('estatus_vaucher')
                                    ->label('Estatus del voucher')
                                    ->icon(Heroicon::OutlinedShieldCheck)
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => StatusVaucher::labelFromMixed($state))
                                    ->color(fn ($state): string => StatusVaucher::filamentColorFromMixed($state))
                                    ->placeholder('—'),
                                TextEntry::make('descripcion_del_plan')
                                    ->label('Descripción del plan')
                                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                                    ->columnSpanFull()
                                    ->placeholder('—')
                                    ->wrap(),
                                TextEntry::make('cupon_de_descuento')
                                    ->label('Cupón de descuento')
                                    ->icon(Heroicon::OutlinedReceiptPercent)
                                    ->placeholder('—'),
                                TextEntry::make('cupon_comision')
                                    ->label('Cupón comisión')
                                    ->icon(Heroicon::OutlinedPercentBadge)
                                    ->placeholder('—'),
                                TextEntry::make('cupon_promocion')
                                    ->label('Cupón promoción')
                                    ->icon(Heroicon::OutlinedSparkles)
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Montos e impuestos')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('monto_pvp_precio_de_venta')
                                    ->label('Monto PVP (precio de venta)')
                                    ->icon(Heroicon::OutlinedCurrencyDollar)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsdOrDash($state))

                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('precio_upgrade')
                                    ->label('Precio upgrade')
                                    ->icon(Heroicon::OutlinedArrowTrendingUp)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsdOrDash($state))

                                    ->placeholder('—'),
                                TextEntry::make('porcentaje_cupon')
                                    ->label('Porcentaje cupón')
                                    ->icon(Heroicon::OutlinedReceiptPercent)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatPercentOrDash($state))

                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Pago del voucher')
                    ->description('Abono del voucher: forma de pago, referencia bancaria, tasa BCV y monto abonado (según CSV TDEV).')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->iconColor('success')
                    ->extraAttributes([
                        'class' => self::HIGHLIGHT_PAYMENT_SECTION,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::HIGHLIGHT_PAYMENT_INNER,
                            ])
                            ->schema([
                                TextEntry::make('fecha_pago_vaucher_credito')
                                    ->label('Fecha pago voucher crédito')
                                    ->icon(Heroicon::OutlinedCalendar)
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('forma_pago')
                                    ->label('Forma de pago')
                                    ->icon(Heroicon::OutlinedWallet)
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => FormaPago::labelFromMixed($state))
                                    ->color(fn ($state): string => FormaPago::filamentColorFromMixed($state))
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('comprobante_pago_path')
                                    ->label('Comprobante de pago')
                                    ->icon(Heroicon::OutlinedPaperClip)
                                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Archivo cargado' : '—')
                                    ->placeholder('—')
                                    ->suffixAction(
                                        Action::make('previewComprobantePago')
                                            ->label('Vista previa')
                                            ->icon(Heroicon::OutlinedEye)
                                            ->color('success')
                                            ->tooltip('Vista previa del comprobante')
                                            ->slideOver()
                                            ->modalWidth(Width::ThreeExtraLarge)
                                            ->modalHeading('Comprobante de pago')
                                            ->modalDescription(fn (TdevReport $record): string => 'Voucher '.$record->vaucher.' · '.$record->pasajero)
                                            ->modalContent(fn (TdevReport $record) => view('filament.administration.tdev-reports.comprobante-pago-preview', [
                                                'url' => filled($record->comprobante_pago_path)
                                                    ? Storage::disk('public')->url($record->comprobante_pago_path)
                                                    : null,
                                                'path' => $record->comprobante_pago_path,
                                            ]))
                                            ->modalSubmitAction(false)
                                            ->modalCancelAction(
                                                fn (Action $action): Action => $action
                                                    ->label('Cerrar')
                                                    ->extraAttributes([
                                                        'class' => TdevReportPaymentModalActions::IOS_GRAY_BTN,
                                                    ])
                                            )
                                            ->visible(fn (TdevReport $record): bool => filled($record->comprobante_pago_path))
                                    ),
                                TextEntry::make('entidad_bancaria_receptora')
                                    ->label('Entidad bancaria receptora')
                                    ->icon(Heroicon::OutlinedBuildingLibrary)
                                    ->placeholder('—'),
                                TextEntry::make('referencia_bancaria_pago_vaucher_credito')
                                    ->label('Referencia bancaria (pago voucher crédito)')
                                    ->icon(Heroicon::OutlinedHashtag)
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->weight('semibold')
                                    ->color('success')
                                    ->placeholder('—'),
                                TextEntry::make('tasa_bcv')
                                    ->label('Tasa BCV')
                                    ->icon(Heroicon::OutlinedChartBar)
                                    ->numeric(4)

                                    ->placeholder('—'),
                                TextEntry::make('monto_abonado_en_cuenta_vaucher_credito')
                                    ->label('Monto abonado (cuenta voucher crédito)')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->money('VES')

                                    ->weight('semibold')
                                    ->color('success')
                                    ->placeholder('—'),
                                TextEntry::make('estatus_pago')
                                    ->label('Estatus de pago')
                                    ->icon(Heroicon::OutlinedCheckBadge)
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => StatusPago::labelFromMixed($state))
                                    ->color(fn ($state): string => StatusPago::filamentColorFromMixed($state))
                                    ->placeholder('—'),
                                TextEntry::make('dias_transcurridos')
                                    ->label('Días transcurridos')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->numeric(0)
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Comisiones')
                    ->description('Porcentajes, montos por rol, liquidación y referencias de comisión.')
                    ->icon(Heroicon::OutlinedScale)
                    ->iconColor('warning')
                    ->extraAttributes([
                        'class' => self::HIGHLIGHT_COMMISSION_SECTION,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::HIGHLIGHT_COMMISSION_INNER,
                            ])
                            ->schema([
                                TextEntry::make('porcentaje_comision')
                                    ->label('Porcentaje de comisión')
                                    ->icon(Heroicon::OutlinedPercentBadge)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatPercentOrDash($state))

                                    ->weight('semibold')
                                    ->color('warning')
                                    ->placeholder('—'),
                                TextEntry::make('monto_comision')
                                    ->label('Monto comisión')
                                    ->icon(Heroicon::OutlinedCurrencyDollar)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsdOrDash($state))

                                    ->weight('semibold')
                                    ->color('warning')
                                    ->placeholder('—'),
                                TextEntry::make('estatus_comision')
                                    ->label('Estatus comisión')
                                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => StatusComision::labelFromMixed($state))
                                    ->color(fn ($state): string => self::commissionStatusColorFromState($state))
                                    ->placeholder('—'),
                                TextEntry::make('comision_agencia')
                                    ->label('Comisión agencia')
                                    ->icon(Heroicon::OutlinedBuildingOffice)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsdOrDash($state))

                                    ->placeholder('—'),
                                TextEntry::make('comision_agente')
                                    ->label('Comisión agente')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsdOrDash($state))

                                    ->placeholder('—'),
                                TextEntry::make('comision_subagente')
                                    ->label('Comisión subagente')
                                    ->icon(Heroicon::OutlinedUsers)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsdOrDash($state))

                                    ->placeholder('—'),
                                TextEntry::make('fecha_pago_comision')
                                    ->label('Fecha pago comisión')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->placeholder('—'),
                                TextEntry::make('formas_pago_comision')
                                    ->label('Formas de pago comisión')
                                    ->icon(Heroicon::OutlinedCreditCard)
                                    ->placeholder('—'),
                                TextEntry::make('referencia_bancaria_comision')
                                    ->label('Referencia bancaria comisión')
                                    ->icon(Heroicon::OutlinedHashtag)
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('relacion_comision')
                                    ->label('Relación comisión')
                                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                                    ->columnSpanFull()
                                    ->placeholder('—')
                                    ->wrap(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Cierre y observaciones')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('neto_del_servicio')
                                    ->label('Neto del servicio')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsdOrDash($state))

                                    ->weight('semibold')
                                    ->placeholder('—'),
                                TextEntry::make('utilidad_tdev')
                                    ->label('Utilidad TDEV')
                                    ->icon(Heroicon::OutlinedArrowTrendingUp)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsdOrDash($state))

                                    ->weight('semibold')
                                    ->color('success')
                                    ->placeholder('—'),
                                TextEntry::make('observaciones')
                                    ->label('Observaciones')
                                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                                    ->columnSpanFull()
                                    ->placeholder('—')
                                    ->wrap()
                                    ->prose(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->icon(Heroicon::OutlinedClock)
                    ->collapsed()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Creado')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Actualizado')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
