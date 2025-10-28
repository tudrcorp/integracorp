<?php

namespace App\Filament\Resources\TelemedicinePatients\Pages;

use App\Models\Plan;
use App\Models\Affiliate;
use App\Models\BusinessLine;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use App\Models\AffiliateCorporate;
use Filament\Actions\CreateAction;
use App\Models\TelemedicinePatient;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use App\Http\Controllers\UtilsController;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Resources\TelemedicinePatients\TelemedicinePatientResource;

class ListTelemedicinePatients extends ListRecords
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Gestión de Pacientes Afiliados y No Afiliados';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Paciente')
                ->icon('heroicon-s-plus'),
            Action::make('asociate_affiliate')
                ->label('Asociar Afiliado')
                ->color('verde')
                ->icon('heroicon-o-plus')
                ->requiresConfirmation()
                ->modalWidth(Width::ExtraLarge)
                ->modalHeading('Asociar Afiliado')
                ->modalDescription('Debe seleccionar el tipo de afiliación, luego seleccione el afiliado y presione el botón "Asociar"')
                ->modalButton('Asociar')
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
                                    ->options(Affiliate::all()->where('status', 'ACTIVO')->pluck('full_name', 'id'))
                                    ->searchable()
                                    ->getSearchResultsUsing(fn(string $search): array => Affiliate::query()
                                        ->where('full_name', 'like', "%{$search}%")
                                        ->orwhere('nro_identificacion', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->pluck('full_name', 'id')
                                        ->all()
                                    )
                                    ->native(false)
                                    ->live()
                                    ->hidden(fn(Get $get) => $get('type_affiliate') == 'cor' || $get('type_affiliate') == null),
                                Select::make('affiliate_corporate_id')
                                    ->label('Lista de Afiliados Corporativos')
                                    ->options(AffiliateCorporate::all()->where('status', 'ACTIVO')->pluck('first_name', 'id'))
                                    ->searchable()
                                    ->getSearchResultsUsing(
                                        fn(string $search): array => AffiliateCorporate::query()
                                            ->where('first_name', 'like', "%{$search}%")
                                            ->orwhere('last_name', 'like', "%{$search}%")
                                            ->orwhere('nro_identificacion', 'like', "%{$search}%")
                                            ->pluck('first_name', 'id')
                                            ->all()
                                    )
                                    ->preload()
                                    ->live()
                                    ->hidden(fn(Get $get) => $get('type_affiliate') == 'inv' || $get('type_affiliate') == null),
                            ])->columnSpanFull()
                        ])
                    ])->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    
                    if($data['type_affiliate'] == 'inv') {
                        
                        $affiliation = Affiliate::where('id', $data['affiliate_id'])
                        ->with('affiliation')
                        ->get()
                        ->toArray();

                        $patient = TelemedicinePatient::create([
                            
                            //Informacion de la Afiliacion
                            'plan_id'                   => $affiliation[0]['affiliation']['plan_id'],
                            'coverage_id'               => $affiliation[0]['affiliation']['coverage_id'],
                            'afilliation_id'            => $affiliation[0]['affiliation']['id'],
                            'code_affiliation'          => $affiliation[0]['affiliation']['code'],
                            'status_affiliation'        => 'ACTIVO',
                            'type_affiliation'          => 'INDIVIDUAL',
                            
                            //Informacion del Afiliado -> Paciente
                            'full_name'                 => $affiliation[0]['full_name'],
                            'nro_identificacion'        => $affiliation[0]['nro_identificacion'],
                            'birth_date'                => $affiliation[0]['birth_date'],
                            'sex'                       => $affiliation[0]['sex'],
                            'age'                       => $affiliation[0]['age'],
                            'phone'                     => $affiliation[0]['phone'],
                            'address'                   => $affiliation[0]['address'],
                            'city_id'                   => $affiliation[0]['city_id'],
                            'country_id'                => $affiliation[0]['country_id'],
                            'region'                    => $affiliation[0]['region'],
                            'state_id'                  => $affiliation[0]['state_id'],
                            
                            //Informacion del titular
                            'email'                     => $affiliation[0]['affiliation']['email_ti'],
                            'phone_contact'             => $affiliation[0]['affiliation']['email_ti'],
                            'email_contact'             => $affiliation[0]['affiliation']['phone_ti'],
                            'created_by'                => Auth::user()->name,

                        //Unidad de Negocios
                        'business_unit_id'          => $affiliation[0]['affiliation']['business_unit_id'] == NULL ? '----' : $affiliation[0]['affiliation']['business_unit_id'],
                        'business_line_id'          => $affiliation[0]['affiliation']['business_line_id'] == NULL ? '----' : $affiliation[0]['affiliation']['business_line_id']
                        ]);
                        
                        
                    }
                    
                    if ($data['type_affiliate'] == 'cor') {
                        
                            $affiliation = AffiliateCorporate::where('id', $data['affiliate_corporate_id'])->with('affiliationCorporate')->get()->toArray();

                            $patient = TelemedicinePatient::create([

                        //Informacion de la Afiliacion
                                'name_corporate'            => $affiliation[0]['affiliation_corporate']['name_corporate'],
                                'plan_id'                   => $affiliation[0]['plan_id'],
                                'coverage_id'               => $affiliation[0]['coverage_id'],
                                'afilliation_corporate_id'  => $affiliation[0]['affiliation_corporate']['id'],
                                'code_affiliation'          => $affiliation[0]['affiliation_corporate']['code'],
                                'status_affiliation'        => 'ACTIVO',
                                'type_affiliation'          => 'CORPORATIVO',

                                //Informacion del Afiliado -> Paciente
                                'full_name'                 => $affiliation[0]['first_name'],
                                'nro_identificacion'        => $affiliation[0]['nro_identificacion'],
                                'birth_date'                => $affiliation[0]['birth_date'],
                                'sex'                       => $affiliation[0]['sex'],
                                'age'                       => $affiliation[0]['age'],
                                'phone'                     => $affiliation[0]['phone'],
                                'address'                   => $affiliation[0]['address'],
                                'city_id'                   => $affiliation[0]['affiliation_corporate']['city_id'],
                                'country_id'                => $affiliation[0]['affiliation_corporate']['country_id'],
                                'region'                    => $affiliation[0]['affiliation_corporate']['region_id'],
                                'state_id'                  => $affiliation[0]['affiliation_corporate']['state_id'],

                                //Informacion del titular
                                'email'                     => $affiliation[0]['email'],
                                'phone_contact'             => $affiliation[0]['affiliation_corporate']['phone'],
                                'email_contact'             => $affiliation[0]['affiliation_corporate']['email'],
                                'created_by'                => Auth::user()->name,

                                //Unidad de Negocios
                                'business_unit_id'          => $affiliation[0]['affiliation_corporate']['business_unit_id'] == NULL ? null : $affiliation[0]['affiliation_corporate']['business_unit_id'],
                                'business_line_id'          => $affiliation[0]['affiliation_corporate']['business_line_id'] == NULL ? null : $affiliation[0]['affiliation_corporate']['business_line_id']
                            ]);
                        
                    }

                })
        ];
    }
}