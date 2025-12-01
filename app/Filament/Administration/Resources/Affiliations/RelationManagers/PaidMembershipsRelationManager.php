<?php

namespace App\Filament\Administration\Resources\Affiliations\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

use BackedEnum;
use App\Models\User;
use App\Models\Collection;
use App\Models\Affiliation;
use Filament\Actions\Action;
use App\Models\PaidMembership;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use App\Http\Controllers\PaidMembershipController;

class PaidMembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'paid_memberships';

    protected static ?string $title = 'PAGOS REGISTRADOS';

    protected static string|BackedEnum|null $icon = 'heroicon-m-document-currency-dollar';

    public function table(Table $table): Table
    {
        return $table
            ->heading('PAGOS REGISTRADOS')
            ->description('Relacion de pago de la afiliacion')
            ->recordTitleAttribute('affiliation_id')
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('azulOscuro')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Covertura')
                    ->badge()
                    ->color('azulOscuro')
                    ->suffix(' US$')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de pago')
                    ->badge()
                    ->color('azulOscuro'),
                TextColumn::make('total_amount')
                    ->label('Pago total')
                    ->numeric()
                    ->suffix(' US$'),
                TextColumn::make('payment_method')
                    ->badge()
                    ->searchable()
                    ->label('Metodo de pago'),
                TextColumn::make('payment_method_usd')
                    ->label('Pago multiple')
                    ->prefix('US$: ')
                    ->description(function ($record) {
                        return $record->payment_method_ves != 'N/A' ? 'VES: ' . $record->payment_method_ves : 'VES: N/A';
                    })
                    ->searchable(),
                TextColumn::make('bank_usd')
                    ->searchable()
                    ->label('Banco')
                    ->description(function ($record) {
                        return $record->bank_ves != 'N/A' ? $record->bank_ves : 'N/A';
                    }),
                TextColumn::make('reference_payment_usd')
                    ->label('Referencia de pago')
                    ->prefix('Ref(Zelle): ')
                    ->description(function ($record) {
                        return $record->reference_payment_ves != 'N/A' ?  'Ref(VES): ' . $record->reference_payment_ves : 'Ref(VES): N/A';
                    })
                    ->searchable(),
                IconColumn::make('document_ves')
                    ->alignment(Alignment::Center)
                    ->label('Comprobante')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->document_ves != 'N/A'
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->document_ves != 'N/A'
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->document_ves);
                    })
                    ->openUrlInNewTab(),
                IconColumn::make('document_usd')
                    ->alignment(Alignment::Center)
                    ->label('Comprobante')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->document_usd != 'N/A'
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->document_usd != 'N/A'
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->document_usd);
                    })
                    ->openUrlInNewTab(),
                TextColumn::make('pay_amount_usd')
                    ->label('Pago registrado')
                    ->suffix(' US$')
                    ->description(function ($record) {
                        return $record->pay_amount_ves != 'N/A' ? $record->pay_amount_ves . ' VES' : 'N/A';
                    }),
                TextColumn::make('tasa_bcv')
                    ->label('Tasa BCV')
                    ->suffix(' VES')
                    ->numeric(),
                TextColumn::make('payment_date')
                    ->label('Pagado desde'),
                TextColumn::make('prox_payment_date')
                    ->label('Pagado hasta'),
                TextColumn::make('renewal_date')
                    ->label('Renovacion')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('created_at')
                    ->label('Cargado el:')
                    ->badge()
                    ->dateTime('d/m/Y'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function ($record) {
                        return $record->status == 'APROBADO' ? 'success' : 'warning';
                    }),
            ])
            ->recordActions([
                Action::make('approve')
                    ->hidden(function (PaidMembership $record) {
                        return $record->status == 'APROBADO';
                    })
                    ->label('Aprobar')
                    ->color('success')
                    ->icon('heroicon-s-check-circle')
                    ->form([
                        TextInput::make('affiliation_code')
                            ->label('Codigo de afiliacion')
                            ->default(function (PaidMembership $record) {
                                return Affiliation::find($record->affiliation_id)->code;
                            })
                            ->dehydrated()
                            ->disabled()
                            ->live(),
                        Select::make('collections')
                            ->label('Avisos de cobro')
                            ->searchable()
                            ->preload()
                            ->multiple()
                            ->options(function (Get $get) {
                                // Log::info($get('affiliation_code'));
                                return Collection::select('id', 'collection_invoice_number', 'status', 'next_payment_date')
                                    ->where('affiliation_code', $get('affiliation_code'))
                                    ->where('status', 'POR PAGAR')
                                    ->get()
                                    ->pluck('next_payment_date', 'id');
                            })
                            ->required()
                            ->live()
                            ->hidden(function (Get $get) {
                                $count = Collection::select('id', 'collection_invoice_number', 'status')
                                    ->where('affiliation_code', $get('affiliation_code'))
                                    ->where('status', 'POR PAGAR')
                                    ->get()
                                    ->count();
                                if ($count == 0) {
                                    return true;
                                }
                                return false;
                            })

                    ])
                    ->action(function (PaidMembership $record, array $data) {

                        $approvePayment = PaidMembershipController::approvePayment($record, $data);

                        if (isset($approvePayment['firstRegister']) && $approvePayment['firstRegister'] == true) {
                            Notification::make()
                                ->title('Operacion exitosa')
                                ->success()
                                ->send();
                        }

                        if (isset($approvePayment['nextRegister']) && $approvePayment['nextRegister'] == true) {
                            Notification::make()
                                ->title('Registro de pago exitoso')
                                ->success()
                                ->send();
                        }

                        redirect()->route('filament.administration.resources.affiliations.index');
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}