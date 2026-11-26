<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\ClinicalRecord;

use App\Models\MedicalQuestionnaireAnswer;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicalAppointment;
use App\Models\TelemedicineAmdPhysicalExam;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineDocument;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicineMedicalReport;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Models\TelemedicineServiceList;
use App\Support\ClinicalEntitlements\AffiliateClinicalEntitlementResolver;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireAnswers;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ficha clínica del paciente (estructura IPS) armada en el momento con lo que Telemedicina y
 * Operaciones ya registran. No guarda copia: cada apertura lee el estado actual.
 *
 * Solo lee. Cada fuente va acotada por paciente y con límite de filas; una fuente que falla no
 * tumba la ficha: su sección queda «Sin registro».
 */
final class PatientClinicalRecord
{
    /**
     * Antecedentes de la historia: columna => etiqueta del formulario de Telemedicina.
     * Sin sufijo son familiares; con «_app», personales.
     *
     * @var array<string, string>
     */
    public const CONDITIONS = [
        'tension_alta' => 'Hipertensión arterial',
        'diabetes' => 'Diabetes mellitus',
        'asma' => 'Asma bronquial',
        'cardiacos' => 'Enfermedades cardíacas',
        'gastritis_ulceras' => 'Gastropatías',
        'enfermedad_autoimmune' => 'Enfermedad autoinmune',
        'trombosis_embooleanas' => 'Insuficiencia venosa',
        'fracturas' => 'Traumatismos',
        'cancer' => 'Cáncer',
        'tranfusiones_sanguineas' => 'Anemia',
        'tiroides' => 'Tiroides',
        'hepatitis' => 'Hepatitis',
        'moretones_frecuentes' => 'Enfermedades hematológicas',
        'psiquiatricas' => 'Enfermedades psiquiátricas',
    ];

    /**
     * Textos que algunos registros usan para no dejar el campo vacío: no son datos clínicos.
     *
     * @var list<string>
     */
    private const FILLER = ['X', 'XX', 'XXX', 'XXXX', '-', '--', '.', '..', '...', '…', 'N/A', 'NA', 'NINGUNO', 'NINGUNA', 'NO', 'S/N', 'SN', '0'];

    private const OPEN_ORDER = ['PENDIENTE', 'EN GESTION', 'EN GESTIÓN'];

    /**
     * @var array<int, string>
     */
    private array $doctors = [];

    private function __construct(private TelemedicinePatient $patient, private CarbonImmutable $now) {}

    /**
     * @return array<string, mixed>
     */
    public static function build(TelemedicinePatient $patient, ?CarbonInterface $now = null): array
    {
        return (new self($patient, CarbonImmutable::instance($now ?? now())))->assemble();
    }

    /**
     * @return array<string, mixed>
     */
    private function assemble(): array
    {
        $this->patient->loadMissing(['plan:id,description', 'coverage:id,price', 'city:id,definition', 'state:id,definition', 'afilliation', 'afilliationCorporate']);

        $history = $this->safe(fn () => TelemedicineHistoryPatient::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->first(), null);
        $historyCount = $this->safe(fn () => TelemedicineHistoryPatient::query()->where('telemedicine_patient_id', $this->patient->id)->count(), 0);
        $consultations = $this->safe(fn () => TelemedicineConsultationPatient::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(80)->get(), new Collection);
        $medications = $this->safe(fn () => TelemedicinePatientMedications::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(80)->get(), new Collection);
        $labs = $this->safe(fn () => TelemedicinePatientLab::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(60)->get(), new Collection);
        $studies = $this->safe(fn () => TelemedicinePatientStudy::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(60)->get(), new Collection);
        $specialties = $this->safe(fn () => TelemedicinePatientSpecialty::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(60)->get(), new Collection);
        $reports = $this->safe(fn () => TelemedicineMedicalReport::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(60)->get(), new Collection);
        $exams = $this->safe(fn () => TelemedicineAmdPhysicalExam::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(40)->get(), new Collection);
        $cases = $this->safe(fn () => TelemedicineCase::query()->where('telemedicine_patient_id', $this->patient->id)->whereNull('logically_deleted_at')->latest('id')->limit(40)->get(), new Collection);
        $coordinations = $this->safe(fn () => OperationCoordinationService::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(60)->get(), new Collection);
        $appointments = $this->safe(fn () => OperationMedicalAppointment::query()->where('telemedicine_patient_id', $this->patient->id)->latest('appointment_at')->limit(40)->get(), new Collection);
        $documents = $this->safe(fn () => TelemedicineDocument::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(60)->get(), new Collection);

        $this->doctors = $this->doctorNames([
            ...$consultations->pluck('telemedicine_doctor_id'), ...$medications->pluck('telemedicine_doctor_id'),
            ...$exams->pluck('telemedicine_doctor_id'), ...$cases->pluck('telemedicine_doctor_id'), ...$reports->pluck('telemedicine_doctor_id'),
        ]);

        $allergies = $this->allergies($history);
        $medicationRows = $this->medications($medications);
        $personal = $this->flagged($history, '_app');

        return [
            'generated_at' => $this->now->format('d/m/Y h:i a'),
            'patient' => $this->identity(),
            'coverage' => $this->coverage(),
            'quotas' => $this->quotas(),
            'alerts' => [
                'allergies' => array_column($allergies, 'agent'),
                'conditions' => $personal,
                'medications' => array_values(array_map(fn (array $row): string => $row['name'], array_filter($medicationRows, fn (array $row): bool => $row['state'] === 'En curso'))),
            ],
            'problems' => $this->problems($consultations),
            'medications' => $medicationRows,
            'allergies' => $allergies,
            'background' => $this->background($history, $historyCount, $personal),
            'vitals' => $this->vitals($consultations, $reports, $exams),
            'orders' => $this->orders($labs, $studies, $specialties),
            'timeline' => $this->timeline($cases, $consultations, $exams, $coordinations, $appointments),
            'last_consultation' => $this->lastConsultation($consultations, $medications, $labs, $studies, $specialties),
            'pending' => $this->pending($labs, $studies, $specialties, $appointments),
            'documents' => $this->documents($documents),
            'courtesies' => $this->courtesies(),
            'counts' => [
                'consultations' => $consultations->count(),
                'cases' => $cases->count(),
                'coordinations' => $coordinations->count(),
            ],
        ];
    }

    /**
     * @return array<string, ?string>
     */
    private function identity(): array
    {
        $p = $this->patient;
        $birth = self::parseDate($p->birth_date);
        $place = implode(', ', array_filter([$this->text($p->address), $p->city?->definition, $p->state?->definition]));

        return [
            'name' => mb_convert_case(mb_strtolower(trim((string) $p->full_name)), MB_CASE_TITLE),
            'document' => filled($p->nro_identificacion) ? 'V-'.number_format((int) preg_replace('/\D/', '', (string) $p->nro_identificacion), 0, ',', '.') : null,
            'birth' => $birth?->format('d/m/Y') ?? $this->text($p->birth_date),
            'age' => $birth !== null ? (int) $birth->diffInYears($this->now).' años' : (filled($p->age) ? $p->age.' años' : null),
            'sex' => filled($p->sex) ? mb_convert_case(mb_strtolower((string) $p->sex), MB_CASE_TITLE) : null,
            'phone' => $this->text($p->phone),
            'email' => $this->text($p->email),
            'address' => $place !== '' ? $place : null,
            'contact' => implode(' · ', array_filter([$this->text($p->phone_contact), $this->text($p->email_contact)])) ?: null,
            'code' => $this->text($p->code),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    private function coverage(): array
    {
        $p = $this->patient;
        $corporate = $p->afilliation_corporate_id !== null;
        $effective = self::parseDate($corporate ? $p->afilliationCorporate?->effective_date : $p->afilliation?->effective_date);

        return [
            'plan' => $this->text($p->plan?->description),
            'coverage' => $p->coverage?->price !== null ? number_format((float) $p->coverage->price, 0, ',', '.').' US$' : null,
            'type' => $corporate ? 'Corporativa' : ($p->afilliation_id !== null ? 'Individual' : null),
            'company' => $corporate ? $this->text($p->name_corporate) : null,
            'code' => $this->text($p->code_affiliation),
            'status' => $this->text($p->status_affiliation),
            'period' => $effective !== null ? $effective->format('d/m/Y').' – '.$effective->addYear()->subDay()->format('d/m/Y') : null,
        ];
    }

    /**
     * @return list<array{label: string, used: int, quota: ?int, remaining: ?int, percent: int}>
     */
    private function quotas(): array
    {
        return $this->safe(function (): array {
            $rows = [];
            $order = $this->planBenefitOrder();

            foreach (AffiliateClinicalEntitlementResolver::forPatient($this->patient)->entitlements as $entitlement) {
                $quota = $entitlement->quota;
                $rows[] = [
                    'sort' => $order[$entitlement->benefitId] ?? PHP_INT_MAX,
                    'label' => $entitlement->displayName(),
                    'used' => $entitlement->used,
                    'quota' => $quota,
                    'remaining' => $entitlement->remaining,
                    'percent' => $quota !== null && $quota > 0 ? (int) min(100, round($entitlement->used * 100 / $quota)) : 0,
                ];
            }

            /** Orden estable: el del plan; lo que no esté en el plan va al final en su orden original. */
            $position = array_flip(array_keys($rows));
            uksort($rows, fn (int $a, int $b): int => [$rows[$a]['sort'], $position[$a]] <=> [$rows[$b]['sort'], $position[$b]]);

            return array_values(array_map(function (array $row): array {
                unset($row['sort']);

                return $row;
            }, $rows));
        }, []);
    }

    /**
     * Posición de cada beneficio en el plan del paciente (orden en que se cargaron en el plan,
     * el mismo que muestra la tarjeta del plan en Operaciones).
     *
     * @return array<int, int> benefit_id => posición
     */
    private function planBenefitOrder(): array
    {
        if ($this->patient->plan_id === null) {
            return [];
        }

        return $this->safe(fn () => array_flip(array_map('intval', \Illuminate\Support\Facades\DB::table('benefit_plans')
            ->where('plan_id', $this->patient->plan_id)
            ->orderBy('id')
            ->pluck('benefit_id')
            ->unique()
            ->values()
            ->all())), []);
    }

    /**
     * Diagnósticos escritos por el médico en cada consulta, el más reciente primero, sin repetir.
     *
     * @param  Collection<int, TelemedicineConsultationPatient>  $consultations
     * @return list<array{text: string, date: string, doctor: ?string, status: ?string}>
     */
    private function problems(Collection $consultations): array
    {
        $seen = [];
        $rows = [];

        foreach ($consultations as $consultation) {
            $text = $this->text($consultation->diagnostic_impression);

            if ($text === null) {
                continue;
            }

            $key = mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', $text) ?? $text);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = [
                'text' => self::clip($text, 220),
                'date' => $consultation->created_at?->format('d/m/Y') ?? '',
                'doctor' => $this->doctors[(int) $consultation->telemedicine_doctor_id] ?? null,
                'status' => $this->text($consultation->status),
            ];

            if (count($rows) >= 12) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, TelemedicinePatientMedications>  $medications
     * @return list<array{name: string, indications: ?string, since: string, until: ?string, state: string, dispatch: ?string, doctor: ?string, covered: ?bool}>
     */
    private function medications(Collection $medications): array
    {
        $rows = [];

        foreach ($medications->take(30) as $medication) {
            $name = $this->text($medication->medicine);

            if ($name === null) {
                continue;
            }

            $start = $medication->created_at !== null ? CarbonImmutable::instance($medication->created_at) : null;
            $days = (int) $medication->duration > 0 ? (int) $medication->duration : self::daysFromText((string) $medication->indications);
            $until = $start !== null && $days > 0 ? $start->addDays($days) : null;
            $status = strtoupper((string) $medication->status);
            $state = match (true) {
                $status === 'CANCELADA' => 'Cancelado',
                $until === null => 'Indicado',
                $until->greaterThanOrEqualTo($this->now->startOfDay()) => 'En curso',
                default => 'Terminado',
            };

            $rows[] = [
                'name' => self::clip($name, 90),
                'indications' => self::clip((string) $this->text($medication->indications), 180) ?: null,
                'since' => $start?->format('d/m/Y') ?? '',
                'until' => $until?->format('d/m/Y'),
                'state' => $state,
                'dispatch' => $this->text($medication->status),
                'doctor' => $this->doctors[(int) $medication->telemedicine_doctor_id] ?? $this->text($medication->assigned_by),
                'covered' => $medication->is_covered === null ? null : (bool) $medication->is_covered,
            ];
        }

        usort($rows, fn (array $a, array $b): int => (int) ($a['state'] !== 'En curso') <=> (int) ($b['state'] !== 'En curso'));

        return $rows;
    }

    /**
     * @return list<array{agent: string, source: string}>
     */
    private function allergies(?TelemedicineHistoryPatient $history): array
    {
        if ($history === null) {
            return [];
        }

        $rows = [];
        $listed = is_array($history->allergies) ? $history->allergies : [];

        foreach ($listed as $item) {
            $agent = $this->text(is_string($item) ? $item : null);

            if ($agent !== null) {
                $rows[] = ['agent' => $agent, 'source' => 'Historia clínica'];
            }
        }

        $other = $this->text($history->observations_allergies);

        if ($other !== null) {
            $rows[] = ['agent' => self::clip($other, 160), 'source' => 'Historia clínica (otras)'];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function flagged(?TelemedicineHistoryPatient $history, string $suffix): array
    {
        if ($history === null) {
            return [];
        }

        $labels = [];

        foreach (self::CONDITIONS as $column => $label) {
            $field = $column.$suffix;

            if ((bool) ($history->getAttribute($field) ?? false)) {
                $detail = $this->text($history->getAttribute('input_'.$field));
                $labels[] = $detail !== null ? $label.' ('.self::clip($detail, 60).')' : $label;
            }
        }

        if ($suffix === '_app' && (bool) $history->vih_app) {
            $labels[] = 'VIH/SIDA';
        }

        return $labels;
    }

    /**
     * @param  list<string>  $personal
     * @return array<string, mixed>
     */
    private function background(?TelemedicineHistoryPatient $history, int $historyCount, array $personal): array
    {
        $tables = fn (string $model): array => $this->safe(fn () => $model::query()->where('telemedicine_patient_id', $this->patient->id)->latest('id')->limit(10)->pluck('observations')
            ->map(fn (mixed $value): ?string => $this->text(is_string($value) ? $value : null))->filter()->map(fn (string $value): string => self::clip($value, 200))->values()->all(), []);

        $habits = [];

        if ($history !== null) {
            foreach (['tabaco' => 'Tabaquismo', 'alcohol' => 'Alcohol', 'drogas' => 'Drogas'] as $column => $label) {
                if ((bool) $history->getAttribute($column)) {
                    $habits[] = $label;
                }
            }
        }

        $gyn = [];

        if ($history !== null) {
            foreach ([
                'edad_primera_menstruation' => 'Menarquia',
                'fecha_ultima_regla' => 'Última regla',
                'numero_embarazos' => 'Embarazos',
                'numero_partos' => 'Partos',
                'cesareas' => 'Cesáreas',
                'numero_abortos' => 'Abortos',
            ] as $column => $label) {
                $value = $this->text((string) ($history->getAttribute($column) ?? ''));

                if ($value !== null) {
                    $gyn[] = $label.': '.$value;
                }
            }
        }

        return [
            'history_date' => $history !== null ? (self::parseDate($history->history_date)?->format('d/m/Y') ?? $history->created_at?->format('d/m/Y')) : null,
            'history_code' => $history !== null ? $this->text($history->code) : null,
            'history_count' => $historyCount,
            'personal' => $personal,
            'personal_notes' => array_values(array_filter([$this->clipText($history?->observations_personal), $this->clipText($history?->observations_pathological), ...$tables(\App\Models\PathologicalHistory::class)])),
            'family' => $this->flagged($history, ''),
            'family_notes' => $tables(\App\Models\FamilyHistory::class),
            'surgical' => array_values(array_filter([$this->clipText($history?->history_surgical), ...$tables(\App\Models\SurgicalHistory::class)])),
            'habits' => $habits,
            'habits_notes' => array_values(array_filter([$this->clipText($history?->observations_not_pathological), ...$tables(\App\Models\NoPathologicalHistory::class)])),
            'gynecological' => $gyn,
            'gynecological_notes' => array_values(array_filter([$this->clipText($history?->observations_ginecologica), ...$tables(\App\Models\GynecologicalHistory::class)])),
            'usual_medication' => array_values(array_filter([$this->clipText($history?->medications_supplements), $this->clipText($history?->observations_medication)])),
            'vaccines' => $history !== null && (bool) $history->vacunas_recientes ? ['Vacunas recientes (sin detalle)'] : [],
            'declared' => $this->declared(),
        ];
    }

    /**
     * Lo que el titular respondió «sí» en el cuestionario médico al afiliarse (afiliación individual).
     *
     * @return array{answered: bool, items: list<string>}
     */
    private function declared(): array
    {
        if ($this->patient->afilliation_id === null) {
            return ['answered' => false, 'items' => []];
        }

        return $this->safe(function (): array {
            $answers = MedicalQuestionnaireAnswer::query()
                ->where('answerable_type', MedicalQuestionnaireAnswers::TYPE_INDIVIDUAL)
                ->where('answerable_id', $this->patient->afilliation_id)
                ->orderBy('sort_order')
                ->get(['question_text', 'answer']);

            return [
                'answered' => $answers->isNotEmpty(),
                'items' => $answers->filter(fn (MedicalQuestionnaireAnswer $answer): bool => (bool) $answer->answer && ! str_contains(mb_strtolower((string) $answer->question_text), 'buena salud'))
                    ->map(fn (MedicalQuestionnaireAnswer $answer): string => trim((string) $answer->question_text))
                    ->values()->all(),
            ];
        }, ['answered' => false, 'items' => []]);
    }

    /**
     * Signos vitales de consultas, informes médicos y exámenes de AMD, del más reciente al más antiguo.
     *
     * @return list<array<string, ?string>>
     */
    private function vitals(Collection $consultations, Collection $reports, Collection $exams): array
    {
        $rows = [];
        $covered = [];

        foreach ($consultations as $consultation) {
            $row = $this->vitalRow($consultation->created_at, 'Consulta', $consultation->pa, $consultation->fc, $consultation->fr, $consultation->temp, $consultation->saturacion, $consultation->peso, $consultation->estatura, $consultation->imc);

            if ($row !== null) {
                $rows[] = $row;
                $covered[(int) $consultation->id] = true;
            }
        }

        foreach ($reports as $report) {
            if (isset($covered[(int) $report->telemedicine_consultation_patient_id])) {
                continue;
            }

            $row = $this->vitalRow($report->created_at, 'Informe médico', $report->pa, $report->fc, $report->fr, $report->temp, $report->saturacion, $report->peso, $report->estatura, $report->imc);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        foreach ($exams as $exam) {
            $row = $this->vitalRow($exam->created_at, 'AMD a domicilio', $exam->blood_pressure, $exam->heart_rate, $exam->respiratory_rate, null, $exam->oxygen_saturation, null, null, null);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['sort'] <=> $a['sort']);

        return array_map(function (array $row): array {
            unset($row['sort']);

            return $row;
        }, array_slice($rows, 0, 12));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vitalRow(?CarbonInterface $at, string $source, mixed $pa, mixed $fc, mixed $fr, mixed $temp, mixed $sat, mixed $weight, mixed $height, mixed $bmi): ?array
    {
        $values = [
            'pa' => self::bloodPressure($pa),
            'fc' => self::measure($fc),
            'fr' => self::measure($fr),
            'temp' => self::measure($temp, ' °C'),
            'sat' => self::measure($sat, ' %'),
            'weight' => self::measure($weight, ' kg'),
            'height' => self::measure($height),
            'bmi' => self::measure($bmi),
        ];

        if (array_filter($values) === []) {
            return null;
        }

        return ['date' => $at?->format('d/m/Y') ?? '', 'source' => $source, 'sort' => $at?->getTimestamp() ?? 0, ...$values];
    }

    /**
     * @return list<array{date: string, kind: string, name: string, status: ?string, open: bool}>
     */
    private function orders(Collection $labs, Collection $studies, Collection $specialties): array
    {
        $rows = [];

        foreach ([['Laboratorio', $labs, 'laboratory'], ['Estudio / imagen', $studies, 'study'], ['Especialista', $specialties, 'specialty']] as [$kind, $items, $column]) {
            foreach ($items as $item) {
                $name = $this->text($item->getAttribute($column));

                if ($name === null) {
                    continue;
                }

                $status = $this->text($item->status);
                $rows[] = [
                    'date' => $item->created_at?->format('d/m/Y') ?? '',
                    'sort' => $item->created_at?->getTimestamp() ?? 0,
                    'kind' => $kind,
                    'name' => self::clip($name, 120),
                    'status' => $status,
                    'open' => in_array(strtoupper((string) $status), self::OPEN_ORDER, true),
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['sort'] <=> $a['sort']);

        return array_map(function (array $row): array {
            unset($row['sort']);

            return $row;
        }, array_slice($rows, 0, 40));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timeline(Collection $cases, Collection $consultations, Collection $exams, Collection $coordinations, Collection $appointments): array
    {
        $services = $this->safe(fn () => TelemedicineServiceList::query()->whereIn('id', $consultations->pluck('telemedicine_service_list_id')->filter()->unique()->all())->pluck('name', 'id')->all(), []);
        $events = [];

        foreach ($cases as $case) {
            $events[] = $this->event($case->created_at, 'Caso', 'Caso '.$case->code.' abierto', $this->text($case->reason), $this->doctors[(int) $case->telemedicine_doctor_id] ?? null, $this->text($case->status), (string) $case->code, 'case');
        }

        foreach ($consultations as $consultation) {
            $detail = $this->text($consultation->diagnostic_impression) ?? $this->text($consultation->reason_consultation);
            $service = $services[(int) $consultation->telemedicine_service_list_id] ?? null;
            $events[] = $this->event($consultation->created_at, 'Telemedicina', ($this->text($consultation->status) ?? 'Consulta').($service ? ' · '.$service : ''), $detail, $this->doctors[(int) $consultation->telemedicine_doctor_id] ?? null, null, (string) ($consultation->telemedicine_case_code ?? ''), 'consultation', (int) $consultation->id);
        }

        foreach ($exams as $exam) {
            $findings = $this->text(implode(' · ', array_filter([$exam->abdomen ? 'Abdomen: '.$exam->abdomen : null, $exam->thorax ? 'Tórax: '.$exam->thorax : null])));
            $events[] = $this->event($exam->created_at, 'AMD', 'Examen físico a domicilio', $findings, $this->doctors[(int) $exam->telemedicine_doctor_id] ?? null, null, '', 'amd');
        }

        foreach ($coordinations as $coordination) {
            $at = self::parseDate($coordination->date_service) ?? self::parseDate($coordination->date_solicitud) ?? ($coordination->created_at !== null ? CarbonImmutable::instance($coordination->created_at) : null);
            $title = implode(' · ', array_filter([$this->text($coordination->servicie), $this->text($coordination->specific_service)])) ?: 'Coordinación de servicio';
            $events[] = $this->event($at, 'Operaciones', $title, $this->text($coordination->symptoms_diagnosis), null, $this->text($coordination->status), (string) ($coordination->reference_number ?? ''), 'coordination', (int) $coordination->id);
        }

        foreach ($appointments as $appointment) {
            $events[] = $this->event($appointment->appointment_at, 'Cita', 'Cita médica', null, null, $this->text($appointment->status), '', 'appointment');
        }

        usort($events, fn (array $a, array $b): int => $b['sort'] <=> $a['sort']);

        return array_map(function (array $event): array {
            unset($event['sort']);

            return $event;
        }, array_slice($events, 0, 40));
    }

    /**
     * @return array<string, mixed>
     */
    private function event(mixed $at, string $type, string $title, ?string $detail, ?string $who, ?string $status, string $reference, string $source, ?int $id = null): array
    {
        $date = $at instanceof CarbonInterface ? $at : self::parseDate(is_string($at) ? $at : null);

        return [
            'sort' => $date?->getTimestamp() ?? 0,
            'date' => $date?->format('d/m/Y') ?? '',
            'type' => $type,
            'title' => self::clip($title, 90),
            'detail' => $detail !== null ? self::clip($detail, 200) : null,
            'who' => $who,
            'status' => $status,
            'reference' => $reference !== '' ? $reference : null,
            'source' => $source,
            'id' => $id,
        ];
    }

    /**
     * Última consulta con contenido, en formato SOAP.
     *
     * @return array<string, mixed>|null
     */
    private function lastConsultation(Collection $consultations, Collection $medications, Collection $labs, Collection $studies, Collection $specialties): ?array
    {
        $consultation = $consultations->first(fn (TelemedicineConsultationPatient $item): bool => $this->text($item->diagnostic_impression) !== null || $this->text($item->reason_consultation) !== null);

        if (! $consultation instanceof TelemedicineConsultationPatient) {
            return null;
        }

        $id = (int) $consultation->id;
        $vitals = $this->vitalRow($consultation->created_at, 'Consulta', $consultation->pa, $consultation->fc, $consultation->fr, $consultation->temp, $consultation->saturacion, $consultation->peso, $consultation->estatura, $consultation->imc);
        $objective = $vitals === null ? [] : array_filter([
            $vitals['pa'] ? 'PA '.$vitals['pa'] : null, $vitals['fc'] ? 'FC '.$vitals['fc'] : null, $vitals['fr'] ? 'FR '.$vitals['fr'] : null,
            $vitals['temp'] ? 'Temp. '.$vitals['temp'] : null, $vitals['sat'] ? 'SpO₂ '.$vitals['sat'] : null, $vitals['weight'] ? 'Peso '.$vitals['weight'] : null,
        ]);
        $plan = [
            ...$medications->where('telemedicine_consultation_patient_id', $id)->map(fn ($item): ?string => $this->text($item->medicine))->filter()->values()->all(),
            ...$labs->where('telemedicine_consultation_patient_id', $id)->map(fn ($item): ?string => ($name = $this->text($item->laboratory)) ? 'Lab: '.$name : null)->filter()->values()->all(),
            ...$studies->where('telemedicine_consultation_patient_id', $id)->map(fn ($item): ?string => ($name = $this->text($item->study)) ? 'Estudio: '.$name : null)->filter()->values()->all(),
            ...$specialties->where('telemedicine_consultation_patient_id', $id)->map(fn ($item): ?string => ($name = $this->text($item->specialty)) ? 'Especialista: '.$name : null)->filter()->values()->all(),
        ];

        return [
            'id' => $id,
            'date' => $consultation->created_at?->format('d/m/Y') ?? '',
            'doctor' => $this->doctors[(int) $consultation->telemedicine_doctor_id] ?? null,
            'status' => $this->text($consultation->status),
            's' => self::clip(implode(' ', array_filter([$this->text($consultation->reason_consultation), $this->text($consultation->current_illness_history)])), 600) ?: null,
            'o' => implode(' · ', $objective) ?: null,
            'a' => ($text = $this->text($consultation->diagnostic_impression)) !== null ? self::clip($text, 400) : null,
            'p' => self::clip(implode(' · ', array_filter([implode(' · ', $plan), $this->text($consultation->observations)])), 600) ?: null,
        ];
    }

    /**
     * @return list<array{date: string, item: string, owner: string, status: string}>
     */
    private function pending(Collection $labs, Collection $studies, Collection $specialties, Collection $appointments): array
    {
        $rows = [];

        foreach ($appointments as $appointment) {
            if ($appointment->appointment_at !== null && $appointment->appointment_at->greaterThanOrEqualTo($this->now->startOfDay())) {
                $rows[] = ['date' => $appointment->appointment_at->format('d/m/Y h:i a'), 'item' => 'Cita médica', 'owner' => 'Operaciones', 'status' => $this->text($appointment->status) ?? 'Agendada'];
            }
        }

        foreach ($this->orders($labs, $studies, $specialties) as $order) {
            if ($order['open']) {
                $rows[] = ['date' => 'Pedido el '.$order['date'], 'item' => $order['kind'].': '.$order['name'], 'owner' => 'Operaciones', 'status' => (string) $order['status']];
            }
        }

        return array_slice($rows, 0, 20);
    }

    /**
     * Servicios dados por cortesía (solo ficha técnica): ítems de coordinación con segundo estatus
     * CORTESIA y órdenes de servicio marcadas como cortesía. El más reciente primero.
     *
     * @return list<array{date: string, type: string, service: string, reason: ?string, by: ?string, reference: ?string, amount: ?string}>
     */
    private function courtesies(): array
    {
        $rows = [];
        $status = \App\Support\Operations\CoordinationServiceCourtesy::STATUS;
        $sources = [
            ['Medicamento', TelemedicinePatientMedications::class, 'medicine'],
            ['Laboratorio', TelemedicinePatientLab::class, 'laboratory'],
            ['Estudio / imagen', TelemedicinePatientStudy::class, 'study'],
            ['Especialista', TelemedicinePatientSpecialty::class, 'specialty'],
        ];
        $items = [];

        foreach ($sources as [$type, $model, $column]) {
            foreach ($this->safe(fn () => $model::query()->where('telemedicine_patient_id', $this->patient->id)->where('courtesy_status', $status)->latest('id')->limit(50)->get(), new Collection) as $item) {
                $items[] = [$type, $item, $column];
            }
        }

        $references = $this->safe(fn () => OperationCoordinationService::query()
            ->whereIn('id', array_values(array_unique(array_filter(array_map(fn (array $entry): int => (int) $entry[1]->operation_coordination_service_id, $items)))))
            ->pluck('reference_number', 'id')->all(), []);

        foreach ($items as [$type, $item, $column]) {
            $at = self::parseDate($item->courtesy_updated_at) ?? ($item->created_at !== null ? CarbonImmutable::instance($item->created_at) : null);
            $rows[] = [
                'sort' => $at?->getTimestamp() ?? 0,
                'date' => $at?->format('d/m/Y') ?? '',
                'type' => $type,
                'service' => self::clip((string) ($this->text($item->getAttribute($column)) ?? $type), 120),
                'reason' => ($reason = $this->text($item->courtesy_reason)) !== null ? self::clip($reason, 200) : null,
                'by' => $this->text($item->courtesy_updated_by),
                'reference' => $this->text($references[(int) $item->operation_coordination_service_id] ?? null),
                'amount' => null,
            ];
        }

        $orders = $this->safe(fn () => \App\Models\OperationServiceOrder::query()
            ->where('is_courtesy', true)
            ->whereIn('operation_coordination_service_id', OperationCoordinationService::query()->select('id')->where('telemedicine_patient_id', $this->patient->id))
            ->latest('id')
            ->limit(30)
            ->get(['id', 'order_number', 'description', 'service_type', 'total_amount_usd', 'observations', 'created_at']), new Collection);

        foreach ($orders as $order) {
            $rows[] = [
                'sort' => $order->created_at?->getTimestamp() ?? 0,
                'date' => $order->created_at?->format('d/m/Y') ?? '',
                'type' => 'Orden de servicio',
                'service' => self::clip((string) ($this->text($order->description) ?? $this->text($order->service_type) ?? 'Orden de servicio'), 120),
                'reason' => ($note = $this->text($order->observations)) !== null ? self::clip($note, 200) : null,
                'by' => null,
                'reference' => $this->text($order->order_number),
                'amount' => is_numeric($order->total_amount_usd) && (float) $order->total_amount_usd > 0 ? number_format((float) $order->total_amount_usd, 2, ',', '.').' US$' : null,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['sort'] <=> $a['sort']);

        return array_map(function (array $row): array {
            unset($row['sort']);

            return $row;
        }, $rows);
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    private function documents(Collection $documents): array
    {
        return $documents->map(fn (TelemedicineDocument $document): array => [
            'date' => $document->created_at?->format('d/m/Y') ?? '',
            'name' => self::clip((string) $document->name, 90),
        ])->values()->all();
    }

    /**
     * @param  list<mixed>  $ids
     * @return array<int, string>
     */
    private function doctorNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        return $this->safe(fn () => TelemedicineDoctor::query()->whereIn('id', $ids)->pluck('full_name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => mb_convert_case(mb_strtolower(trim((string) $name)), MB_CASE_TITLE)])
            ->all(), []);
    }

    /**
     * Texto limpio para mostrar: sin HTML, sin espacios de más y sin rellenos como «XXX».
     */
    public function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        if ($clean === '' || in_array(mb_strtoupper($clean), self::FILLER, true) || in_array($clean, ['[]', 'null'], true)) {
            return null;
        }

        return $clean;
    }

    private function clipText(?string $value): ?string
    {
        $text = $this->text($value);

        return $text !== null ? self::clip($text, 300) : null;
    }

    public static function clip(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)).'…' : $text;
    }

    /**
     * Fechas guardadas como texto en varios formatos (d/m/Y, j/n/Y, Y-m-d…).
     */
    public static function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y-m-d H:i:s', 'd/m/Y H:i', 'd/m/Y H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                continue;
            }

            $date = CarbonImmutable::instance($date);

            return $date->year >= 1900 && $date->year <= 2100 ? $date : null;
        }

        return null;
    }

    /**
     * La consulta guarda la PA como decimal: 120.80 significa 120/80. Un texto ya escrito «120/80» se respeta.
     */
    private static function bloodPressure(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if (! is_numeric($raw)) {
            return $raw !== '' ? mb_substr($raw, 0, 20) : null;
        }

        if ((float) $raw <= 0) {
            return null;
        }

        [$systolic, $diastolic] = array_pad(explode('.', number_format((float) $raw, 2, '.', '')), 2, '00');

        return (int) $diastolic > 0 ? $systolic.'/'.$diastolic : $systolic;
    }

    /**
     * Duración escrita en las indicaciones («por 10 días», «por 1 mes», «por 2 semanas»).
     */
    public static function daysFromText(string $text): int
    {
        if (preg_match('/\bpor\s+(\d{1,3}|un|una)\s*(d[ií]as?|semanas?|mes(?:es)?)\b/iu', $text, $match) !== 1) {
            return 0;
        }

        $amount = ctype_digit($match[1]) ? (int) $match[1] : 1;
        $unit = mb_strtolower($match[2]);

        return match (true) {
            str_starts_with($unit, 'sem') => $amount * 7,
            str_starts_with($unit, 'mes') => $amount * 30,
            default => $amount,
        };
    }

    private static function measure(mixed $value, string $unit = ''): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '0' || (is_numeric($value) && (float) $value <= 0)) {
            return null;
        }

        if (is_numeric($value)) {
            $value = rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        }

        return mb_substr($value, 0, 20).$unit;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  T  $fallback
     * @return T
     */
    private function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            Log::warning('Ficha clínica: una fuente no respondió', ['patient' => $this->patient->id, 'error' => $exception->getMessage()]);

            return $fallback;
        }
    }
}
