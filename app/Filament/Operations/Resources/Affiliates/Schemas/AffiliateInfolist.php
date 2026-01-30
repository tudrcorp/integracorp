<?php

namespace App\Filament\Operations\Resources\Affiliates\Schemas;

use App\Models\Affiliate;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class AffiliateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description(fn(Affiliate $record) => 'AFILIADO: ' . $record->full_name . ' | ' . 'EDAD: ' . $record->age . ' años | ' . 'SEXO: ' . $record->sex)
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('INFORMACIÓN PRINCIPAL')
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre Completo:')
                                    ->badge()
                                    ->default(fn(Affiliate $record) => strtoupper($record->full_name))
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),
                                TextEntry::make('nro_identificacion')
                                    ->label('Número de Identificación:')
                                    ->prefix('V-')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('birth_date')
                                    ->label('Fecha de Nacimiento:'),
                                TextEntry::make('age')
                                    ->label('Edad:')
                                    ->suffix(' años'),
                                TextEntry::make('sex')
                                    ->label('Sexo:'),
                                TextEntry::make('phone')
                                    ->label('Teléfono:'),
                                TextEntry::make('email')
                                    ->label('Correo Electrónico:'),
                                TextEntry::make('address')
                                    ->label('Dirección:'),
                                TextEntry::make('city.definition')
                                    ->label('Ciudad:'),
                                TextEntry::make('country.name')
                                    ->label('País:'),
                                TextEntry::make('state.definition')
                                    ->label('Estado:'),
                                TextEntry::make('region')
                                    ->label('Región:'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de Registro:')
                                    ->badge()
                                    ->dateTime(),

                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('INFORMACIÓN DE LA AFILIACIÓN')
                            ->schema([
                                TextEntry::make('affiliation.code')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Código de Afiliación:'),
                                TextEntry::make('plan.description')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-person-available-16')
                                    ->label('Plan:'),
                                TextEntry::make('plan.businessUnit.definition')
                                    ->label('Unidad de Negocio:')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-person-available-16'),
                                TextEntry::make('coverage.price')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Cobertura:'),
                                TextEntry::make('affiliation.effective_date')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Fecha de Vigencia:'),
                                TextEntry::make('affiliation.service_providers')
                                    ->label('Proveedor de Servicios:')
                                    ->icon('fluentui-money-hand-20')
                                    ->badge()
                                    ->color('success')
                                    ->listWithLineBreaks(),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Estatus del Afiliado:'),
                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('INFORMACIÓN DEL PAGADOR')
                            ->schema([
                                TextEntry::make('affiliation.full_name_payer')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Nombre Y Apellido:'),
                                TextEntry::make('affiliation.nro_identificacion_payer')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-person-available-16')
                                    ->label('Número de Identificación:'),
                                TextEntry::make('affiliation.phone_payer')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-person-available-16')
                                    ->label('Teléfono:'),
                                TextEntry::make('affiliation.email_payer')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-person-available-16')
                                    ->label('Correo Electrónico:'),
                                
                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('CUESTIONARIO DE AFILIACIÓN')
                            ->hidden(fn($record) => $record->plan_id != 3)
                            ->schema([
                                IconEntry::make('affiliation.cuestion_1')
                                    ->label('¿Usted y el grupo de beneficiarios solicitantes, gozan de buena salud?')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_2')
                                    ->label('¿Usted o el grupo de beneficiarios presentan alguna condición médica o congénita?')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_3')
                                    ->label('¿Usted o el grupo de beneficiario ha sido intervenido quirúrgicamente? ')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_4')
                                    ->label('¿Usted o el grupo de beneficiario padece o ha padecido alguna enfermedad?')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_5')
                                    ->label('Enfermedades Cardiovasculares, tales como; Hipertensión Arterial, Ataque cardíaco, Angina o dolor de pecho,
                                            Soplo Cardíaco, Insuficiencia Cardíaca Congestiva o desórdenes del corazón o sistema circulatorio.')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_6')
                                    ->label('Enfermedades Cerebrovasculares, tales como: Desmayos, confusión, parálisis de miembros, dificultad para
                                            hablar, articular y entender, Accidente Cerebro-vascular (ACV). Cefalea o migraña. Epilepsia o Convulsiones.
                                            Otros trastornos o enfermedad del Cerebro o Sistema Nervioso.')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_7')
                                    ->label('Enfermedades Respiratorias, tales como: Asma Bronquial, Bronquitis, Bronquiolitis, Enfisema, Neumonía, Enfer-
                                            medad pulmonar Obstructiva Crónica (EPOC) u otras enfermedades del Sistema Respiratorio.')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_8')
                                    ->label('Enfermedades o Trastornos Endocrinos tales como: Diabetes Mellitus, Bocio, hipertiroidismo, hipotiroidismo,
                                            Tiroiditis, Resistencia a la insulina, enfermedad de Cushing, cáncer de tiroides.')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_9')
                                    ->label('Enfermedades Gastrointestinales como: Litiasis vesicular, Cólico Biliar, Úlcera gástrica, gastritis, Hemorragia
                                            digestivas, colitis, hemorroides, Apendicitis, Peritonitis, Pancreatitis u otros desórdenes del estómago, intestino,
                                            hígado o vesícula biliar.')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_10')
                                    ->label('Enfermedades Renales: Litiasis renal, Cólico nefrítico, Sangre en la orina o Hematuria, Cistitis, Infecciones
                                            urinarias, Pielonefritis, Insuficiencia renal aguda. Otras enfermedades del riñón, vejiga o próstata.')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_11')
                                    ->label('Enfermedades Osteoarticulares, Artrosis, Artritis reumatoide, Traumatismo craneoencefálico, Fracturas óseas,
                                            Luxaciones o esguinces, tumores óseos, u otros trastornos de los músculos, articulaciones o columna vertical o
                                            espalda.')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_12')
                                    ->label('¿Ha sufrido o padece de alguna enfermedad de la Piel como: Dermatitis, Celulitis, Abscesos cutáneos, quistes,
                                            tumores o cáncer? ,Quemaduras o Heridas Complicadas.')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_13')
                                    ->label('¿Padece de alguna enfermedad o desorden de los ojos, oídos, nariz o garganta?')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_14')
                                    ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamen-
                                            tosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_15')
                                    ->label('¿Usted o alguno de los solicitantes, toma algún tipo de medicamentos por tratamiento prolongado?')
                                    ->boolean(),
                                IconEntry::make('affiliation.cuestion_16')
                                    ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamen-
                                            tosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?')
                                    ->boolean(),
                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('INFORMACIÓN DE BENEFICIOS Y SUS LIMITES')
                            ->schema([
                                TextEntry::make('plan.benefitPlans.description')
                                    ->label('Beneficios del Plan:')
                                    ->icon('heroicon-c-check')
                                    ->badge()
                                    ->color('success')
                                    // ->bulleted()
                                    ->listWithLineBreaks(),
                                TextEntry::make('plan.benefitPlans.limit.description')
                                    // ->belowContent(Text::make('This is the user\'s full name.')->weight(FontWeight::Bold))
                                    ->label('Limite por Beneficios:')
                                    ->icon('heroicon-s-arrow-small-right')
                                    ->badge()
                                    ->color('gray')
                                    // ->bulleted()
                                    ->listWithLineBreaks(),
                            ])->columnSpanFull()->columns(2),
                    ])->columnSpanFull(),
            ]);
    }
}
