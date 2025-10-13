<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\RelationManagers;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

use BackedEnum;
use App\Models\User;
use App\Models\Collection;
use Filament\Actions\Action;
use App\Models\AffiliationCorporate;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Alignment;
use App\Models\PaidMembershipCorporate;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use App\Http\Controllers\PaidMembershipCorporateController;

class PaidMembershipCorporatesRelationManager extends RelationManager
{
    protected static string $relationship = 'paid_membership_corporates';

    protected static ?string $title = 'Pagos asociados';

    protected static string|BackedEnum|null $icon = 'heroicon-o-credit-card';

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
                TextColumn::make('reference_payment_zelle')
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
                    ->hidden(function (PaidMembershipCorporate $record) {
                        return $record->status == 'APROBADO';
                    })
                    ->label('Aprobar')
                    ->color('success')
                    ->icon('heroicon-s-check-circle')
                    ->form([
                        TextInput::make('affiliation_code')
                            ->label('Codigo de afiliacion')
                            ->default(function (PaidMembershipCorporate $record) {
                                return AffiliationCorporate::find($record->affiliation_corporate_id)->code;
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
                    ->action(function (PaidMembershipCorporate $record, array $data) {

                        $approvePayment = PaidMembershipCorporateController::approvePayment($record, $data);

                        if (isset($approvePayment['firstRegister']) && $approvePayment['firstRegister'] == true) {
                            Notification::make()
                                ->title('Operacion exitosa')
                                ->success()
                                ->send();

                            //Notificacion para Admin
                            $recipient = User::where('is_admin', 1)->get();
                            foreach ($recipient as $user) {
                                $recipient_for_user = User::find($user->id);
                                Notification::make()
                                    ->title('COMPROBANTE APROBADO')
                                    ->body('El pago ha sido aprobado. Codigo de afiliacion: ' . $record->affiliation_corporate->code)
                                    ->icon('heroicon-m-user-plus')
                                    ->iconColor('success')
                                    ->success()
                                    ->sendToDatabase($recipient_for_user);
                            }
                        }

                        if (isset($approvePayment['nextRegister']) && $approvePayment['nextRegister'] == true) {
                            Notification::make()
                                ->title('Registro de pago exitoso')
                                ->success()
                                ->send();
                        }

                        redirect()->route('filament.admin.resources.affiliation-corporates.index');
                    }),
            ])
            ->headerActions([
                // CreateAction::make(),
            ]);
    }
}