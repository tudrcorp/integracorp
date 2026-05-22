<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\RelationManagers;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Http\Controllers\PaidMembershipController;
use App\Models\Affiliation;
use App\Models\Collection;
use App\Models\PaidMembership;
use App\Models\User;
use App\Support\FilamentDateDisplay;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaidMembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'paid_memberships';

    protected static ?string $title = 'Pagos registrados';

    protected static string|BackedEnum|null $icon = 'heroicon-m-document-currency-dollar';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_payment_usd')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['plan', 'coverage'])
                ->orderByDesc('created_at'))
            ->heading('Pagos registrados')
            ->description('Comprobantes y montos asociados a esta afiliación. Apruebe los pendientes desde el menú de acciones.')
            ->emptyStateHeading('Sin pagos registrados')
            ->emptyStateDescription('Aún no hay comprobantes de pago vinculados a esta afiliación.')
            ->emptyStateIcon(Heroicon::Banknotes)
            ->striped()
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('status')
                    ->label('Estatus')
                    ->icon(Heroicon::Signal)
                    ->badge()
                    ->color(fn (string $state): string => $state === 'APROBADO' ? 'success' : 'warning')
                    ->sortable(),
                TextColumn::make('pay_amount_usd')
                    ->label('Monto USD')
                    ->icon(Heroicon::CurrencyDollar)
                    ->weight(FontWeight::SemiBold)
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->description(fn (PaidMembership $record): ?string => $record->pay_amount_ves !== 'N/A'
                        ? $record->pay_amount_ves.' VES'
                        : null)
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Método')
                    ->badge()
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                TextColumn::make('total_amount')
                    ->label('Total contrato')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bank_usd')
                    ->label('Banco USD')
                    ->icon(Heroicon::BuildingLibrary)
                    ->description(fn (PaidMembership $record): string => $record->bank_ves !== 'N/A'
                        ? 'VES: '.$record->bank_ves
                        : 'VES: N/A')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_payment_usd')
                    ->label('Referencia')
                    ->icon(Heroicon::Hashtag)
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->prefix('USD: ')
                    ->description(fn (PaidMembership $record): string => $record->reference_payment_ves !== 'N/A'
                        ? 'VES: '.$record->reference_payment_ves
                        : 'VES: N/A')
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                TextColumn::make('payment_date')
                    ->label('Desde')
                    ->icon(Heroicon::CalendarDays)
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state)),
                TextColumn::make('prox_payment_date')
                    ->label('Hasta')
                    ->icon(Heroicon::CalendarDays)
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state)),
                TextColumn::make('renewal_date')
                    ->label('Renovación')
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                    ->badge()
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tasa_bcv')
                    ->label('Tasa BCV')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' VES')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('document_usd')
                    ->alignment(Alignment::Center)
                    ->label('USD')
                    ->tooltip('Comprobante USD')
                    ->icon(fn (PaidMembership $record): string => $record->document_usd !== 'N/A'
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-x-circle')
                    ->color(fn (PaidMembership $record): string => $record->document_usd !== 'N/A'
                        ? 'success'
                        : 'gray')
                    ->url(fn (PaidMembership $record): ?string => $record->document_usd !== 'N/A'
                        ? asset('storage/'.$record->document_usd)
                        : null)
                    ->openUrlInNewTab(),
                IconColumn::make('document_ves')
                    ->alignment(Alignment::Center)
                    ->label('VES')
                    ->tooltip('Comprobante VES')
                    ->icon(fn (PaidMembership $record): string => $record->document_ves !== 'N/A'
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-x-circle')
                    ->color(fn (PaidMembership $record): string => $record->document_ves !== 'N/A'
                        ? 'success'
                        : 'gray')
                    ->url(fn (PaidMembership $record): ?string => $record->document_ves !== 'N/A'
                        ? asset('storage/'.$record->document_ves)
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('gray')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'APROBADO' => 'Aprobado',
                        'POR APROBAR' => 'Por aprobar',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('approve')
                        ->label('Aprobar pago')
                        ->color('success')
                        ->icon(Heroicon::CheckCircle)
                        ->modalWidth(Width::TwoExtraLarge)
                        ->modalHeading('Aprobar comprobante de pago')
                        ->modalDescription('Seleccione los avisos de cobro que cubre este pago. Solo se listan avisos en estatus POR PAGAR.')
                        ->hidden(fn (PaidMembership $record): bool => $record->status === 'APROBADO')
                        ->form([
                            Section::make('Validación del pago')
                                ->icon(Heroicon::ClipboardDocumentCheck)
                                ->schema([
                                    TextInput::make('affiliation_code')
                                        ->label('Código de afiliación')
                                        ->default(fn (PaidMembership $record): ?string => Affiliation::query()
                                            ->whereKey($record->affiliation_id)
                                            ->value('code'))
                                        ->disabled()
                                        ->dehydrated()
                                        ->live(),
                                    Select::make('collections')
                                        ->label('Avisos de cobro')
                                        ->searchable()
                                        ->preload()
                                        ->multiple()
                                        ->options(fn (Get $get): array => Collection::query()
                                            ->where('affiliation_code', $get('affiliation_code'))
                                            ->where('status', 'POR PAGAR')
                                            ->get()
                                            ->mapWithKeys(fn (Collection $collection): array => [
                                                $collection->id => $collection->next_payment_date.' — #'.$collection->collection_invoice_number,
                                            ])
                                            ->all())
                                        ->required()
                                        ->hidden(fn (Get $get): bool => Collection::query()
                                            ->where('affiliation_code', $get('affiliation_code'))
                                            ->where('status', 'POR PAGAR')
                                            ->doesntExist()),
                                ]),
                        ])
                        ->action(function (PaidMembership $record, array $data): void {
                            $approvePayment = PaidMembershipController::approvePayment($record, $data);

                            if (isset($approvePayment['firstRegister']) && $approvePayment['firstRegister'] === true) {
                                Notification::make()
                                    ->title('Pago aprobado')
                                    ->body('El comprobante fue aprobado correctamente.')
                                    ->success()
                                    ->icon(Heroicon::CheckCircle)
                                    ->send();

                                $recipient = User::query()->where('is_admin', 1)->get();
                                foreach ($recipient as $user) {
                                    $recipientForUser = User::find($user->id);
                                    if ($recipientForUser !== null) {
                                        Notification::make()
                                            ->title('Comprobante aprobado')
                                            ->body('Pago aprobado. Afiliación: '.$record->affiliation->code)
                                            ->icon(Heroicon::UserPlus)
                                            ->iconColor('success')
                                            ->success()
                                            ->sendToDatabase($recipientForUser);
                                    }
                                }
                            }

                            if (isset($approvePayment['nextRegister']) && $approvePayment['nextRegister'] === true) {
                                Notification::make()
                                    ->title('Registro de pago exitoso')
                                    ->success()
                                    ->send();
                            }

                            $this->redirect(AffiliationResource::getUrl('view', [
                                'record' => $record->affiliation_id,
                            ], panel: 'business'));
                        }),
                ])
                    ->icon(Heroicon::EllipsisVertical),
            ]);
    }
}
