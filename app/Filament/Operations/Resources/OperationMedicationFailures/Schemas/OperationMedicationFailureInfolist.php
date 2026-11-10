<?php

namespace App\Filament\Operations\Resources\OperationMedicationFailures\Schemas;

use App\Models\OperationMedicationFailure;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class OperationMedicationFailureInfolist
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Medicamento')
                    ->icon(Heroicon::ExclamationTriangle)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        TextEntry::make('medicine_name')->label('Medicamento recetado')->weight(FontWeight::SemiBold)->columnSpan(2),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => OperationMedicationFailure::statusLabel($state))
                            ->color(fn (?string $state): string => OperationMedicationFailure::statusColor($state)),
                        TextEntry::make('reason')
                            ->label('Motivo')
                            ->formatStateUsing(fn (?string $state): string => OperationMedicationFailure::reasonLabel($state)),
                        TextEntry::make('indications')->label('Indicaciones')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('notes')->label('Nota para compras')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make('Origen')
                    ->icon(Heroicon::UserGroup)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        TextEntry::make('patient_name')->label('Paciente')->placeholder('—'),
                        TextEntry::make('case_code')->label('Caso')->badge()->color('info')->placeholder('—'),
                        TextEntry::make('operation_coordination_service_id')->label('Servicio médico')->prefix('#')->placeholder('—'),
                        TextEntry::make('reported_by_name')
                            ->label('Reportado por')
                            ->helperText(fn (OperationMedicationFailure $record): ?string => $record->created_at?->format('d/m/Y H:i'))
                            ->placeholder('—'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make('Cierre')
                    ->icon(Heroicon::CheckCircle)
                    ->visible(fn (OperationMedicationFailure $record): bool => ! $record->isPendingPurchase())
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        TextEntry::make('resolved_by_name')->label('Cerrada por')->helperText(fn (OperationMedicationFailure $record): ?string => $record->resolved_at?->format('d/m/Y H:i')),
                        TextEntry::make('product.name')->label('Producto del inventario')->placeholder('—'),
                        TextEntry::make('resolution_notes')->label('Nota')->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
