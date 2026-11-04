<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QualityControlAffiliateType;
use App\Enums\QualityControlRating;
use App\Enums\QualityControlService;
use App\Models\QualityControlSurvey;
use App\Models\QualityControlSurveyNote;
use App\Support\Operations\QualityControl\QualityControlAffiliateLookup;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PDF de Quality Control: la ficha de una encuesta y el informe de un listado filtrado.
 *
 * Como el PDF de la orden de servicio, se renderiza como mucho una vez por contenido: el
 * HTML es determinista y su huella nombra el archivo guardado. Vista previa, descarga,
 * correo y WhatsApp usan la misma copia. Se guarda en el disco **privado** (trae datos de
 * pacientes); para WhatsApp se publica una copia con enlace firmado que vence.
 */
final class QualityControlPdfService
{
    /** Se sube si cambian opciones de DomPDF que no se ven en el HTML. */
    public const RENDER_VERSION = '1';

    public const DISK = 'local';

    public const DIRECTORY = 'quality-control-pdf';

    /** Encuestas que admite un informe: más que eso, se pide acotar con filtros. */
    public const REPORT_MAX_SURVEYS = 20000;

    /** Filas de la tabla de detalle del informe (los totales cuentan todas). */
    public const REPORT_TABLE_LIMIT = 300;

    /** Encuestas neutras o insatisfechas que se listan con su seguimiento. */
    public const REPORT_LOW_LIMIT = 100;

    private const LOGO_FILE = 'image/logoNewPdf-800.png';

    /**
     * @return array{path: string, contents: string, fingerprint: string, filename: string}
     */
    public static function surveyDocument(QualityControlSurvey $survey): array
    {
        $html = self::surveyHtml($survey);

        return self::persisted($html, 'surveys', 'encuesta-'.$survey->getKey(), self::surveyFilename($survey));
    }

    public static function surveyHtml(QualityControlSurvey $survey): string
    {
        $survey->loadMissing(['notes', 'individualAffiliate', 'corporateAffiliate']);
        $type = $survey->affiliate_type;
        $affiliate = $type === QualityControlAffiliateType::Individual ? $survey->individualAffiliate : $survey->corporateAffiliate;

        return view('documents.quality-control-survey', [
            'survey' => $survey,
            'typeLabel' => $type->label(),
            'affiliateName' => $affiliate !== null ? QualityControlAffiliateLookup::nameOf($affiliate) : null,
            'affiliateStatus' => $affiliate?->status,
            'ratings' => self::ratingRows($survey),
            'services' => QualityControlService::labelsFor($survey->services),
            'notes' => $survey->notes,
            'logoDataUri' => self::logoDataUri(),
        ])->render();
    }

    public static function surveyFilename(QualityControlSurvey $survey): string
    {
        $patient = Str::slug(Str::limit((string) $survey->patient_name, 40, ''), '-') ?: 'paciente';

        return 'quality-control-'.$patient.'-'.$survey->getKey().'.pdf';
    }

    /**
     * @param  list<int>  $surveyIds  encuestas del listado filtrado (ya acotadas por tipo)
     * @return array{path: string, contents: string, fingerprint: string, filename: string}
     */
    public static function reportDocument(QualityControlAffiliateType $type, array $surveyIds, string $filtersLabel): array
    {
        $html = self::reportHtml($type, $surveyIds, $filtersLabel);

        return self::persisted($html, 'reports', 'informe-'.Str::lower($type->value), self::reportFilename($type));
    }

    /**
     * @param  list<int>  $surveyIds
     */
    public static function reportHtml(QualityControlAffiliateType $type, array $surveyIds, string $filtersLabel): string
    {
        $surveys = collect();

        foreach (array_chunk(array_values(array_unique(array_map('intval', $surveyIds))), 1000) as $chunk) {
            $surveys = $surveys->concat(
                QualityControlSurvey::query()
                    ->ofType($type)
                    ->whereIn('id', $chunk)
                    ->withCount('notes')
                    ->get(['id', 'affiliate_type', 'responded_at', 'service_date', 'patient_name', 'patient_document', 'rating_channels', 'rating_response_time', 'rating_medical_service', 'rating_overall', 'services', 'observations'])
            );
        }

        $surveys = $surveys->sortByDesc(fn (QualityControlSurvey $survey): string => $survey->responded_at?->format('YmdHis').str_pad((string) $survey->getKey(), 10, '0', STR_PAD_LEFT))->values();

        $questions = [];
        foreach (QualityControlSurvey::RATING_FIELDS as $field => $label) {
            $counts = array_fill_keys(array_map(fn (QualityControlRating $rating): string => $rating->value, QualityControlRating::cases()), 0);
            $answered = 0;

            foreach ($surveys as $survey) {
                $rating = $survey->getAttribute($field);

                if ($rating instanceof QualityControlRating) {
                    $counts[$rating->value]++;
                    $answered++;
                }
            }

            $satisfied = $counts[QualityControlRating::TotallySatisfied->value] + $counts[QualityControlRating::Satisfied->value];

            $questions[] = [
                'label' => $label,
                'counts' => $counts,
                'answered' => $answered,
                'rate' => $answered > 0 ? (int) round($satisfied * 100 / $answered) : null,
            ];
        }

        $services = array_fill_keys(array_keys(QualityControlService::options()), 0);
        foreach ($surveys as $survey) {
            foreach ((array) $survey->services as $service) {
                if (array_key_exists($service, $services)) {
                    $services[$service]++;
                }
            }
        }
        arsort($services);

        $low = $surveys->filter(function (QualityControlSurvey $survey): bool {
            foreach (array_keys(QualityControlSurvey::RATING_FIELDS) as $field) {
                $rating = $survey->getAttribute($field);

                if ($rating === QualityControlRating::Neutral || $rating === QualityControlRating::Dissatisfied) {
                    return true;
                }
            }

            return false;
        })->values();

        $lowShown = $low->take(self::REPORT_LOW_LIMIT)->values();
        $latestNotes = $lowShown->isEmpty() ? collect() : QualityControlSurveyNote::query()
            ->whereIn('quality_control_survey_id', $lowShown->pluck('id'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['quality_control_survey_id', 'note', 'created_by_name', 'created_at'])
            ->unique('quality_control_survey_id')
            ->keyBy('quality_control_survey_id');

        $from = $surveys->min(fn (QualityControlSurvey $survey): ?string => $survey->responded_at?->format('Y-m-d'));
        $to = $surveys->max(fn (QualityControlSurvey $survey): ?string => $survey->responded_at?->format('Y-m-d'));

        return view('documents.quality-control-report', [
            'typeLabel' => $type->label(),
            'filtersLabel' => $filtersLabel,
            'generatedOn' => Carbon::today()->format('d/m/Y'),
            'total' => $surveys->count(),
            'withFollowUp' => $surveys->where('notes_count', '>', 0)->count(),
            'range' => $from !== null ? Carbon::parse($from)->format('d/m/Y').' al '.Carbon::parse($to)->format('d/m/Y') : '—',
            'questions' => $questions,
            'ratingLabels' => QualityControlRating::options(),
            'services' => collect($services)->mapWithKeys(fn (int $count, string $service): array => [QualityControlService::from($service)->label() => $count])->all(),
            'low' => $lowShown,
            'lowTotal' => $low->count(),
            'latestNotes' => $latestNotes,
            'rows' => $surveys->take(self::REPORT_TABLE_LIMIT)->values(),
            'rowsLimited' => $surveys->count() > self::REPORT_TABLE_LIMIT,
            'logoDataUri' => self::logoDataUri(),
        ])->render();
    }

    public static function reportFilename(QualityControlAffiliateType $type): string
    {
        return 'informe-quality-control-'.Str::lower($type->label()).'-'.Carbon::today()->format('Y-m-d').'.pdf';
    }

    /**
     * Copia para WhatsApp: archivo propio con nombre impredecible, servido solo por enlace
     * firmado que vence. Se borran las copias de más de 3 días.
     */
    public static function shareCopy(string $contents): string
    {
        $disk = Storage::disk(self::DISK);
        $limit = Carbon::now()->subDays(3)->getTimestamp();

        foreach ($disk->files(self::DIRECTORY.'/shared') as $file) {
            if ($disk->lastModified($file) < $limit) {
                $disk->delete($file);
            }
        }

        $share = (string) Str::uuid();
        $disk->put(self::DIRECTORY.'/shared/'.$share.'.pdf', $contents);

        return $share;
    }

    public static function sharedPath(string $share): ?string
    {
        if (! Str::isUuid($share)) {
            return null;
        }

        $path = self::DIRECTORY.'/shared/'.$share.'.pdf';

        return Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    public static function fingerprint(string $html): string
    {
        return substr(sha1(self::RENDER_VERSION.'|'.$html), 0, 16);
    }

    /**
     * @return array{path: string, contents: string, fingerprint: string, filename: string}
     */
    private static function persisted(string $html, string $folder, string $prefix, string $filename): array
    {
        $disk = Storage::disk(self::DISK);
        $fingerprint = self::fingerprint($html);
        $path = self::DIRECTORY.'/'.$folder.'/'.$prefix.'-v'.$fingerprint.'.pdf';

        if ($disk->exists($path)) {
            $contents = (string) $disk->get($path);

            if (strlen($contents) > 100) {
                return ['path' => $path, 'contents' => $contents, 'fingerprint' => $fingerprint, 'filename' => $filename];
            }
        }

        $contents = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();

        /** Las versiones viejas del mismo documento ya no sirven: se reemplazan. */
        foreach ($disk->files(self::DIRECTORY.'/'.$folder) as $file) {
            if (str_starts_with(basename($file), $prefix.'-v') && $file !== $path) {
                $disk->delete($file);
            }
        }

        $disk->put($path, $contents);

        return ['path' => $path, 'contents' => $contents, 'fingerprint' => $fingerprint, 'filename' => $filename];
    }

    /**
     * @return list<array{label: string, value: string, color: string}>
     */
    private static function ratingRows(QualityControlSurvey $survey): array
    {
        $rows = [];

        foreach (QualityControlSurvey::RATING_FIELDS as $field => $label) {
            $rating = $survey->getAttribute($field);
            $rows[] = [
                'label' => $label,
                'value' => $rating instanceof QualityControlRating ? $rating->label() : 'Sin respuesta',
                'color' => self::ratingHex($rating instanceof QualityControlRating ? $rating : null),
            ];
        }

        return $rows;
    }

    public static function ratingHex(?QualityControlRating $rating): string
    {
        return match ($rating) {
            QualityControlRating::TotallySatisfied, QualityControlRating::Satisfied => '#047857',
            QualityControlRating::Neutral => '#b45309',
            QualityControlRating::Dissatisfied => '#b91c1c',
            null => '#6b7280',
        };
    }

    private static function logoDataUri(): string
    {
        static $dataUri = null;

        if ($dataUri !== null) {
            return $dataUri;
        }

        $path = public_path(self::LOGO_FILE);
        $path = is_file($path) ? $path : public_path('image/logoNewPdf.png');
        $contents = is_file($path) ? file_get_contents($path) : false;

        return $dataUri = ($contents === false || $contents === '') ? '' : 'data:image/png;base64,'.base64_encode($contents);
    }
}
