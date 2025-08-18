<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TelemedicineExamenList;
use App\Models\TelemedicineStudiesList;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class TelemedicineExamenListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [

            //Hematología y Coagulación

            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Conteo de Eosinófilos en Sangre Nasal',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Conteo de Leucocitos y Plaquetas',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Conteo de Reticulocitos',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Dimero D',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Fibrinogeno',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Frotis de sangre periférica',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Hematocrito y hemoglobina',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Hematología completa',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Hemoglobina Glicada A1C',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Hemoparásitos',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Prueba de Coombs directo/ indirecto',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Tiempo de coagulación',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Tiempo de Protrombina (TP)',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Tiempo de sangría de duke',
                'category' => 'Hematología y Coagulación',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Tiempo de Tromboplastina (TPT)',
                'category' => 'Hematología y Coagulación',
            ],

            //Hormonales

            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => '17-OH Progesterona',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Androstenediona',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cortisol: am pm',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Sulfato DHEA',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Estradiol',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'FSH',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Gonadotropina Coriónica Cuantitativa',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Gonadotropina Coriónica Cualitativa',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Hormona de Crecimiento Basal',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Insulina Basal',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Insulina post prandial',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Insulina post carga',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Péptico C',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'LH',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Paratohormona',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Prolactina Pool',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Progesterona',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Testosterona total',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Testosterona libre',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Perfil androgénico (Testosterona total, SHBG, albúmina, testosterona libre calculada)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Anticuerpos anti-tiroglobulina (TGB)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Anticuerpos anti-microsomal (TPO)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'T3 libre',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'T4 libre',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'TSH ultrasensible',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Tiroglobulina',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IGF-1 (factor insulinoide)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Screening pre natal l trimestre (anexar al eco el formato Screening)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Screening pre natal ll trimestre (anexar al eco el formato Screening)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Alfafetoproteína (Hígado)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'CEA (vejiga/mama/pulmón/otros)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Ca 125 (mama/ovario)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Ca 15-3 (hígado/pulmón/mama)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Ca 19-9 (páncreas/gastrointestinal)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'PSA Libre',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'PSA Total',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'HGC cuant. (gónada/pulmón/páncreas)',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'He4',
                'category' => 'Hormonales',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cyfra 21-1',
                'category' => 'Hormonales',
            ],

            //Orina

            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Nitrógeno Ureico en Orina de 24 horas',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Osmolaridad en Orina',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Ácido Úrico 24h. parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Albuminómetria 24 h parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Amilasa 24h. Parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Calcio 24h o parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Creatinina 24 h parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Depuracion de creatinina (orina 24 h)',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Fósforo 24h. Parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Microalbuminuria 24 h. Parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Orina completa',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Microalbuminuria 24 h. Parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Potasio 24h Parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Proteina de Bence Jones',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Sodio 24 h Parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Magnesio 24 h Parcial',
                'category' => 'Orina',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Urea 24h',
                'category' => 'Orina',
            ],

            //Heces

            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Adenovirus (antígeno)',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Rotavirus (antígeno)',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Coloración de Cryptosporidium',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Triple Test parasitario: Cryptosporidium sp (antígeno) Entamoeba histolytica (antígeno) Giardia lamblia (antígeno)',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Examen de heces / Concentrado',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Grasa neutras (Sudán)',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Helicobacter pylori (antígeno)',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Recuento de polimorfonucleares',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Sangre oculta / Transferrina',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Azúcares reductores',
                'category' => 'Heces',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Test de sacarosa',
                'category' => 'Heces',
            ],

            //Microbiológico

            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Baciloscopia',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Antígeno Streptococcus pyogenes',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Coprocultivo',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cultivo de ambiente',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cultivo de hongos',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cultivo de Koch (esputo/Orina)',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cultivo de Mycoplasma (M. hominis, ureaplasma)',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cultivo de secreción faringea',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cultivo de secreción vaginal',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cultivo de secreción uretreal',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Directo de hongos',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Espermocultivo',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Estreptococcus grupo A',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Hemocultivo',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Serología de hongos',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Serología para Leptospira (lgM)-(lgG)',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Tinta china para LCR',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Tinción de Gram',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Tinción de Ziehl Neelsen',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Urocultivo',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Serología para Salmonella',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Serología para Escherichia coli',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Brucela Rosa de Bengala',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Análisis Microbiológico del Agua',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Análisis Parasitológico del Agua',
                'category' => 'Microbiológico',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Cultivo de Leche',
                'category' => 'Microbiológico',
            ],

            //Inmunológicos

            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM anti-C. pneumoniae',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM anti-M. pneumoniae',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Antiestreptolisina (ASTO)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Anticuerpos anti – Trypanosoma cruzi (Chagas)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Chlamydia trachomatis (Antígeno)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM C. trachomatis',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM anti Citomegalovirus',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Crioglobulinas',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Complemento 3 (C3)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Complemento 4 (C4)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Complemento total (Ch50)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Dengue IgG/lgM',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM anti Virus Epstein Barr',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM anti-Helicobacter pylori',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM anti Virus Hepatitis A',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Antígeno de superficie Hepatitis B',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Antígeno E. Virus Hepatitis B',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Anti-antígeno E. Virus Hepatitis B',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM Anti-Core Virus Hepatitis B',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG Anti-Core Virus Hepatitis B',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Anti-Core Total Virus Hepatitis B',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Anticuerpos anti-virus Hepatitis C',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM Herpes simples l',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM Herpes simples ll',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'H.I.V. Antígeno p24/(HIV-1/HIV-2)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'H.I.V. ELISA 4ta. Generación',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'H.I.V. Prueba Rápida',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Inmunoglobulina E (IgE total)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Inmunoglobulina A (IgA total)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Inmunoglobulina G (IgG total)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Inmunoglobulina M (IgM total)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'L.E. Test',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Monotest',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Proteína C. Reactiva (PCR)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Factor Reumatoideo cuantificado',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Reacción de Widai (Antig. Febriles)',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM anti-Virus de la Rubéola',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgG IgM anti-Toxoplasma gondii',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'V.D.R.L',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Virus del sarampión',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Virus Sincitial Respiratorio',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Antígeno Virus Sincitial Respiratorio',
                'category' => 'Inmunológicos',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Influenza A y B hisopado nasofaringeo',
                'category' => 'Inmunológicos',
            ],

            //Química Clínica

            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Ácido Úrico sérico',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Amilasa sérica',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Apolipoproteínas A1 B',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Bilirrubina total y fraccionada',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Calcio sérico',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Creatinina sérica',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'CK TOTAL',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'CK – MB',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Colesterol total',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Colesterol Fraccionado',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Colinesterasa',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Deshidrogenasa láctica (LDH)',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Fosfatasa ácida total prostática',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Fosfatasa alcalina',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Fósforo sérico',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Gamma Glutamil Transferasa (GGT)',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Glicemia basal',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Glicmia pre y post carga',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Glicemia Pre y Post Prandial',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Hierro sérico',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Lipasa',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Magnesio',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Nitrógeno uréico (BUN)',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Electrolitos (Sodio, Potasio, Cloro)',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Proteínas totales y fraccionadas',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Prueba de Tolerancia a la Glucosa de ____ Horas',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Transferrina ___ % saturación',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Transaminasa oxalacética (TGO-AST)',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Transaminasa Pirúvica (TGP-ALT)',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Triglicéridos',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Úrea',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Acido fólico sérico',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Acido fólico intraeritrócitario',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Vitamina B12',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Troponina l',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Troponina T',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Gases Venosos',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Gases Arteriales',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Análisis de cálculo renal',
                'category' => 'Química Clínica',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Amonio',
                'category' => 'Química Clínica',
            ],

            //Inmunofluorescencia

            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Anticuerpos anti-ADN de doble hebra (Crithidia luciliae)',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'Anticuerpos Antinucleares (ANA - HEP-2)',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'FTA-ABS (Anti Treponema pallidum)',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Legionella pneumophila',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Mycoplasma pneumoniae',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Coxiella burnetii',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Chlamydophila pneumoniae',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Adenovirus',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Virus Sincitial respiratorio',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Influenza A',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Influenza B',
                'category' => 'Inmunofluorescencia',
            ],
            [
                'code' => 'TDEC-EX-' . random_int(11111111, 99999999),
                'description' => 'IgM anti-Parainfluenza serotipos 1,2 y 3',
                'category' => 'Inmunofluorescencia',
            ],

        ];

        TelemedicineExamenList::insert($data);
    }
}