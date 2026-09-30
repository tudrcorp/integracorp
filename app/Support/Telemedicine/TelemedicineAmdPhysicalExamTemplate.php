<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineAmdPhysicalExam;

/**
 * Plantilla del examen físico AMD: signos vitales y el texto normal de cada
 * sistema. El médico sólo edita lo que encuentre alterado.
 */
final class TelemedicineAmdPhysicalExamTemplate
{
    /**
     * Largo de la columna de cada signo vital.
     */
    public const VITAL_MAX_LENGTH = 30;

    /**
     * columna => [etiqueta, unidad]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const VITALS = [
        'heart_rate' => ['Frecuencia cardíaca', 'lpm'],
        'respiratory_rate' => ['Frecuencia respiratoria', 'rpm'],
        'blood_pressure' => ['Presión arterial', 'mmHg'],
        'pulse' => ['Pulso', 'lpm'],
        'oxygen_saturation' => ['SpO2', '%'],
    ];

    /**
     * columna => [etiqueta, texto normal por defecto]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SYSTEMS = [
        'skin' => ['PIEL', 'Normocoloreada, normotérmica, hidratada, elasticidad, grosor y movilidad normales, sin lesiones, llenado capilar <3 seg.; PIEL, CICATRICES, TATUAJES: N/A.'],
        'head' => ['CABEZA, CRÁNEO', 'Normocefálico, normosimétrico, cabello bien implantado, sin masas palpables ni visibles.; OJOS: Cejas y pestañas íntegras, ojos simétricos, conjuntiva palpebral y bulbar normocoloreada, pupilas isocóricas, normorreactivas a la luz. Agudeza visual conservada.'],
        'ears' => ['OÍDOS', 'Pabellones auriculares simétricos, bien implantados, no dolorosos a la tracción, conductos auditivos externos permeables sin salida de secreciones, membrana timpánica indemne.'],
        'nose' => ['NARIZ', 'Pirámide nasal central, fosas nasales permeables, sin alteraciones anatómicas visibles, sin salida de secreciones, sin masas palpables ni visibles, senos paranasales no dolorosos al tacto.'],
        'mouth' => ['BOCA', 'Labios simétricos, mucosa oral húmeda, normocoloreada e íntegra, amígdalas eutróficas no eritematosas, dientes completos y simétricos, sin caries.'],
        'neck' => ['CUELLO Y TIROIDES', 'Simétrico. Ausencia de tumoraciones, sin adenopatías. Tiroides Grado 0.'],
        'thorax' => ['TÓRAX', 'Simétrico, normoexpansible. Ruidos respiratorios presentes en ambos hemitórax sin agregados. Ruidos cardíacos rítmicos y regulares sin soplos ni galopes.'],
        'abdomen' => ['ABDOMEN', 'Plano, buena coloración y pigmentación, ruidos hidroaéreos presentes, indoloro a la palpación, blando, depresible, ausencia de masas visibles y palpables, sin megalias.'],
        'genitourinary' => ['GENITOURINARIO', 'Genitales: No explorados. Puño percusión renal bilateral negativa.'],
        'extremities' => ['EXTREMIDADES', 'Miembros Superiores: Simétricos, eutróficos, móviles, sin edema / Miembros Inferiores: Simétricos, eutróficos, móviles, sin edema.'],
        'nervous_system' => ['SISTEMA NERVIOSO', 'Tono Muscular: 0, Fuerza Muscular: V/V, Reflejos Osteotendinosos: II/VI, Pares Craneales indemnes.'],
        'mental_status' => ['ESTADO MENTAL', 'Consciente, orientado en los tres planos: tiempo, espacio y persona. Memoria anterógrada y retrógrada conservada.; DORSO Y C. VERT: Normal; HERNIAS: No palpables.'],
    ];

    /**
     * Estado inicial del formulario: signos vitales vacíos y sistemas normales.
     *
     * @return array<string, string|null>
     */
    public static function defaults(): array
    {
        return [
            ...array_fill_keys(array_keys(self::VITALS), null),
            ...array_map(static fn (array $system): string => $system[1], self::SYSTEMS),
        ];
    }

    public static function defaultFor(string $system): string
    {
        return self::SYSTEMS[$system][1] ?? '';
    }

    /**
     * El texto de un sistema difiere del normal (espacios y saltos no cuentan).
     */
    public static function isEdited(string $system, mixed $value): bool
    {
        $normalize = static fn (mixed $text): string => (string) preg_replace('/\s+/u', ' ', trim((string) ($text ?? '')));

        return $normalize($value) !== $normalize(self::defaultFor($system));
    }

    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [...array_keys(self::VITALS), ...array_keys(self::SYSTEMS)];
    }

    /**
     * Examen listo para mostrar: signos con unidad y sistemas con su texto.
     *
     * @return array{vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}
     */
    public static function display(TelemedicineAmdPhysicalExam $exam): array
    {
        $vitals = [];

        foreach (self::VITALS as $column => [$label, $unit]) {
            $value = trim((string) ($exam->getAttribute($column) ?? ''));

            if ($value !== '') {
                $vitals[] = ['label' => $label, 'value' => self::withUnit($value, $unit)];
            }
        }

        $systems = [];

        foreach (self::SYSTEMS as $column => [$label, $default]) {
            $text = trim((string) ($exam->getAttribute($column) ?? ''));

            if ($text !== '') {
                $systems[] = ['label' => $label, 'text' => $text, 'is_default' => $text === $default];
            }
        }

        return ['vitals' => $vitals, 'systems' => $systems];
    }

    /**
     * No repite la unidad si el médico ya la escribió («80lpm», «98 %»).
     */
    private static function withUnit(string $value, string $unit): string
    {
        return str_ends_with(mb_strtolower(str_replace(' ', '', $value)), mb_strtolower($unit))
            ? $value
            : $value.' '.$unit;
    }
}
