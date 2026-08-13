<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CreditReconciliations\Tables;

use App\Models\CreditReconciliation;
use App\Models\WhiteCompany;
use App\Support\PaidMembershipDocumentUrl;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CreditReconciliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->forWhiteCompanies()->with([
                'whiteCompany',
                'paidMembership',
                'paidMembershipCorporate',
            ]))
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Conciliación de crédito')
            ->description('Movimientos que descuentan el crédito asignado a empresas aliadas al cancelar cuotas de afiliación.')
            ->emptyStateHeading('Sin conciliaciones de crédito')
            ->emptyStateDescription('Los movimientos aparecen al cancelar cuotas de afiliaciones de empresas aliadas.')
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->columns([
                TextColumn::make('whiteCompany.name')
                    ->label('Empresa aliada')
                    ->description(fn (CreditReconciliation $record): ?string => $record->whiteCompany?->rif ? 'RIF: '.$record->whiteCompany->rif : null)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->icon(Heroicon::OutlinedBuildingLibrary),
                TextColumn::make('affiliation_code')
                    ->label('Afiliación')
                    ->badge()
                    ->icon(Heroicon::OutlinedIdentification)
                    ->searchable()
                    ->sortable()
                    ->description(fn (CreditReconciliation $record): ?string => filled($record->affiliation_kind)
                        ? strtoupper((string) $record->affiliation_kind)
                        : null),
                TextColumn::make('affiliates_count')
                    ->label('Afiliados')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('annual_amount')
                    ->label('Monto anual')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('total_to_pay')
                    ->label('Total a pagar')
                    ->money('USD')
                    ->sortable()
                    ->weight('font-semibold'),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia')
                    ->badge()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('collection_invoice_number')
                    ->label('Aviso de cobro')
                    ->badge()
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('plan_type')
                    ->label('Tipo de plan')
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('comprobante')
                    ->label('Comprobante')
                    ->alignment(Alignment::Center)
                    ->icon(fn (CreditReconciliation $record): Heroicon => PaidMembershipDocumentUrl::fromReconciliation($record) !== null
                        ? Heroicon::OutlinedCheckCircle
                        : Heroicon::OutlinedXCircle)
                    ->color(fn (CreditReconciliation $record): string => PaidMembershipDocumentUrl::fromReconciliation($record) !== null
                        ? 'success'
                        : 'gray')
                    ->url(fn (CreditReconciliation $record): ?string => PaidMembershipDocumentUrl::fromReconciliation($record))
                    ->openUrlInNewTab(),
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('white_company_id')
                    ->label('Empresa aliada')
                    ->options(fn (): array => WhiteCompany::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_frequency')
                    ->label('Frecuencia de pago')
                    ->options([
                        'ANUAL' => 'ANUAL',
                        'SEMESTRAL' => 'SEMESTRAL',
                        'TRIMESTRAL' => 'TRIMESTRAL',
                        'MENSUAL' => 'MENSUAL',
                    ]),
                SelectFilter::make('affiliation_kind')
                    ->label('Tipo de afiliación')
                    ->options([
                        'individual' => 'Individual',
                        'corporate' => 'Corporativa',
                    ]),
            ])
            ->recordActions([
                Action::make('view_credit_note')
                    ->label('Ver y descargar')
                    ->tooltip('Nota de crédito')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('success')
                    ->url(fn (CreditReconciliation $record): ?string => PaidMembershipDocumentUrl::fromReconciliation($record))
                    ->openUrlInNewTab()
                    ->hidden(fn (CreditReconciliation $record): bool => PaidMembershipDocumentUrl::fromReconciliation($record) === null),
                ViewAction::make()
                    ->label('Ver'),
            ])
            ->toolbarActions([]);
    }
}
