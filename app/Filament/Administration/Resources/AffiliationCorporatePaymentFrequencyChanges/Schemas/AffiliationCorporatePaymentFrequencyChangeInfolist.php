<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Schemas;

use App\Models\AffiliationCorporatePaymentFrequencyChange as FrequencyChange;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AffiliationCorporatePaymentFrequencyChangeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cambio')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->columnSpanFull()
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('affiliation_code')->label('Afiliación')->weight('bold')->copyable(),
                        TextEntry::make('affiliation_name')->label('Empresa')->placeholder('—'),
                        TextEntry::make('frequency_change')
                            ->label('Frecuencia')
                            ->badge()
                            ->color('warning')
                            ->state(fn (FrequencyChange $record): string => CorporatePaymentFrequency::label($record->previous_frequency)
                                .' → '.CorporatePaymentFrequency::label($record->new_frequency)),
                        TextEntry::make('display_status')
                            ->label('Estado')
                            ->badge()
                            ->state(fn (FrequencyChange $record): string => $record->displayStatus())
                            ->color(fn (FrequencyChange $record): string => match ($record->displayStatus()) {
                                'Revertido' => 'danger',
                                'Validado' => 'success',
                                default => 'warning',
                            }),
                        TextEntry::make('fee_anual')->label('Tarifa anual (no cambia)')->money('USD', locale: 'es'),
                        TextEntry::make('previous_total_amount')->label('Monto por período antes')->money('USD', locale: 'es'),
                        TextEntry::make('new_total_amount')->label('Monto por período después')->money('USD', locale: 'es'),
                        TextEntry::make('pending_balance')->label('Saldo pendiente redistribuido')->money('USD', locale: 'es'),
                    ]),
                ]),
            Section::make('Trazabilidad')
                ->icon(Heroicon::OutlinedFingerPrint)
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('performed_by_name')->label('Hecho por')->icon(Heroicon::OutlinedUser),
                        TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('performed_from')->label('Panel')->placeholder('—'),
                        TextEntry::make('ip')->label('IP')->placeholder('—'),
                        TextEntry::make('batch_uuid')
                            ->label('Lote')
                            ->helperText('Cambios hechos juntos en una acción masiva comparten este lote.')
                            ->copyable()
                            ->columnSpan(2),
                        TextEntry::make('validated_by_name')
                            ->label('Validado por')
                            ->placeholder('Pendiente de validar')
                            ->helperText(fn (FrequencyChange $record): ?string => $record->validated_at?->format('d/m/Y H:i')),
                        TextEntry::make('notifications_summary')
                            ->label('Avisos enviados')
                            ->state(fn (FrequencyChange $record): string => self::notificationsSummary($record)),
                    ]),
                ]),
            Section::make('Reverso')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->columnSpanFull()
                ->visible(fn (FrequencyChange $record): bool => $record->isReversed())
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('reversed_by_name')->label('Revertido por'),
                        TextEntry::make('reversed_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('reversal_reason')->label('Motivo')->columnSpanFull(),
                    ]),
                ]),
            Section::make(fn (FrequencyChange $record): string => $record->isReversed()
                ? 'Avisos de cobro originales (restaurados a POR PAGAR)'
                : 'Avisos de cobro cancelados')
                ->icon(Heroicon::OutlinedDocumentMinus)
                ->columnSpanFull()
                ->schema([self::collectionsEntry('cancelled_collections', 'No tenía avisos de cobro pendientes.')]),
            Section::make(fn (FrequencyChange $record): string => $record->isReversed()
                ? 'Avisos de cobro creados por el cambio (anulados por el reverso)'
                : 'Avisos de cobro nuevos')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->columnSpanFull()
                ->schema([self::collectionsEntry('created_collections', 'No se generaron avisos nuevos.')]),
        ]);
    }

    private static function collectionsEntry(string $name, string $empty): RepeatableEntry
    {
        return RepeatableEntry::make($name)
            ->hiddenLabel()
            ->placeholder($empty)
            ->table([
                TableColumn::make('Aviso'),
                TableColumn::make('Fecha de cobro'),
                TableColumn::make('Período'),
                TableColumn::make('Monto'),
            ])
            ->schema([
                TextEntry::make('invoice')->weight('bold'),
                TextEntry::make('date'),
                TextEntry::make('months')->formatStateUsing(fn (mixed $state): string => (int) $state.' '.((int) $state === 1 ? 'mes' : 'meses')),
                TextEntry::make('amount')->money('USD', locale: 'es'),
            ]);
    }

    private static function notificationsSummary(FrequencyChange $record): string
    {
        $log = $record->notification_log ?? [];
        $parts = [];

        foreach (['applied' => 'Cambio', 'reversed' => 'Reverso'] as $event => $label) {
            $entry = $log[$event] ?? null;

            if (! is_array($entry)) {
                continue;
            }

            $parts[] = $label.': '.((int) ($entry['emails_sent'] ?? 0)).' correos, '
                .((int) ($entry['whatsapps_queued'] ?? 0)).' WhatsApp, '
                .((int) ($entry['database_notified'] ?? 0)).' en el panel'
                .(($entry['channels_active'] ?? true) ? '' : ' (correo y WhatsApp pausados)');
        }

        return $parts !== [] ? implode(' · ', $parts) : 'En cola';
    }
}
