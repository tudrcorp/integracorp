<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\TelemedicinePatient;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ListTelemedicinePatients extends ListRecords
{
    /**
     * Misma apariencia que el botón "Crear Ticket" (menu-user): ticket-btn-ios en theme.css + píldora rounded-full.
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /** Misma forma iOS que TICKET_BUTTON_CLASS pero gris (theme.css .ticket-btn-ios-gray) */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Lista de Pacientes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Nuevo Paciente')
                ->icon('heroicon-s-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->hidden(fn () => in_array('ATENMEDI', Auth::user()?->departament ?? [], true)),
            Action::make('asociate_affiliate')
                ->label('Asociar Afiliado')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->modalSubmitAction(
                    fn (Action $action): Action => $action
                        ->color('success')
                        ->extraAttributes([
                            'class' => self::TICKET_BUTTON_CLASS.' min-w-[7rem] !px-6',
                        ])
                )
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->color('gray')
                        ->extraAttributes([
                            'class' => self::TICKET_BUTTON_GRAY_CLASS.' min-w-[7rem] !px-6',
                        ])
                )
                ->requiresConfirmation()
                ->modalWidth(Width::ExtraLarge)
                ->modalHeading('Seleccionar Afiliado')
                ->modalDescription('Debe seleccionar el tipo de afiliación, luego seleccione el afiliado y presione el botón "Aceptar"')
                ->modalButton('Aceptar')
                ->form([
                    Grid::make(1)
                        ->schema([
                            Fieldset::make('Tipo de Afiliación!')
                                ->schema([
                                    Radio::make('type_affiliate')
                                        ->label('Seleccione!')
                                        ->live()
                                        ->options([
                                            'inv' => 'INDIVIDUAL',
                                            'cor' => 'CORPORATIVA',
                                        ]),
                                    Grid::make(1)
                                        ->schema([
                                            Select::make('affiliate_id')
                                                ->label('Lista de Afiliados Individuales')
                                                ->options(Affiliate::all()->pluck('full_name', 'id')) // Cargamos todos para la validación, o filtramos en el query de búsqueda
                                                ->searchable()
                                                ->getSearchResultsUsing(
                                                    fn (string $search): array => Affiliate::query()
                                                        ->where(function ($query) use ($search) {
                                                            $query->where('full_name', 'like', "%{$search}%")
                                                                ->orWhere('nro_identificacion', 'like', "%{$search}%");
                                                        })
                                                        ->limit(50)
                                                        ->pluck('full_name', 'id')
                                                        ->all()
                                                )
                                                /**
                                                 * OPTIMIZACIÓN: Validación de estado ACTIVO
                                                 * Esta regla verifica que el ID seleccionado pertenezca a un afiliado con estatus 'ACTIVO'
                                                 */
                                                ->rules([
                                                    fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                                        $affiliate = Affiliate::find($value);

                                                        if ($affiliate && $affiliate->status !== 'ACTIVO') {
                                                            $fail("El afiliado seleccionado ({$affiliate->full_name}) no está activo.");
                                                        }
                                                    },
                                                ])
                                                ->validationMessages([
                                                    'required' => 'Debe seleccionar un afiliado.',
                                                ])
                                                ->native(false)
                                                ->live()
                                                ->hidden(fn (Get $get) => $get('type_affiliate') == 'cor' || $get('type_affiliate') == null),

                                            Select::make('affiliate_corporate_id')
                                                ->label('Lista de Afiliados Corporativos')
                                                ->options(AffiliateCorporate::all()->pluck('first_name', 'id'))
                                                ->searchable()
                                                ->getSearchResultsUsing(
                                                    fn (string $search): array => AffiliateCorporate::query()
                                                        ->where(function ($query) use ($search) {
                                                            $query->where('first_name', 'like', "%{$search}%")
                                                                ->orWhere('last_name', 'like', "%{$search}%")
                                                                ->orWhere('nro_identificacion', 'like', "%{$search}%");
                                                        })
                                                        ->limit(50)
                                                        ->pluck('first_name', 'id')
                                                        ->all()
                                                )
                                                /**
                                                 * VALIDACIÓN DE ESTADO:
                                                 * Permite buscar inactivos pero impide su selección final con un mensaje claro.
                                                 */
                                                ->rules([
                                                    fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                                        $affiliate = AffiliateCorporate::find($value);

                                                        if ($affiliate && $affiliate->status !== 'ACTIVO') {
                                                            $fail("El afiliado corporativo seleccionado ({$affiliate->first_name}) no está activo.");
                                                        }
                                                    },
                                                ])
                                                ->preload()
                                                ->live()
                                                ->native(false)
                                                ->hidden(fn (Get $get) => $get('type_affiliate') == 'inv' || $get('type_affiliate') == null),
                                        ])->columnSpanFull(),
                                ]),
                        ])->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    if ($data['type_affiliate'] == 'inv') {
                        $affiliation = Affiliate::where('id', $data['affiliate_id'])
                            ->with('affiliation')
                            ->get()
                            ->toArray();

                        $emailTitular = Str::lower(trim((string) ($affiliation[0]['affiliation']['email_ti'] ?? '')));

                        $attributes = [
                            'plan_id' => $affiliation[0]['affiliation']['plan_id'],
                            'coverage_id' => $affiliation[0]['affiliation']['coverage_id'],
                            'afilliation_id' => $affiliation[0]['affiliation']['id'],
                            'code_affiliation' => $affiliation[0]['affiliation']['code'],
                            'status_affiliation' => 'ACTIVO',
                            'type_affiliation' => 'INDIVIDUAL',
                            'full_name' => $affiliation[0]['full_name'],
                            'nro_identificacion' => $affiliation[0]['nro_identificacion'],
                            'birth_date' => $affiliation[0]['birth_date'],
                            'sex' => $affiliation[0]['sex'],
                            'age' => $affiliation[0]['age'],
                            'phone' => $affiliation[0]['phone'],
                            'address' => $affiliation[0]['address'],
                            'city_id' => $affiliation[0]['city_id'],
                            'country_id' => $affiliation[0]['country_id'],
                            'region' => $affiliation[0]['region'],
                            'state_id' => $affiliation[0]['state_id'],
                            'email' => $emailTitular !== '' ? $emailTitular : ($affiliation[0]['affiliation']['email_ti'] ?? null),
                            'phone_contact' => $affiliation[0]['affiliation']['phone_ti'] ?? null,
                            'email_contact' => filled($affiliation[0]['affiliation']['email_payer'] ?? null)
                                ? Str::lower(trim((string) $affiliation[0]['affiliation']['email_payer']))
                                : null,
                            'created_by' => Auth::user()->name,
                            'business_unit_id' => $affiliation[0]['affiliation']['business_unit_id'] == null ? '----' : $affiliation[0]['affiliation']['business_unit_id'],
                            'business_line_id' => $affiliation[0]['affiliation']['business_line_id'] == null ? '----' : $affiliation[0]['affiliation']['business_line_id'],
                        ];

                        $patient = $emailTitular !== ''
                            ? TelemedicinePatient::updateOrCreate(['email' => $emailTitular], $attributes)
                            : TelemedicinePatient::create($attributes);

                        Notification::make()
                            ->title($patient->wasRecentlyCreated ? 'Paciente registrado' : 'Paciente actualizado')
                            ->body(
                                $patient->wasRecentlyCreated
                                    ? 'El afiliado se asoció como paciente de telemedicina.'
                                    : 'Ya existía un paciente con ese correo; se actualizaron los datos con la afiliación seleccionada.'
                            )
                            ->success()
                            ->send();
                    }

                    if ($data['type_affiliate'] == 'cor') {
                        $affiliation = AffiliateCorporate::where('id', $data['affiliate_corporate_id'])->with('affiliationCorporate')->get()->toArray();

                        $emailKey = Str::lower(trim((string) ($affiliation[0]['email'] ?? '')));

                        $attributes = [
                            'name_corporate' => $affiliation[0]['affiliation_corporate']['name_corporate'],
                            'plan_id' => $affiliation[0]['plan_id'],
                            'coverage_id' => $affiliation[0]['coverage_id'],
                            'afilliation_corporate_id' => $affiliation[0]['affiliation_corporate']['id'],
                            'code_affiliation' => $affiliation[0]['affiliation_corporate']['code'],
                            'status_affiliation' => 'ACTIVO',
                            'type_affiliation' => 'CORPORATIVO',
                            'full_name' => $affiliation[0]['first_name'],
                            'nro_identificacion' => $affiliation[0]['nro_identificacion'],
                            'birth_date' => $affiliation[0]['birth_date'],
                            'sex' => $affiliation[0]['sex'],
                            'age' => $affiliation[0]['age'],
                            'phone' => $affiliation[0]['phone'],
                            'address' => $affiliation[0]['address'],
                            'city_id' => $affiliation[0]['affiliation_corporate']['city_id'],
                            'country_id' => $affiliation[0]['affiliation_corporate']['country_id'],
                            'region' => $affiliation[0]['affiliation_corporate']['region_id'],
                            'state_id' => $affiliation[0]['affiliation_corporate']['state_id'],
                            'email' => $emailKey !== '' ? $emailKey : ($affiliation[0]['email'] ?? null),
                            'phone_contact' => $affiliation[0]['affiliation_corporate']['phone'],
                            'email_contact' => $affiliation[0]['affiliation_corporate']['email'],
                            'created_by' => Auth::user()->name,
                            'business_unit_id' => $affiliation[0]['affiliation_corporate']['business_unit_id'] == null ? null : $affiliation[0]['affiliation_corporate']['business_unit_id'],
                            'business_line_id' => $affiliation[0]['affiliation_corporate']['business_line_id'] == null ? null : $affiliation[0]['affiliation_corporate']['business_line_id'],
                        ];

                        $patient = $emailKey !== ''
                            ? TelemedicinePatient::updateOrCreate(['email' => $emailKey], $attributes)
                            : TelemedicinePatient::create($attributes);

                        Notification::make()
                            ->title($patient->wasRecentlyCreated ? 'Paciente registrado' : 'Paciente actualizado')
                            ->body(
                                $patient->wasRecentlyCreated
                                    ? 'El afiliado corporativo se asoció como paciente de telemedicina.'
                                    : 'Ya existía un paciente con ese correo; se actualizaron los datos con la afiliación seleccionada.'
                            )
                            ->success()
                            ->send();
                    }
                })
                ->hidden(fn () => in_array('ATENMEDI', Auth::user()?->departament ?? [], true)),
        ];
    }
}
