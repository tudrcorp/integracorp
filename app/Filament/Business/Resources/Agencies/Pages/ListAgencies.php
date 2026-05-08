<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use App\Filament\Business\Resources\Agencies\Widgets\AgencyGeoChart;
use App\Filament\Business\Resources\Agencies\Widgets\AgentActiveForEstructureChart;
use App\Filament\Business\Resources\Agencies\Widgets\ControlActividadInteraccion;
use App\Filament\Business\Resources\Agencies\Widgets\NewRegisterAgencyForMountChart;
use App\Filament\Business\Resources\Agencies\Widgets\StatsOverviewAgency;
use App\Filament\Business\Resources\Agencies\Widgets\TotalEstructureAgency;
use App\Filament\Business\Resources\Agencies\Widgets\TotalSaleForEstructureChart;
use App\Http\Controllers\NotificationController;
use App\Jobs\SendBusinessAgencyFichaPdfMailJob;
use App\Models\Agency;
use App\Models\AgencyNoteBlog;
use App\Support\BusinessAgencyFichaPdfAccess;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ListAgencies extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Gestión de Agencias';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear agencia')
                ->icon('heroicon-s-user-plus')
                ->color('success'),
            Action::make('send_link')
                ->label('Enviar enlace de registro')
                ->icon('heroicon-m-paper-airplane')
                ->color('primary')
                ->modalHeading('Enviar enlace de registro externo')
                ->modalDescription('El enlace incluye el código de la agencia cifrado en la URL (Integracorp → /agency/c/…). Indique al menos correo electrónico o WhatsApp para enviarlo.')
                ->modalIcon('heroicon-m-link')
                ->modalIconColor('primary')
                ->modalSubmitActionLabel('Enviar')
                ->modalCancelActionLabel('Cancelar')
                ->modalWidth(Width::ExtraLarge)
                ->form([
                    Section::make('Agencia en el enlace')
                        ->description('El destinatario completará el registro bajo la estructura comercial de la agencia que elija; el código viaja cifrado igual que en el panel General.')
                        ->schema([
                            Select::make('agency_code')
                                ->label('Agencia')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->options(function (): array {
                                    $query = Agency::query()
                                        ->where('status', 'ACTIVO')
                                        ->orderBy('name_corporative');

                                    if (Auth::user()->is_accountManagers) {
                                        $query->where('ownerAccountManagers', Auth::id());
                                    }

                                    return $query
                                        ->get()
                                        ->mapWithKeys(fn (Agency $agency): array => [
                                            $agency->code => $agency->name_corporative.' · '.$agency->code,
                                        ])
                                        ->all();
                                })
                                ->default(fn () => Auth::user()?->code_agency)
                                ->placeholder('Seleccione una agencia')
                                ->helperText('Nombre corporativo y código que se asociarán al formulario de registro.'),
                        ]),
                    Section::make('Destinatarios')
                        ->description('Opcional por campo: puede usar solo correo, solo WhatsApp o ambos en el mismo envío.')
                        ->schema([
                            Grid::make([
                                'default' => 1,
                                'lg' => 2,
                            ])->schema([
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->maxLength(255)
                                    ->autocomplete('email')
                                    ->prefixIcon('heroicon-m-envelope')
                                    ->placeholder('ejemplo@empresa.com')
                                    ->helperText('Correo válido donde recibirán el enlace.'),
                                TextInput::make('phone')
                                    ->label('WhatsApp')
                                    ->prefixIcon('heroicon-s-phone')
                                    ->tel()
                                    ->placeholder('04127018390 o +584121234567')
                                    ->helperText('Número con WhatsApp. Venezuela: 0412… sin espacios. Extranjero: código de país (+58…, +1…).'),
                            ]),
                        ]),
                ])
                ->action(function (array $data) {

                    try {
                        $agencyCode = $data['agency_code'] ?? null;
                        if (blank($agencyCode)) {
                            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_SEND_FAILED', 'business.agencies.send-register-link', [
                                'reason' => 'missing_agency_code',
                            ]);

                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('Debe seleccionar la agencia cuyo código se usará en el enlace de registro.')
                                ->icon('heroicon-c-shield-exclamation')
                                ->color('warning')
                                ->send();

                            return false;
                        }

                        $allowedAgencyQuery = Agency::query()
                            ->where('status', 'ACTIVO')
                            ->where('code', $agencyCode);

                        if (Auth::user()->is_accountManagers) {
                            $allowedAgencyQuery->where('ownerAccountManagers', Auth::id());
                        }

                        if (! $allowedAgencyQuery->exists()) {
                            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_SEND_FAILED', 'business.agencies.send-register-link', [
                                'reason' => 'invalid_or_unauthorized_agency_code',
                                'agency_code' => $agencyCode,
                            ]);

                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('El código de agencia seleccionado no es válido o no tiene permisos para usarlo.')
                                ->icon('heroicon-c-shield-exclamation')
                                ->color('danger')
                                ->send();

                            return false;
                        }

                        $baseUrl = rtrim((string) config('parameters.INTEGRACORP_URL'), '/');
                        $link = $baseUrl.'/agency/c/'.Crypt::encryptString($agencyCode);

                        if ($data['phone'] == null && $data['email'] == null) {
                            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_SEND_FAILED', 'business.agencies.send-register-link', [
                                'reason' => 'missing_email_and_phone',
                                'agency_code' => $agencyCode,
                            ]);

                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('La notificacion no pudo ser enviada debido a que no se proporcionaron datos de contacto(Email y/o Teléfono).')
                                ->icon('heroicon-c-shield-exclamation')
                                ->color('warning')
                                ->send();

                            return false;
                        }

                        if ($data['email'] != null) {

                            $sendEmail = NotificationController::send_email_agency_register($link, $data['email']);
                            if ($sendEmail == true) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_EMAIL_SENT', 'business.agencies.send-register-link', [
                                    'recipient_email' => $data['email'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body('La notificación via email fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('success')
                                    ->send();
                            } else {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_EMAIL_FAILED', 'business.agencies.send-register-link', [
                                    'recipient_email' => $data['email'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('ENVIO FALLIDO')
                                    ->body('La notificación via email NO fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('danger')
                                    ->send();
                            }
                        }

                        if ($data['phone'] != null) {

                            $response = NotificationController::send_link_agency_register_wp($link, $data['phone']);
                            if ($response) {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_WHATSAPP_SENT', 'business.agencies.send-register-link', [
                                    'recipient_phone' => $data['phone'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('NOTIFICACION ENVIADA')
                                    ->body('La notificación via whatsapp fue enviada con exito.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('success')
                                    ->send();
                            } else {
                                SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_WHATSAPP_FAILED', 'business.agencies.send-register-link', [
                                    'recipient_phone' => $data['phone'],
                                    'agency_code' => $agencyCode,
                                ]);

                                Notification::make()
                                    ->title('ENVIO FALLIDO')
                                    ->body('La notificación vía WhatsApp no pudo enviarse. Verifique el número e intente de nuevo.')
                                    ->icon('heroicon-c-shield-check')
                                    ->color('danger')
                                    ->send();
                            }
                        }
                    } catch (\Throwable $th) {
                        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_SEND_FAILED', 'business.agencies.send-register-link', [
                            'error' => $th->getMessage(),
                            'recipient_email' => $data['email'] ?? null,
                            'recipient_phone' => $data['phone'] ?? null,
                            'agency_code' => $data['agency_code'] ?? null,
                        ]);

                        Notification::make()
                            ->title('ENVIO FALLIDO')
                            ->body($th->getMessage())
                            ->icon('heroicon-c-shield-check')
                            ->color('danger')
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @return int|array<string, int|null>
     */
    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewAgency::class,
            ControlActividadInteraccion::class,
            NewRegisterAgencyForMountChart::class,
            AgencyGeoChart::class,
            AgentActiveForEstructureChart::class,
            TotalEstructureAgency::class,
            TotalSaleForEstructureChart::class,
        ];
    }

    /**
     * Guarda una nota desde el slide-over del centro de acciones (sin cerrar el panel).
     */
    public function saveAgencyCommandCenterNoteFromSlideover(string $recordKey, string $note): void
    {
        try {
            if (! Schema::hasTable((new AgencyNoteBlog)->getTable())) {
                Notification::make()
                    ->title('No disponible')
                    ->body('El historial de notas no está disponible en esta base de datos.')
                    ->warning()
                    ->send();

                return;
            }

            $note = Str::limit(trim($note), 255, '');
            if ($note === '') {
                Notification::make()
                    ->title('Nota vacía')
                    ->body('Escriba el texto de la observación antes de guardar.')
                    ->warning()
                    ->send();

                return;
            }

            $base = Agency::query();
            if (Auth::user()?->is_accountManagers) {
                $base->where('ownerAccountManagers', Auth::id());
            }

            $agency = $base->whereKey($recordKey)->first();
            if ($agency === null) {
                Notification::make()
                    ->title('No autorizado o no encontrado')
                    ->body('No se pudo localizar la agencia o no tiene permisos para registrar la nota.')
                    ->danger()
                    ->send();

                return;
            }

            AgencyNoteBlog::create([
                'agency_id' => $agency->id,
                'note' => $note,
                'created_by' => Auth::user()->name ?? (string) Auth::id(),
            ]);

            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_OBSERVATION_ADDED', 'business.agencies.add-observation', [
                'agency_id' => $agency->id,
                'agency_code' => $agency->code,
                'note_length' => strlen($note),
                'source' => 'command_center_slideover',
            ]);

            Notification::make()
                ->title('Nota guardada con éxito')
                ->body('La nota se guardó correctamente y ya aparece en la bitácora.')
                ->success()
                ->send();
        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_OBSERVATION_ADD_FAILED', 'business.agencies.add-observation', [
                'agency_id' => $recordKey,
                'error' => $th->getMessage(),
                'source' => 'command_center_slideover',
            ]);

            Notification::make()
                ->title('No se pudo guardar la nota')
                ->body('Intente de nuevo o contacte a soporte si el problema continúa.')
                ->danger()
                ->send();
        }
    }

    public function queueAgencyFichaPdfEmail(int $agencyId, string $email): void
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Notification::make()
                ->title('Correo inválido')
                ->body('Indique una dirección de correo válida.')
                ->danger()
                ->send();

            return;
        }

        $agency = Agency::query()->find($agencyId);
        if ($agency === null) {
            Notification::make()
                ->title('Agencia no encontrada')
                ->danger()
                ->send();

            return;
        }

        if (! BusinessAgencyFichaPdfAccess::userCanAccess($agency)) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_ACCESS_DENIED', 'business.agencies.ficha-pdf.email.livewire', [
                'agency_id' => $agencyId,
                'reason' => 'forbidden',
            ]);
            Notification::make()
                ->title('Sin permiso')
                ->body('No puede enviar la ficha de esta agencia.')
                ->danger()
                ->send();

            return;
        }

        SendBusinessAgencyFichaPdfMailJob::dispatch(
            (int) $agency->getKey(),
            $email,
            (int) Auth::id(),
        );

        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_EMAIL_QUEUED', 'business.agencies.ficha-pdf.email.livewire', [
            'agency_id' => $agency->getKey(),
            'agency_name' => $agency->name_corporative,
            'recipient_email' => $email,
        ]);

        Notification::make()
            ->title('Correo encolado')
            ->body('El envío con el PDF adjunto se procesará en segundo plano.')
            ->success()
            ->send();
    }
}
