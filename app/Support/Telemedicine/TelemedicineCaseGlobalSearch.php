<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use App\Support\Filament\BusinessGlobalSearch;
use Filament\GlobalSearch\GlobalSearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Buscador global del panel médico: encuentra casos por número de caso, cédula,
 * nombre del paciente o diagnóstico.
 *
 * Cada palabra del término debe coincidir en al menos uno de esos campos, así
 * que «genesis dispepsico» acota a los casos de esa paciente con ese
 * diagnóstico y el nombre se encuentra en cualquier orden. Las tildes no
 * importan porque las columnas usan `utf8mb4_unicode_ci`.
 *
 * El alcance es el de la Bitácora del médico: sus casos asignados (o el pool
 * TDG), incluidas las altas médicas, para que pueda consultar la historia de un
 * paciente que regresa. Los casos eliminados los oculta el scope global.
 */
final class TelemedicineCaseGlobalSearch
{
    public const RESULTS_LIMIT = 15;

    public const MIN_TERM_LENGTH = 2;

    public const MAX_WORDS = 6;

    public const MAX_TERM_LENGTH = 100;

    public const DIAGNOSIS_PREVIEW_LENGTH = 70;

    public static function normalizeTerm(string $search): string
    {
        $term = Str::squish($search);

        return mb_substr($term, 0, self::MAX_TERM_LENGTH);
    }

    /**
     * Palabras a exigir. Una cédula escrita con espacios («V 22.171.244») se
     * trata como una sola palabra para no partirla.
     *
     * @return list<string>
     */
    public static function words(string $search): array
    {
        $term = self::normalizeTerm($search);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        if (str_contains($term, ' ') && BusinessGlobalSearch::looksLikeDocument($term)) {
            return [BusinessGlobalSearch::normalizeDocument($term)];
        }

        $words = array_values(array_unique(array_filter(
            explode(' ', $term),
            static fn (string $word): bool => $word !== '',
        )));

        return array_slice($words, 0, self::MAX_WORDS);
    }

    /**
     * Dígitos de una cédula escrita con prefijo, puntos o guiones
     * («V-22.171.244» → «22171244»), o null si la palabra no parece una cédula.
     */
    public static function documentDigits(string $word): ?string
    {
        if (! BusinessGlobalSearch::looksLikeDocument($word)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $word);

        return is_string($digits) && $digits !== '' ? $digits : null;
    }

    /**
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function constrain(Builder $query, string $search): Builder
    {
        $words = self::words($search);

        if ($words === []) {
            return $query->whereRaw('0 = 1');
        }

        foreach ($words as $word) {
            $query->where(function (Builder $match) use ($word): void {
                self::applyWordMatch($match, $word);
            });
        }

        return self::applyRelevanceOrder($query, $words);
    }

    /**
     * @param  Builder<TelemedicineCase>  $query
     */
    private static function applyWordMatch(Builder $query, string $word): void
    {
        $like = '%'.self::escapeLike($word).'%';
        $digits = self::documentDigits($word);
        $digitsLike = $digits !== null ? '%'.$digits.'%' : null;

        $query
            ->where('telemedicine_cases.code', 'like', $like)
            ->orWhere('telemedicine_cases.patient_name', 'like', $like)
            ->orWhereHas('telemedicinePatient', function (Builder $patient) use ($like, $digitsLike): void {
                $patient->where(function (Builder $patientMatch) use ($like, $digitsLike): void {
                    $patientMatch
                        ->where('full_name', 'like', $like)
                        ->orWhere('nro_identificacion', 'like', $like);

                    if ($digitsLike !== null) {
                        $patientMatch->orWhere('nro_identificacion', 'like', $digitsLike);
                    }
                });
            })
            ->orWhereHas('consultations', function (Builder $consultation) use ($like, $digitsLike): void {
                $consultation->where(function (Builder $consultationMatch) use ($like, $digitsLike): void {
                    $consultationMatch
                        ->where('diagnostic_impression', 'like', $like)
                        ->orWhere('nro_identificacion', 'like', $like);

                    if ($digitsLike !== null) {
                        $consultationMatch->orWhere('nro_identificacion', 'like', $digitsLike);
                    }
                });
            });
    }

    /**
     * Primero el número de caso exacto, luego la cédula exacta, luego los
     * códigos que empiezan por el término; dentro de cada grupo, los casos
     * activos antes que las altas y los más recientes primero.
     *
     * @param  Builder<TelemedicineCase>  $query
     * @param  list<string>  $words
     * @return Builder<TelemedicineCase>
     */
    private static function applyRelevanceOrder(Builder $query, array $words): Builder
    {
        $first = $words[0];
        $document = self::documentDigits($first) ?? $first;

        return $query
            ->orderByRaw(
                'CASE'
                .' WHEN telemedicine_cases.code = ? THEN 0'
                .' WHEN EXISTS (SELECT 1 FROM telemedicine_patients AS search_patient'
                .' WHERE search_patient.id = telemedicine_cases.telemedicine_patient_id'
                .' AND search_patient.nro_identificacion IN (?, ?)) THEN 1'
                .' WHEN telemedicine_cases.code LIKE ? THEN 2'
                .' ELSE 3 END',
                [$first, $first, $document, self::escapeLike($first).'%'],
            )
            ->orderByRaw("CASE WHEN telemedicine_cases.status = 'ALTA MEDICA' THEN 1 ELSE 0 END")
            ->orderByDesc('telemedicine_cases.updated_at')
            ->orderByDesc('telemedicine_cases.id');
    }

    /**
     * @return Collection<int, TelemedicineCase>
     */
    public static function cases(string $search): Collection
    {
        if (self::words($search) === []) {
            return collect();
        }

        $query = TelemedicineCase::query()->select('telemedicine_cases.*');

        TelemedicineCaseFilamentListQuery::applyTelemedicinaGlobalSearchConstraints($query);

        return self::constrain($query, $search)
            ->with([
                'telemedicinePatient:id,full_name,nro_identificacion',
                'telemedicineDoctor:id,full_name',
                'consultations' => fn ($consultations) => $consultations
                    ->select(['id', 'telemedicine_case_id', 'diagnostic_impression'])
                    ->whereNotNull('diagnostic_impression')
                    ->where('diagnostic_impression', '!=', '')
                    ->latest('id')
                    ->limit(1),
            ])
            ->withMax('consultations', 'created_at')
            ->limit(self::RESULTS_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, GlobalSearchResult>
     */
    public static function results(string $search): Collection
    {
        $cases = self::cases($search);

        if ($cases->isEmpty()) {
            return collect();
        }

        $user = Auth::user();
        $ownDoctorId = $user instanceof User ? $user->doctor_id : null;
        $showDoctor = $ownDoctorId !== null;

        return $cases->map(fn (TelemedicineCase $case): GlobalSearchResult => new GlobalSearchResult(
            title: self::title($case),
            url: TelemedicineCaseResource::getUrl('view', ['record' => $case]),
            details: self::details($case, $showDoctor, $ownDoctorId),
        ));
    }

    public static function title(TelemedicineCase $case): string
    {
        $patientName = Str::squish((string) ($case->telemedicinePatient?->full_name ?: $case->patient_name));
        $code = filled($case->code) ? (string) $case->code : 'Caso #'.$case->id;

        return $patientName === '' ? $code : $code.' · '.$patientName;
    }

    /**
     * @return array<string, string>
     */
    public static function details(TelemedicineCase $case, bool $showDoctor = false, mixed $ownDoctorId = null): array
    {
        $details = [];

        $document = trim((string) $case->telemedicinePatient?->nro_identificacion);
        if ($document !== '') {
            $details['Cédula'] = $document;
        }

        $details['Estado'] = self::statusLabel((string) $case->status);

        /**
         * Último diagnóstico registrado, aunque la consulta más reciente no
         * tenga uno: la relación viene acotada así desde {@see self::cases()}.
         *
         * @var TelemedicineConsultationPatient|null $withDiagnosis
         */
        $withDiagnosis = $case->relationLoaded('consultations') ? $case->consultations->first() : null;
        $diagnosis = Str::squish((string) $withDiagnosis?->diagnostic_impression);

        if ($diagnosis !== '') {
            $details['Diagnóstico'] = Str::limit($diagnosis, self::DIAGNOSIS_PREVIEW_LENGTH);
        }

        $lastConsultationAt = $case->getAttribute('consultations_max_created_at');
        if (filled($lastConsultationAt)) {
            $details['Última consulta'] = Carbon::parse((string) $lastConsultationAt)->format('d/m/Y');
        }

        $doctorName = Str::squish((string) $case->telemedicineDoctor?->full_name);
        if ($showDoctor && $doctorName !== '' && (string) $case->telemedicine_doctor_id !== (string) $ownDoctorId) {
            $details['Médico'] = $doctorName;
        }

        return $details;
    }

    public static function statusLabel(string $status): string
    {
        return match (mb_strtoupper(trim($status))) {
            'ALTA MEDICA' => 'Alta médica',
            'EN SEGUIMIENTO' => 'En seguimiento',
            'ASIGNADO' => 'Asignado',
            '' => 'Sin estado',
            default => Str::ucfirst(mb_strtolower(trim($status))),
        };
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
