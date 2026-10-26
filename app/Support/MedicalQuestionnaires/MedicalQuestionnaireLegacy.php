<?php

declare(strict_types=1);

namespace App\Support\MedicalQuestionnaires;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paso del cuestionario fijo (16 columnas `cuestion_N` en `affiliations`) al cuestionario dinámico.
 *
 * Las 16 preguntas son las de los formularios internos de afiliación individual: es la numeración
 * con la que se respondieron todas las afiliaciones del Plan Especial que existen hoy (ninguna vino
 * de la pre-afiliación pública, que usaba otra numeración de 14). Las columnas viejas no se borran:
 * {@see MedicalQuestionnaireLegacyObserver} mantiene el cuestionario nuevo al día si alguien todavía
 * las escribe (importador de afiliaciones).
 */
final class MedicalQuestionnaireLegacy
{
    public const SPECIAL_PLAN_ID = 3;

    public const QUESTIONNAIRE_NAME = 'Declaración de condiciones médicas · Plan Especial';

    /**
     * @var array<string, string>
     */
    public const QUESTIONS = [
        'cuestion_1' => '¿Usted y el grupo de beneficiarios solicitantes, gozan de buena salud?',
        'cuestion_2' => '¿Usted o el grupo de beneficiarios presentan alguna condición médica o congénita?',
        'cuestion_3' => '¿Usted o el grupo de beneficiario ha sido intervenido quirúrgicamente?',
        'cuestion_4' => '¿Usted o el grupo de beneficiario padece o ha padecido alguna enfermedad?',
        'cuestion_5' => 'Enfermedades Cardiovasculares, tales como; Hipertensión Arterial, Ataque cardíaco, Angina o dolor de pecho, Soplo Cardíaco, Insuficiencia Cardíaca Congestiva o desórdenes del corazón o sistema circulatorio.',
        'cuestion_6' => 'Enfermedades Cerebrovasculares, tales como: Desmayos, confusión, parálisis de miembros, dificultad para hablar, articular y entender, Accidente Cerebro-vascular (ACV). Cefalea o migraña. Epilepsia o Convulsiones. Otros trastornos o enfermedad del Cerebro o Sistema Nervioso.',
        'cuestion_7' => 'Enfermedades Respiratorias, tales como: Asma Bronquial, Bronquitis, Bronquiolitis, Enfisema, Neumonía, Enfermedad pulmonar Obstructiva Crónica (EPOC) u otras enfermedades del Sistema Respiratorio.',
        'cuestion_8' => 'Enfermedades o Trastornos Endocrinos tales como: Diabetes Mellitus, Bocio, hipertiroidismo, hipotiroidismo, Tiroiditis, Resistencia a la insulina, enfermedad de Cushing, cáncer de tiroides.',
        'cuestion_9' => 'Enfermedades Gastrointestinales como: Litiasis vesicular, Cólico Biliar, Úlcera gástrica, gastritis, Hemorragia digestivas, colitis, hemorroides, Apendicitis, Peritonitis, Pancreatitis u otros desórdenes del estómago, intestino, hígado o vesícula biliar.',
        'cuestion_10' => 'Enfermedades Renales: Litiasis renal, Cólico nefrítico, Sangre en la orina o Hematuria, Cistitis, Infecciones urinarias, Pielonefritis, Insuficiencia renal aguda. Otras enfermedades del riñón, vejiga o próstata.',
        'cuestion_11' => 'Enfermedades Osteoarticulares, Artrosis, Artritis reumatoide, Traumatismo craneoencefálico, Fracturas óseas, Luxaciones o esguinces, tumores óseos, u otros trastornos de los músculos, articulaciones o columna vertical o espalda.',
        'cuestion_12' => '¿Ha sufrido o padece de alguna enfermedad de la Piel como: Dermatitis, Celulitis, Abscesos cutáneos, quistes, tumores o cáncer? ,Quemaduras o Heridas Complicadas.',
        'cuestion_13' => '¿Padece de alguna enfermedad o desorden de los ojos, oídos, nariz o garganta?',
        'cuestion_14' => '¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?',
        'cuestion_15' => '¿Usted o alguno de los solicitantes, toma algún tipo de medicamentos por tratamiento prolongado?',
        'cuestion_16' => '¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?',
    ];

    /**
     * Crea el cuestionario del Plan Especial con las 16 preguntas y pasa las respuestas que ya
     * existen. Idempotente: si ya existe, no duplica nada.
     *
     * @return array{questionnaire_id: ?int, questions: int, answers: int}
     */
    public static function seed(): array
    {
        if (! Schema::hasTable('medical_questionnaires') || ! DB::table('plans')->where('id', self::SPECIAL_PLAN_ID)->exists()) {
            return ['questionnaire_id' => null, 'questions' => 0, 'answers' => 0];
        }

        $now = now();
        $questionnaireId = DB::table('medical_questionnaires')->where('plan_id', self::SPECIAL_PLAN_ID)->value('id');

        if ($questionnaireId === null) {
            $questionnaireId = DB::table('medical_questionnaires')->insertGetId([
                'plan_id' => self::SPECIAL_PLAN_ID,
                'name' => self::QUESTIONNAIRE_NAME,
                'is_active' => true,
                'created_by' => 'SISTEMA',
                'updated_by' => 'SISTEMA',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $questionIds = [];
        $order = 1;

        foreach (self::QUESTIONS as $column => $text) {
            $id = DB::table('medical_questionnaire_questions')
                ->where('medical_questionnaire_id', $questionnaireId)
                ->where('legacy_column', $column)
                ->value('id');

            if ($id === null) {
                $id = DB::table('medical_questionnaire_questions')->insertGetId([
                    'medical_questionnaire_id' => $questionnaireId,
                    'sort_order' => $order,
                    'text' => $text,
                    'is_active' => true,
                    'legacy_column' => $column,
                    'created_by' => 'SISTEMA',
                    'updated_by' => 'SISTEMA',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $questionIds[$column] = ['id' => (int) $id, 'order' => $order, 'text' => $text];
            $order++;
        }

        $answers = 0;

        DB::table('affiliations')
            ->where('plan_id', self::SPECIAL_PLAN_ID)
            ->select(['id', ...array_keys(self::QUESTIONS), 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($affiliations) use ($questionIds, $now, &$answers): void {
                $rows = [];

                foreach ($affiliations as $affiliation) {
                    foreach ($questionIds as $column => $question) {
                        if ($affiliation->{$column} === null) {
                            continue;
                        }

                        $rows[] = [
                            'answerable_type' => MedicalQuestionnaireAnswers::TYPE_INDIVIDUAL,
                            'answerable_id' => (int) $affiliation->id,
                            'medical_questionnaire_question_id' => $question['id'],
                            'question_text' => $question['text'],
                            'sort_order' => $question['order'],
                            'answer' => (bool) $affiliation->{$column},
                            'answered_by' => 'MIGRACION',
                            'created_at' => $affiliation->created_at ?? $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    $answers += DB::table('medical_questionnaire_answers')->insertOrIgnore($rows);
                }
            });

        return ['questionnaire_id' => (int) $questionnaireId, 'questions' => count($questionIds), 'answers' => $answers];
    }
}
