<?php

namespace App\Livewire;

use App\Filament\Shared\MedicalQuestionnaires\MedicalQuestionnaireFields;
use App\Models\AffiliationCorporate;
use App\Models\City;
use App\Models\CorporateQuote;
use App\Models\Country;
use App\Models\DetailCorporateQuote;
use App\Models\Region;
use App\Models\State;
use App\Models\User;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireAnswers;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireCatalog;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;

class CorporatePreAffiliation extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public $id;

    public function mount($id): void
    {
        $this->id = $id;
        $this->form->fill();
    }

    public function form(Form $form): Form
    {

        return $form
            ->schema([
                Section::make('AFILIACION')
                    ->collapsible()
                    ->description('Fomulario de afiliacion. Campo Requerido(*)')
                    ->icon('heroicon-s-building-library')
                    ->schema([
                        Select::make('plan_id')
                            ->label('Plan')
                            ->live()
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->options(function () {
                                $corporate_quote_id = Crypt::decryptString($this->id);
                                $plans = DetailCorporateQuote::join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                                    ->join('corporate_quotes', 'detail_corporate_quotes.corporate_quote_id', '=', 'corporate_quotes.id')
                                    ->where('corporate_quotes.id', $corporate_quote_id)
                                    ->select('plans.id as plan_id', 'plans.description as description')
                                    ->distinct() // Asegurarse de que no haya duplicados
                                    ->get()
                                    ->pluck('description', 'plan_id');

                                return $plans;
                            }),
                        TextInput::make('date_today')
                            ->label('Fecha')
                            ->default(now()->format('d-m-Y'))
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                    ])->columns(4),
                Section::make('INFORMACION CORPORATIVA DEL CONTRATANTE')
                    ->description('Campo Requerido(*)')
                    // ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        TextInput::make('full_name_con')
                            ->label('Razon social')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('full_name_con', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->required()
                            ->validationMessages([
                                'required' => 'Campo requerido',
                            ])
                            ->maxLength(255),
                        TextInput::make('rif')
                            ->label('Rif:')
                            ->prefix('J-')
                            ->numeric()
                            ->unique(
                                ignoreRecord: true,
                                table: 'affiliations',
                                column: 'nro_identificacion_con',
                            )
                            ->required()
                            ->validationMessages([
                                'unique' => 'El RIF ya se encuentra registrado.',
                                'required' => 'Campo requerido',
                                'numeric' => 'El campo es numerico',
                            ])
                            ->required(),

                        // DatePicker::make('birth_date_con')
                        //     ->label('Fecha de Nacimiento')
                        //     ->prefixIcon('heroicon-m-calendar-days')
                        //     ->displayFormat('d/m/Y')
                        //     ->required()
                        //     ->validationMessages([
                        //         'required'  => 'Campo Requerido',
                        //     ]),
                        TextInput::make('email_con')
                            ->label('Email')
                            ->prefixIcon('heroicon-s-at-symbol')
                            ->email()
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                table: 'affiliations',
                                column: 'email_con',
                            )
                            ->validationMessages([
                                'unique' => 'El Correo electrónico ya se encuentra registrado.',
                                'required' => 'Campo requerido',
                                'email' => 'El campo es un email',
                            ])
                            ->maxLength(255),
                        TextInput::make('adress_con')
                            ->label('Dirección')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('adress_con', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->required()
                            ->validationMessages([
                                'required' => 'Campo Requerido',
                            ])
                            ->maxLength(255),
                        Select::make('country_code_con')
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
                                '+375' => '🇧🇾 +375 (Bielorrusia)',
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
                            ->live(onBlur: true),
                        TextInput::make('phone_con')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->label('Número de teléfono')
                            ->required()
                            ->validationMessages([
                                'required' => 'Campo Requerido',
                            ])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                $countryCode = $get('country_code_con');
                                if ($countryCode) {
                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                    $set('phone_con', $countryCode.$cleanNumber);
                                }
                            }),
                        Select::make('country_id_con')
                            ->label('País')
                            ->live()
                            ->options(Country::all()->pluck('name', 'id'))
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->required()
                            ->validationMessages([
                                'required' => 'Campo Requerido',
                            ])
                            ->preload(),
                        Select::make('state_id_con')
                            ->label('Estado')
                            ->options(function (Get $get) {
                                return State::where('country_id', $get('country_id_con'))->pluck('definition', 'id');
                            })
                            ->afterStateUpdated(function (Set $set, $state) {
                                $region_id = State::where('id', $state)->value('region_id');
                                $region = Region::where('id', $region_id)->value('definition');
                                $set('region_con', $region);
                            })
                            ->live()
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->required()
                            ->validationMessages([
                                'required' => 'Campo Requerido',
                            ])
                            ->preload(),
                        TextInput::make('region_con')
                            ->label('Región')
                            ->prefixIcon('heroicon-m-map')
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                        Select::make('city_id_con')
                            ->label('Ciudad')
                            ->options(function (Get $get) {
                                return City::where('country_id', $get('country_id_con'))->where('state_id', $get('state_id_con'))->pluck('definition', 'id');
                            })
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->required()
                            ->validationMessages([
                                'required' => 'Campo Requerido',
                            ])
                            ->preload(),
                    ])->columns(4),
                Section::make('DECLARACION DE CONDICIONES MEDICAS')
                    ->description('(Sólo para solicitantes del Plan Especial). Responda Si o No, tomando en cuenta todos los solicitantes. Las respuestas afirmativas deben ser ampliadas.')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        MedicalQuestionnaireFields::make(),
                    ])
                    ->hidden(fn (Get $get): bool => ! MedicalQuestionnaireCatalog::planHasQuestionnaire($get('plan_id'))),
                Section::make('ACUERDO Y CONDICIONES')
                    ->collapsed()
                    ->description(function (Get $get) {
                        if ($get('plan_id') == 1 || $get('plan_id') == 2) {
                            return 'Estoy de acuerdo en aceptar la cobertura domiciliaria para patologías agudas del plan seleccionado, bajo los términos y condiciones con que sea
                                       emitido. De no ser así, notificare mi desacuerdo por escrito, durante los quince (15) días siguientes.';
                        }
                        if ($get('plan_id') == 3) {
                            return 'Certifico que he leído todas las respuestas y declaraciones en esta solicitud y que a mi mejor entendimiento, están completas y son verdaderas.
                                    Entiendo que cualquier omisión o declaración incompleta o incorrecta puede causar que las reclamaciones sean negadas y que el plan sea modificado, rescindido
                                    o cancelado.
                                    Estoy de acuerdo en aceptar la cobertura bajo los términos y condiciones con que sea emitida.
                                    De no ser así , notificaré mi desacuerdo por escrito a la compañía durante los quince (15) días siguientes al recibir el certificado de cobertura.
                                    Como Agente, acepto completa responsabilidad por el envío de esta solicitud, todas las primas cobradas y por la entrega de la póliza cuando sea emitida.
                                    Desconozco la existencia de cualquier condición que no haya sido revelada en esta solicitud que pudiera afectar la asegurabilidad de los propuestos asegurados.';
                        }
                    })
                    ->icon('heroicon-m-folder-plus')
                    ->schema([
                        Checkbox::make('is_accepted')
                            ->label('ACEPTO')
                            ->required(),
                        Grid::make(3)->schema([
                            TextInput::make('full_name_agent')
                                ->label('Nombre del agente')
                                ->default(Auth::user()->name)
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255),
                            TextInput::make('date_today')
                                ->label('Fecha')
                                ->disabled()
                                ->dehydrated()
                                ->default(now()->format('d-m-Y'))
                                ->maxLength(255),
                        ]),
                    ])->columns(3),
                Section::make('AFILIADOS')
                    ->description('Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([]),
            ])->statePath('data');
    }

    public function create(): void
    {

        $data = $this->form->getState();

        if ($data['is_accepted'] == false) {
            Notification::make()
                ->title('NOTIFICACION')
                ->body('Debe aceptar el acuerdo y condiciones para poder proseguir con la afiliacion.')
                ->icon('heroicon-m-user-plus')
                ->iconColor('error')
                ->danger()
                ->send();

            return;
        }

        // Infomacion de la cotizacion
        $corporate_quote_id = Crypt::decryptString($this->id);
        $corporate_quote = CorporateQuote::find($corporate_quote_id);
        // dd($corporate_quote);

        // Logica para genera el codgio de afiliacion
        if (AffiliationCorporate::withTrashed()->max('id') == null) {
            $parte_entera = 0;
        } else {
            $parte_entera = AffiliationCorporate::withTrashed()->max('id');
        }

        $affiliation = AffiliationCorporate::create([
            'corporate_quote_id' => $corporate_quote_id,
            'code' => 'TDEC-AFC-000'.$parte_entera + 1,
            'code_agency' => $corporate_quote->code_agency,
            'plan_id' => $data['plan_id'],

            'full_name_con' => $data['full_name_con'],
            'rif' => $data['rif'],
            'adress_con' => $data['adress_con'],
            'city_id_con' => $data['city_id_con'],
            'state_id_con' => $data['state_id_con'],
            'country_id_con' => $data['country_id_con'],
            'region_con' => $data['region_con'],
            'phone_con' => $data['phone_con'],
            'email_con' => $data['email_con'],

            'full_name_agent' => $corporate_quote->created_by,
            'date_today' => $data['date_today'],

            'status' => 'APROBADA-POR-PAGAR',
            'created_by' => 'LINK EXTERNO',
        ]);

        MedicalQuestionnaireAnswers::save(
            $affiliation,
            $data['plan_id'] ?? null,
            (array) ($this->form->getRawState()[MedicalQuestionnaireFields::STATE_PATH] ?? []),
            'LINK EXTERNO',
        );

        Notification::make()
            ->title('AFILIACION CORPORATIVA REGISTRADA')
            ->body('El registro de la afiliacion fue enviado con exito')
            ->icon('heroicon-m-user-plus')
            ->iconColor('success')
            ->success()
            ->seconds(5)
            ->send();

        // Notificacion para Admin
        $recipient = User::where('is_admin', 1)->get();
        foreach ($recipient as $user) {
            $recipient_for_user = User::find($user->id);
            Notification::make()
                ->title('NUEVA AFILIACION CORPORATIVA')
                ->body('Se ha registrado una nueva afiliacion corporativa de forma exitosa')
                ->icon('heroicon-m-user-plus')
                ->iconColor('success')
                ->success()
                ->sendToDatabase($recipient_for_user);
        }

        $this->form->fill();

        // redirect
        $this->redirect('/at/c');
    }

    public function render()
    {
        return view('livewire.corporate-pre-affiliation');
    }
}
