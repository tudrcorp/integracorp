<?php

namespace App\Filament\Resources\TelemedicinePatients\Pages;

use App\Models\Affiliate;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use App\Models\AffiliateCorporate;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Resources\TelemedicinePatients\TelemedicinePatientResource;

class ListTelemedicinePatients extends ListRecords
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Pacientes';

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
                                    ->options(Affiliate::all()->pluck('full_name', 'id'))
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
                                    ->options(AffiliateCorporate::all()->pluck('first_name', 'id'))
                                    ->searchable()
                                    ->getSearchResultsUsing(
                                        fn(string $search): array => AffiliateCorporate::query()
                                            ->where('first_name', 'like', "%{$search}%")
                                            ->orwhere('last_name', 'like', "%{$search}%")
                                            ->orwhere('nro_identificacion', 'like', "%{$search}%")
                                            ->limit(50)
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
                        $affiliation = Affiliate::where('id', $data['affiliate_id'])->with('affiliation')->get()->toArray();
                        
                        //Amaceno la informacion en una variable de sesion
                        session()->put('affiliate_to_patient', $affiliation);
                        
                        dd(session()->get('affiliate_to_patient'));
                        
                    }
                    
                    if ($data['type_affiliate'] == 'cor') {
                        $affiliation = AffiliateCorporate::where('id', $data['affiliate_corporate_id'])->with('affiliationCorporate')->first();
                        dd($affiliation);
                        
                    }
                    // $this->redirect(route('filament.admin.resources.telemedicine-patients.create', [
                    //     'type_affiliate' => $data['type_affiliate'],
                    //     'affiliate_id' => $data['affiliate_id'],
                    //     // 'affiliate_corporate_id' => $data['affiliate_corporate_id'],
                    // ]));
                })
        ];
    }
}