<?php

namespace App\Filament\Business\Resources\Affiliations\Schemas;

use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;

class AffiliationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make()
                    ->collapsible()
                    ->description(fn(Affiliation $record) => 'Afiliación generada el: ' . $record->created_at->format('d/m/Y H:ma') . ' - Creada por: ' . $record->created_by)
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('AFILIACION')
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
                                    ->color('success')
                                    ->icon(Heroicon::CheckCircle),
                                TextEntry::make('activation_date')
                                    ->label('Fecha de Activación:')
                                    ->badge()
                                    ->color('success')
                                    ->icon(Heroicon::InformationCircle)

                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('TITULAR DEL PLAN')
                            ->schema([
                                TextEntry::make('full_name_ti')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-person-available-16')
                                    ->label('Nombre y Apellido:'),
                                TextEntry::make('nro_identificacion_ti')
                                    ->label('Nro. de Identificación:'),
                                TextEntry::make('phone_ti')
                                    ->label('Número de teléfono:'),
                                TextEntry::make('email_ti')
                                    ->label('Correo electrónico:'),
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
                                    ->formatStateUsing(fn(Affiliation $record) => number_format((float)$record->coverage->price, 0, ',', '.'))
                                    ->label('Cobertura')
                                    ->prefix('US$ ')
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

                        Fieldset::make('ALIADO DE SERVICIO NIVEL 1')
                            ->schema([
                                TextEntry::make('aliado_1_name')
                                    ->label('Nombre del Aliado')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::DocumentText),
                                TextEntry::make('date_init_aliado_1')
                                    ->label('Fecha de inicio:')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('date_end_aliado_1')
                                    ->label('Fecha de Vencimiento:')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::CalendarDays),
                                IconEntry::make('vaucher_aliado_1')
                                    ->label('Ver Voucher')
                                    ->icon(function (Affiliation $record) {
                                        // Muestra un ícono si la imagen existe
                                        return $record->vaucher_aliado_1 != null
                                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                                    })
                                    ->color(function (Affiliation $record) {
                                        // Color del ícono basado en la existencia de la imagen
                                        return $record->vaucher_aliado_1 != null
                                            ? 'success' // Verde si la imagen existe
                                            : 'danger'; // Rojo si no existe
                                    })
                                    ->url(function (Affiliation $record) {
                                        return asset('storage/' . $record->vaucher_aliado_1);
                                    })
                                    ->openUrlInNewTab(),
                                // ->size(IconSize::Medium)->boolean()->action(Action::make('activate')->label('Activar')->color('success')->icon(Heroicon::CheckCircle)),
                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('ALIADO DE SERVICIO NIVEL 2')
                            ->schema([
                                TextEntry::make('affiliates.vaucherIls')
                                    ->label('Número de Voucher')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::DocumentText),
                                TextEntry::make('affiliates.dateInit')
                                    ->label('Fecha de inicio:')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('affiliates.dateEnd')
                                    ->label('Fecha de Vencimiento:')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::CalendarDays),
                                IconEntry::make('affiliates.document_ils')
                                    ->label('Ver Voucher')
                                    ->icon(function (Affiliation $record) {
                                        // Muestra un ícono si la imagen existe
                                        return $record->affiliates[0]->document_ils != null
                                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                                    })
                                    ->color(function (Affiliation $record) {
                                        // Color del ícono basado en la existencia de la imagen
                                        return $record->affiliates[0]->document_ils != null
                                            ? 'success' // Verde si la imagen existe
                                            : 'danger'; // Rojo si no existe
                                    })
                                    ->url(function (Affiliation $record) {
                                        return asset('storage/' . $record->affiliates[0]->document_ils);
                                    })
                                    ->openUrlInNewTab(),
                                    // ->size(IconSize::Medium)->boolean()->action(Action::make('activate')->label('Activar')->color('success')->icon(Heroicon::CheckCircle)),
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
            ]);
    }
}