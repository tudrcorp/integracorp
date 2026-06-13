<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Tables;

use App\Http\Controllers\IndividualQuoteExportCsvController;
use App\Http\Controllers\LogController;
use App\Jobs\ResendEmailPropuestaEconomica;
use App\Models\Agency;
use App\Models\Bitacora;
use App\Models\IndividualQuote;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class IndividualQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    return IndividualQuote::query()->where('ownerAccountManagers', Auth::user()->id);
                }

                return IndividualQuote::query();
            })
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'accountManager',
                'agent',
            ]))
            ->defaultSort('created_at', 'desc')
            ->deferFilters(false)
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Cotizaciones individuales')
            ->description('Use la búsqueda, ordene columnas y aplique filtros por fecha, estatus o tipo de plan. Puede copiar correo y teléfono; oculte columnas desde el selector si necesita más espacio.')
            ->emptyStateHeading('Sin cotizaciones individuales')
            ->emptyStateDescription('Las cotizaciones creadas por agencias o agentes aparecerán aquí. Cree una nueva desde el botón correspondiente del recurso.')
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->columns([
                TextColumn::make('code_agency')
                    ->label('Agencia')
                    ->prefix(function (IndividualQuote $record): string {
                        $agency = self::agenciesByCode()->get($record->code_agency);
                        $definition = $agency?->typeAgency?->definition;

                        return $definition ? $definition.' - ' : 'MASTER - ';
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-building-library')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('code')
                    ->label('Código de cotización')
                    ->badge()
                    ->alignCenter()
                    ->color('primary')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->weight('font-semibold'),
                TextColumn::make('accountManager.name')
                    ->label('Account Manager')
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->placeholder('—')
                    ->default(fn (IndividualQuote $record): string => $record->accountManager?->name ?? '—')
                    ->color(function (string $state): string {
                        return match ($state) {
                            '—' => 'gray',
                            default => 'success',
                        };
                    })
                    ->hidden(fn (): bool => ! Auth::user()->is_business_admin)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agent.name')
                    ->label('Agente')
                    ->badge()
                    ->placeholder('—')
                    ->default(fn (IndividualQuote $record): string => $record->agent?->name ?? '—')
                    ->color(function (string $state): string {
                        return match ($state) {
                            '—' => 'gray',
                            default => 'success',
                        };
                    })
                    ->icon('heroicon-m-user')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('full_name')
                    ->label('Solicitante')
                    ->icon(Heroicon::OutlinedUser)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->grow(),
                TextColumn::make('plan')
                    ->label('Tipo de plan')
                    ->formatStateUsing(fn (mixed $state): string => self::planLabel($state))
                    ->badge()
                    ->color(fn (mixed $state): string => self::planColor(self::planLabel($state)))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(28)
                    ->tooltip(fn (IndividualQuote $record): ?string => $record->email),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Generada el')
                    ->description(fn (IndividualQuote $record): string => Carbon::parse($record->created_at)->diffForHumans())
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'verdeOpaco',
                            'APROBADA' => 'success',
                            'ANULADA' => 'warning',
                            'DECLINADA' => 'danger',
                            'EJECUTADA' => 'azul',
                            default => 'azulOscuro',
                        };
                    })
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA' => 'heroicon-c-information-circle',
                            'APROBADA' => 'heroicon-s-check-circle',
                            'ANULADA' => 'heroicon-s-exclamation-circle',
                            'DECLINADA' => 'heroicon-c-x-circle',
                            'EJECUTADA' => 'heroicon-s-check-circle',
                            default => 'heroicon-c-information-circle',
                        };
                    })
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Fecha de cotización')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->native(false),
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
                            $indicators['desde'] = 'Cotización desde '.Carbon::parse($data['desde'])->translatedFormat('d M Y');
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Cotización hasta '.Carbon::parse($data['hasta'])->translatedFormat('d M Y');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'PRE-APROBADA' => 'PRE-APROBADA',
                        'APROBADA' => 'APROBADA',
                        'ANULADA' => 'ANULADA',
                        'DECLINADA' => 'DECLINADA',
                        'EJECUTADA' => 'EJECUTADA',
                    ])
                    ->searchable()
                    ->preload()
                    ->indicator('Estatus'),
                SelectFilter::make('plan')
                    ->label('Tipo de plan')
                    ->options([
                        1 => 'Plan Inicial',
                        2 => 'Plan Ideal',
                        3 => 'Plan Especial',
                        'CM' => 'MultiPlan',
                    ])
                    ->searchable()
                    ->preload()
                    ->indicator('Plan'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),
                EditAction::make()
                    ->label('Editar'),
                ActionGroup::make([

                    /**FORWARD */

                    /* DESCARGAR DOCUMENTO */
                    Action::make('download')
                        ->label('Descargar cotización')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->requiresConfirmation()
                        ->modalHeading('DESCARGAR COTIZACION')
                        ->modalWidth(Width::FiveExtraLarge)
                        ->action(function (IndividualQuote $record, array $data) {

                            try {

                                if (! file_exists(public_path('storage/quotes/'.$record->code.'.pdf'))) {
                                    SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_PDF_DOWNLOAD_FAILED', 'business.individual-quotes.download', [
                                        'panel' => 'business',
                                        'individual_quote_id' => $record->id,
                                        'code' => $record->code,
                                        'reason' => 'file_not_found',
                                    ]);

                                    Notification::make()
                                        ->title('NOTIFICACIÓN')
                                        ->body('El documento asociado a la cotización no se encuentra disponible. Por favor, intente nuevamente en unos segundos.')
                                        ->icon('heroicon-s-x-circle')
                                        ->iconColor('warning')
                                        ->warning()
                                        ->send();

                                    return;
                                }
                                /**
                                 * Descargar el documento asociado a la cotizacion
                                 * ruta: storage/
                                 */
                                $path = public_path('storage/quotes/'.$record->code.'.pdf');

                                SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_PDF_DOWNLOADED', 'business.individual-quotes.download', [
                                    'panel' => 'business',
                                    'individual_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'path' => $path,
                                ]);

                                return response()->download($path);

                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_PDF_DOWNLOAD_FAILED', 'business.individual-quotes.download', [
                                    'panel' => 'business',
                                    'individual_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'reason' => $th->getMessage(),
                                ]);

                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('forward')
                        ->label('Reenviar Cotizacion')
                        ->icon('heroicon-o-arrow-uturn-right')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Reenvío de Cotizacion')
                        ->modalWidth(Width::FiveExtraLarge)
                        ->form([
                            Section::make()
                                ->heading('Informacion')
                                ->description('El link puede sera enviado por email y/o telefono!')
                                ->schema([
                                    TextInput::make('email')
                                        ->label('Email')
                                        ->email(),
                                    Grid::make(2)->schema([
                                        Select::make('country_code')
                                            ->label('Código de país')
                                            ->options([
                                                '+1' => '🇺🇸 +1 (Estados Unidos)',
                                                '+44' => '🇬🇧 +44 (Reino Unido)',
                                                '+49' => '🇩🇪 +49 (Alemania)',
                                                '+33' => '🇫🇷 +33 (Francia)',
                                                '+34' => '🇪🇸 +34 (España)',
                                                '+39' => '🇮🇹 +39 (Italia)',
                                                '+7' => '🇷🇺 +7 (Rusia)',
                                                '+55' => '🇧🇷 +55 (Brasil)',
                                                '+91' => '🇮🇳 +91 (India)',
                                                '+86' => '🇨🇳 +86 (China)',
                                                '+81' => '🇯🇵 +81 (Japón)',
                                                '+82' => '🇰🇷 +82 (Corea del Sur)',
                                                '+52' => '🇲🇽 +52 (México)',
                                                '+58' => '🇻🇪 +58 (Venezuela)',
                                                '+57' => '🇨🇴 +57 (Colombia)',
                                                '+54' => '🇦🇷 +54 (Argentina)',
                                                '+56' => '🇨🇱 +56 (Chile)',
                                                '+51' => '🇵🇪 +51 (Perú)',
                                                '+502' => '🇬🇹 +502 (Guatemala)',
                                                '+503' => '🇸🇻 +503 (El Salvador)',
                                                '+504' => '🇭🇳 +504 (Honduras)',
                                                '+505' => '🇳🇮 +505 (Nicaragua)',
                                                '+506' => '🇨🇷 +506 (Costa Rica)',
                                                '+507' => '🇵🇦 +507 (Panamá)',
                                                '+593' => '🇪🇨 +593 (Ecuador)',
                                                '+592' => '🇬🇾 +592 (Guyana)',
                                                '+591' => '🇧🇴 +591 (Bolivia)',
                                                '+598' => '🇺🇾 +598 (Uruguay)',
                                                '+20' => '🇪🇬 +20 (Egipto)',
                                                '+27' => '🇿🇦 +27 (Sudáfrica)',
                                                '+234' => '🇳🇬 +234 (Nigeria)',
                                                '+212' => '🇲🇦 +212 (Marruecos)',
                                                '+971' => '🇦🇪 +971 (Emiratos Árabes)',
                                                '+92' => '🇵🇰 +92 (Pakistán)',
                                                '+880' => '🇧🇩 +880 (Bangladesh)',
                                                '+62' => '🇮🇩 +62 (Indonesia)',
                                                '+63' => '🇵🇭 +63 (Filipinas)',
                                                '+66' => '🇹🇭 +66 (Tailandia)',
                                                '+60' => '🇲🇾 +60 (Malasia)',
                                                '+65' => '🇸🇬 +65 (Singapur)',
                                                '+61' => '🇦🇺 +61 (Australia)',
                                                '+64' => '🇳🇿 +64 (Nueva Zelanda)',
                                                '+90' => '🇹🇷 +90 (Turquía)',
                                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                                '+372' => '🇪🇪 +372 (Estonia)',
                                                '+371' => '🇱🇻 +371 (Letonia)',
                                                '+370' => '🇱🇹 +370 (Lituania)',
                                                '+48' => '🇵🇱 +48 (Polonia)',
                                                '+40' => '🇷🇴 +40 (Rumania)',
                                                '+46' => '🇸🇪 +46 (Suecia)',
                                                '+47' => '🇳🇴 +47 (Noruega)',
                                                '+45' => '🇩🇰 +45 (Dinamarca)',
                                                '+41' => '🇨🇭 +41 (Suiza)',
                                                '+43' => '🇦🇹 +43 (Austria)',
                                                '+31' => '🇳🇱 +31 (Países Bajos)',
                                                '+32' => '🇧🇪 +32 (Bélgica)',
                                                '+353' => '🇮🇪 +353 (Irlanda)',
                                                '+380' => '🇺🇦 +380 (Ucrania)',
                                                '+994' => '🇦🇿 +994 (Azerbaiyán)',
                                                '+995' => '🇬🇪 +995 (Georgia)',
                                                '+976' => '🇲🇳 +976 (Mongolia)',
                                                '+998' => '🇺🇿 +998 (Uzbekistán)',
                                                '+84' => '🇻🇳 +84 (Vietnam)',
                                                '+856' => '🇱🇦 +856 (Laos)',
                                                '+374' => '🇦🇲 +374 (Armenia)',
                                                '+965' => '🇰🇼 +965 (Kuwait)',
                                                '+966' => '🇸🇦 +966 (Arabia Saudita)',
                                                '+972' => '🇮🇱 +972 (Israel)',
                                                '+963' => '🇸🇾 +963 (Siria)',
                                                '+961' => '🇱🇧 +961 (Líbano)',
                                                '+960' => '🇲🇻 +960 (Maldivas)',
                                                '+992' => '🇹🇯 +992 (Tayikistán)',
                                            ])
                                            ->searchable()
                                            ->default('+58')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ]),
                                        TextInput::make('phone')
                                            ->prefixIcon('heroicon-s-phone')
                                            ->tel()
                                            ->label('Número de teléfono')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $countryCode = $get('country_code');
                                                if ($countryCode) {
                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                    $set('phone', $countryCode.$cleanNumber);
                                                }
                                            }),
                                    ]),
                                ]),
                        ])
                        ->action(function (IndividualQuote $record, array $data) {

                            try {

                                $email = null;
                                $phone = null;

                                if (isset($data['email'])) {
                                    $email = $data['email'];
                                }

                                if (isset($data['phone'])) {
                                    $phone = $data['phone'];
                                }

                                /**
                                 * JOB
                                 */
                                $job = ResendEmailPropuestaEconomica::dispatch($record, $email, $phone);

                                if ($job) {
                                    SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_FORWARD_SENT', 'business.individual-quotes.forward', [
                                        'panel' => 'business',
                                        'individual_quote_id' => $record->id,
                                        'code' => $record->code,
                                        'email' => $email,
                                        'phone' => $phone,
                                    ]);

                                    Notification::make()
                                        ->title('RE-ENVIADO EXITOSO')
                                        ->body('La información fue reenviada correctamente.')
                                        ->icon('heroicon-s-check-circle')
                                        ->iconColor('verde')
                                        ->success()
                                        ->send();
                                }
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_FORWARD_FAILED', 'business.individual-quotes.forward', [
                                    'panel' => 'business',
                                    'individual_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'email' => $data['email'] ?? null,
                                    'phone' => $data['phone'] ?? null,
                                    'reason' => $th->getMessage(),
                                ]);

                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('change_status')
                        ->label('Actualizar estatus')
                        ->color('azulOscuro')
                        ->icon('heroicon-s-check-circle')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ExtraLarge)
                        ->modalHeading('ACCIONES')
                        ->form([
                            Section::make()
                                ->schema([
                                    Grid::make(1)->schema([
                                        Select::make('status')
                                            ->label('Estatus')
                                            ->options([
                                                'PRE-APROBADA' => 'PRE-APROBADA',
                                                'APROBADA' => 'APROBADA',
                                                'ANULADA' => 'ANULADA',
                                                'DECLINADA' => 'DECLINADA',
                                                'EJECUTADA' => 'EJECUTADA',
                                            ])
                                            ->required()
                                            ->searchable()
                                            ->preload(),
                                        Textarea::make('description')
                                            ->autosize()
                                            ->label('Observaciones')
                                            ->placeholder('Describa las razones de la acción')
                                            ->required()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('description', strtoupper($state));
                                            }),
                                    ]),

                                ]),
                        ])
                        ->action(function (IndividualQuote $record, array $data): void {

                            try {

                                $record->status = $data['status'];
                                $record->save();

                                $bitacora = new Bitacora;
                                $bitacora->individual_quote()->associate($record);
                                $bitacora->user()->associate(Auth::user());
                                $bitacora->details = 'Se ha actualizado el estatus de la cotizacion a: '.$data['status'].'. Razón del cambio: '.$data['description'].'.';
                                $bitacora->save();

                                /**
                                 * LOG
                                 */
                                LogController::log(Auth::user()->id, 'Actualizacion de estatus', 'Modulo Cotizacion Individual', 'ACTUALIZAR ESTATUS');
                                SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_STATUS_UPDATED', 'business.individual-quotes.change-status', [
                                    'panel' => 'business',
                                    'individual_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'status' => $record->status,
                                    'description' => $data['description'] ?? null,
                                ]);

                                Notification::make()
                                    ->title('ESTATUS ACTUALIZADO EXITOSAMENTE')
                                    ->body('El estatus de la cotizacion ha sido actualizado exitosamente.')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('verde')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_STATUS_UPDATE_FAILED', 'business.individual-quotes.change-status', [
                                    'panel' => 'business',
                                    'individual_quote_id' => $record->id,
                                    'code' => $record->code,
                                    'status' => $data['status'] ?? null,
                                    'description' => $data['description'] ?? null,
                                    'reason' => $th->getMessage(),
                                ]);

                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Más acciones')
                    ->tooltip('Reenviar, descargar PDF o actualizar estatus')
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->iconButton()
                    ->color('azulOscuro'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportCsvController')
                        ->label('Exportar CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos una cotización')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $ids = $records->pluck('id')->all();
                            $token = IndividualQuoteExportCsvController::storeIdsAndGetToken($ids);

                            return redirect()->route('business.individual-quotes.export-csv', ['token' => $token]);
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }

    private static ?Collection $agenciesByCodeCache = null;

    private static function agenciesByCode(): Collection
    {
        if (self::$agenciesByCodeCache === null) {
            self::$agenciesByCodeCache = Agency::query()->with('typeAgency')->get()->keyBy('code');
        }

        return self::$agenciesByCodeCache;
    }

    private static function planLabel(mixed $plan): string
    {
        return match (true) {
            $plan === '1' || $plan === 1 => 'Plan Inicial',
            $plan === '2' || $plan === 2 => 'Plan Ideal',
            $plan === '3' || $plan === 3 => 'Plan Especial',
            $plan === 'CM' => 'MultiPlan',
            $plan === null, $plan === '' => '—',
            default => (string) $plan,
        };
    }

    private static function planColor(string $label): string
    {
        return match ($label) {
            'Plan Inicial' => 'azulClaro',
            'Plan Ideal' => 'azulOscuro',
            'Plan Especial' => 'verde',
            'MultiPlan' => 'warning',
            '—' => 'gray',
            default => 'info',
        };
    }
}
