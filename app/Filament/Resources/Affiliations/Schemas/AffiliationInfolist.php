<?php

namespace App\Filament\Resources\Affiliations\Schemas;

use App\Models\Affiliation;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

class AffiliationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

            Section::make()
                ->description(fn(Affiliation $record) => 'Pre-afiliación generada el: ' . $record->created_at->format('d/m/Y H:ma') . ' - Creada por: ' . $record->created_by)
                ->columnSpanFull()
                ->icon(Heroicon::Bars3BottomLeft)
                ->schema([
                    Fieldset::make('PREAFILIACION')
                        ->schema([
                            TextEntry::make('code')
                                ->label('Nro. de solicitud:')
                                ->badge()
                                ->color('success'),
                            TextEntry::make('individual_quote.code')
                                ->label('Nro. de cotización:')
                                ->badge()
                                ->color('success'),
                            // ...
                            TextEntry::make('created_by')
                                ->label('Registrado por:')
                                ->badge()
                                ->color('primary')
                                ->default(fn(Affiliation $record) => 'AGT-000' . $record->agent_id . ' : ' . $record->full_name),
                            TextEntry::make('created_at')
                                ->label('Fecha:')
                                ->badge()
                                ->icon(Heroicon::CalendarDays)
                                ->dateTime(),
                            TextEntry::make('status')
                                ->label('Estatus de la Afiliación:')
                                ->badge()
                                ->color('warning')
                                ->icon(Heroicon::InformationCircle),
                            TextEntry::make('activation_date')
                                ->label('Fecha de Activación:')
                                ->badge()
                                ->color('warning')
                                ->icon(Heroicon::InformationCircle)
                                
                        ])->columnSpanFull()->columns(5),

                    Fieldset::make('TITULAR DE LA PÓLIZA')
                        ->schema([
                            TextEntry::make('full_name_ti')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-person-available-16')
                                ->label('Nombre y Apellido:'),
                            TextEntry::make('nro_identificacion_ti')
                                ->label('Nro. de Identificación:'),
                            TextEntry::make('email_ti')
                                ->label('Correo electrónico:'),
                            TextEntry::make('phone_ti')
                                ->label('Número de teléfono:'),
                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('RESPONSABLE DE PAGO (PAGADOR)')
                        ->schema([
                            TextEntry::make('full_name_payer')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Nombre y Apellido:'),
                            TextEntry::make('nro_identificacion_payer')
                                ->label('Nro. de Identificación:'),
                            TextEntry::make('email_payer')
                                ->label('Correo electrónico:'),
                            TextEntry::make('phone_payer')
                                ->label('Número de teléfono:'),
                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('PLAN Y FRECUENCIA DE PAGO')
                        ->schema([
                            TextEntry::make('plan.description')
                                ->label('Plan')
                                ->badge()
                                ->color('primary')
                                ->numeric(),
                            TextEntry::make('coverage.price')
                                ->label('Precio')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('fee_anual')
                                ->label('Tarifa anual')
                                ->prefix('US$ ')
                                ->badge()
                                ->color('primary')
                                ->numeric(),
                            TextEntry::make('payment_frequency')
                                ->label('Frecuencia de pago')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('total_amount')
                                ->label('Monto total')
                                ->prefix('US$ ')
                                ->badge()
                                ->color('success')
                                ->numeric(),
                            TextEntry::make('family_members')
                                ->label('Miembros de la familia')
                                ->suffix(' Persona(s)')
                                ->badge()
                                ->color('primary')
                                ->numeric(),
                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('INFORMACIÓN ILS')
                        ->schema([
                            TextEntry::make('vaucher_ils')
                                ->label('Voucher ILS')
                                ->badge()
                                ->color('primary')
                                ->icon(Heroicon::DocumentText), 
                            TextEntry::make('date_payment_initial_ils')
                                ->label('Fecha de inicio de cobro:')
                                ->badge()
                                ->color('primary')
                                ->icon(Heroicon::CalendarDays),
                            TextEntry::make('date_payment_final_ils')
                                ->label('Fecha de fin de cobro:')
                                ->badge()
                                ->color('primary')
                                ->icon(Heroicon::CalendarDays),
                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('DECLARACIÓN MÉDICA')
                        ->schema([
                                IconEntry::make('cuestion_1')
                                    ->label('¿Usted y el grupo de beneficiarios solicitantes, gozan de buena salud?')
                                    ->boolean(),
                                IconEntry::make('cuestion_2')
                                    ->label('¿Usted o el grupo de beneficiarios presentan alguna condición médica o congénita?')
                                    ->boolean(),
                                IconEntry::make('cuestion_3')
                                    ->label('¿Usted o el grupo de beneficiario ha sido intervenido quirúrgicamente? ')
                                    ->boolean(),
                                IconEntry::make('cuestion_4')
                                    ->label('¿Usted o el grupo de beneficiario padece o ha padecido alguna enfermedad?')
                                    ->boolean(),
                                IconEntry::make('cuestion_5')
                                    ->label('Enfermedades Cardiovasculares, tales como; Hipertensión Arterial, Ataque cardíaco, Angina o dolor de pecho,
                                                                Soplo Cardíaco, Insuficiencia Cardíaca Congestiva o desórdenes del corazón o sistema circulatorio.')
                                    ->boolean(),
                                IconEntry::make('cuestion_6')
                                    ->label('Enfermedades Cerebrovasculares, tales como: Desmayos, confusión, parálisis de miembros, dificultad para
                                                                hablar, articular y entender, Accidente Cerebro-vascular (ACV). Cefalea o migraña. Epilepsia o Convulsiones.
                                                                Otros trastornos o enfermedad del Cerebro o Sistema Nervioso.')
                                    ->boolean(),
                                IconEntry::make('cuestion_7')
                                    ->label('Enfermedades Respiratorias, tales como: Asma Bronquial, Bronquitis, Bronquiolitis, Enfisema, Neumonía, Enfer-
                                                                medad pulmonar Obstructiva Crónica (EPOC) u otras enfermedades del Sistema Respiratorio.')
                                    ->boolean(),
                                IconEntry::make('cuestion_8')
                                    ->label('Enfermedades o Trastornos Endocrinos tales como: Diabetes Mellitus, Bocio, hipertiroidismo, hipotiroidismo,
                                                            Tiroiditis, Resistencia a la insulina, enfermedad de Cushing, cáncer de tiroides.')
                                    ->boolean(),
                                IconEntry::make('cuestion_9')
                                    ->label('Enfermedades Gastrointestinales como: Litiasis vesicular, Cólico Biliar, Úlcera gástrica, gastritis, Hemorragia
                                                            digestivas, colitis, hemorroides, Apendicitis, Peritonitis, Pancreatitis u otros desórdenes del estómago, intestino,
                                                            hígado o vesícula biliar.')
                                    ->boolean(),
                                IconEntry::make('cuestion_10')
                                    ->label('Enfermedades Renales: Litiasis renal, Cólico nefrítico, Sangre en la orina o Hematuria, Cistitis, Infecciones
                                                            urinarias, Pielonefritis, Insuficiencia renal aguda. Otras enfermedades del riñón, vejiga o próstata.')
                                    ->boolean(),
                                IconEntry::make('cuestion_11')
                                    ->label('Enfermedades Osteoarticulares, Artrosis, Artritis reumatoide, Traumatismo craneoencefálico, Fracturas óseas,
                                                            Luxaciones o esguinces, tumores óseos, u otros trastornos de los músculos, articulaciones o columna vertical o
                                                            espalda.')
                                    ->boolean(),
                                IconEntry::make('cuestion_12')
                                    ->label('¿Ha sufrido o padece de alguna enfermedad de la Piel como: Dermatitis, Celulitis, Abscesos cutáneos, quistes,
                                                            tumores o cáncer? ,Quemaduras o Heridas Complicadas.')
                                    ->boolean(),
                                IconEntry::make('cuestion_13')
                                    ->label('¿Padece de alguna enfermedad o desorden de los ojos, oídos, nariz o garganta?')
                                    ->boolean(),
                                IconEntry::make('cuestion_14')
                                    ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamen-
                                                            tosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?')
                                    ->boolean(),
                                IconEntry::make('cuestion_15')
                                    ->label('¿Usted o alguno de los solicitantes, toma algún tipo de medicamentos por tratamiento prolongado?')
                                    ->boolean(),
                                IconEntry::make('cuestion_16')
                                    ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamen-
                                                            tosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?')
                                    ->boolean(),
                        ])->columnSpanFull()->columns(3),
                ])->columnSpanFull(),






























                
               
                TextEntry::make('activation_date'),
            ]);
    }
}