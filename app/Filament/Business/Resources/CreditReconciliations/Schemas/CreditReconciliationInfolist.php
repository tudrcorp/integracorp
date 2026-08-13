<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CreditReconciliations\Schemas;

use App\Models\CreditReconciliation;
use App\Support\PaidMembershipDocumentUrl;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreditReconciliationInfolist
{
    private const SECTION_CARD = 'rounded-[1.25rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_10px_36px_-12px_rgba(15,23,42,0.1)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_10px_36px_-12px_rgba(0,0,0,0.4)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movimiento de crédito')
                    ->description('Consumo generado al cancelar una cuota de la afiliación.')
                    ->icon('heroicon-o-banknotes')
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->schema([
                                TextEntry::make('whiteCompany.name')
                                    ->label('Empresa aliada')
                                    ->placeholder('—'),
                                TextEntry::make('total_to_pay')
                                    ->label('Monto descontado')
                                    ->money('USD'),
                                TextEntry::make('affiliation_code')
                                    ->label('Código de afiliación')
                                    ->placeholder('—'),
                                TextEntry::make('affiliation_kind')
                                    ->label('Tipo de afiliación')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'corporate' => 'Corporativa',
                                        'individual' => 'Individual',
                                        default => $state ?: '—',
                                    }),
                                TextEntry::make('affiliates_count')
                                    ->label('Cantidad de afiliados'),
                                TextEntry::make('annual_amount')
                                    ->label('Monto anual')
                                    ->money('USD'),
                                TextEntry::make('payment_frequency')
                                    ->label('Frecuencia de pago')
                                    ->placeholder('—'),
                                TextEntry::make('collection_invoice_number')
                                    ->label('Nro. de aviso de cobro')
                                    ->placeholder('—'),
                                TextEntry::make('plan_type')
                                    ->label('Tipo de plan')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Registrado')
                                    ->dateTime('d/m/Y H:i'),
                                TextEntry::make('comprobante')
                                    ->label('Comprobante')
                                    ->state(fn (CreditReconciliation $record): string => PaidMembershipDocumentUrl::fromReconciliation($record) !== null
                                        ? 'Ver comprobante'
                                        : '—')
                                    ->url(fn (CreditReconciliation $record): ?string => PaidMembershipDocumentUrl::fromReconciliation($record))
                                    ->openUrlInNewTab(),
                            ])
                            ->columnSpanFull(),
                        TextEntry::make('affiliation_information')
                            ->label('Información de la afiliación')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
