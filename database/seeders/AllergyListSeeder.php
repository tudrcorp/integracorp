<?php

namespace Database\Seeders;

use App\Models\AllergyList;
use Illuminate\Database\Seeder;
use App\Models\TelemedicineAllergyList;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AllergyListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [

            // 🍽️ Alergias Alimentarias
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia al maní (cacahuate)',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a la leche de vaca',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia al huevo',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a frutos secos (nueces, almendras, avellanas)',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a mariscos (camarones, langostinos, cangrejos)',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a pescado (salmón, atún, bacalao)',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia al trigo',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a la soja',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia al apio',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a la mostaza',
                'category' => 'Alimentaria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a sulfitos (conservantes)',
                'category' => 'Alimentaria',
            ],

            // 🌬️ Alergias Respiratorias
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia al polen (fiebre del heno)',
                'category' => 'Respiratoria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a ácaros del polvo doméstico',
                'category' => 'Respiratoria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a mohos (Alternaria, Cladosporium)',
                'category' => 'Respiratoria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia al pelo y caspa de animales (gato, perro)',
                'category' => 'Respiratoria',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a cucarachas',
                'category' => 'Respiratoria',
            ],

            // 🐝 Alergias por Picaduras de Insectos
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a picadura de abeja',
                'category' => 'Por picadura',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a picadura de avispa',
                'category' => 'Por picadura',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a picadura de hormiga de fuego',
                'category' => 'Por picadura',
            ],

            // 💊 Alergias Medicamentosas
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a penicilina y antibióticos betalactámicos',
                'category' => 'Medicamentosa',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a AINEs (ibuprofeno, aspirina)',
                'category' => 'Medicamentosa',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a quimioterápicos',
                'category' => 'Medicamentosa',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a insulina',
                'category' => 'Medicamentosa',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Reacción al contraste yodado (TAC)',
                'category' => 'Medicamentosa',
            ],

            // 🧴 Alergias Cutáneas (de contacto)
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia al látex',
                'category' => 'De contacto',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia al níquel (joyas, botones)',
                'category' => 'De contacto',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a fragancias y perfumes',
                'category' => 'De contacto',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a parabenos y conservantes',
                'category' => 'De contacto',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Alergia a hiedra venenosa y plantas urticantes',
                'category' => 'De contacto',
            ],

            // 🌞 Otras alergias
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Urticaria por frío (alergia al frío)',
                'category' => 'Otras',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Dermatitis polimorfa lumínica (alergia al sol)',
                'category' => 'Otras',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Anafilaxia inducida por ejercicio',
                'category' => 'Otras',
            ],
            [
                'code' => 'TDEC-AL-' . random_int(11111111, 99999999),
                'description' => 'Sensibilidad al semen humano',
                'category' => 'Otras',
            ],

        ];

        AllergyList::insert($data);
    }
}