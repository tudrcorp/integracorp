<?php

declare(strict_types=1);

namespace App\Support\Quotes\Design;

use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\QuoteDesignVersion;
use App\Models\User;
use App\Support\CorporateQuotePdfGenerator;
use App\Support\IndividualQuotePdfGenerator;
use App\Support\IndividualQuotePdfLayout;
use App\Support\Quotes\IndividualQuoteProposalPages;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Propuesta económica de una cotización cuyos planes tienen diseño de cotización.
 *
 * Arma el documento completo aquí, sin el microservicio:
 *
 * 1. Páginas fijas de antes: las del diseño si **todos** los planes comparten el mismo diseño y
 *    este trae imágenes; si no, la portada y el «acerca de» de siempre.
 * 2. Una página de cálculos por plan, en orden de plan: con su diseño, o la página dinámica de
 *    siempre si ese plan no tiene diseño. El Plan Especial conserva su hoja de patologías.
 * 3. Páginas fijas de después: mismas reglas que las de antes (contraportada de siempre).
 *
 * Si algo falla devuelve `false` y el generador sigue con el flujo de siempre: el analista
 * nunca se queda sin propuesta por culpa de un diseño.
 */
final class QuoteDesignedProposal
{
    private const PATHOLOGIES_PLAN_ID = 3;

    /**
     * @param  list<int|string>  $planIds
     * @param  bool  $quoteIsNew  `false` al regenerar una propuesta perdida: solo se respeta el
     *                            diseño con el que se emitió, nunca se le pone uno nuevo
     */
    public static function generate(string $scope, IndividualQuote|CorporateQuote $quote, array $planIds, bool $quoteIsNew = true): bool
    {
        $planIds = self::normalizePlanIds($planIds);
        $files = [];

        try {
            if (! QuoteDesignResolver::anyPlanHasDesign($scope, (int) $quote->getKey(), $planIds, $quoteIsNew)) {
                return false;
            }

            $plans = self::plans($scope, $quote, $planIds, $quoteIsNew);

            if ($plans === []) {
                return false;
            }

            $document = self::assemble($scope, $plans, $files, self::sharedTheme($plans));
            $code = (string) $quote->code;

            if (! self::store($code, $document)) {
                return false;
            }

            Log::info('quote-design: propuesta generada con diseño de cotización', [
                'code' => $code,
                'scope' => $scope,
                'plans' => array_map(static fn (array $plan): array => [
                    'plan_id' => $plan['plan_id'],
                    'design_version_id' => $plan['version']?->getKey(),
                ], $plans),
            ]);

            self::notifySuccess($code);

            return true;
        } catch (Throwable $exception) {
            Log::error('quote-design: no se pudo armar la propuesta con diseño; se usa el flujo de siempre', [
                'code' => $quote->code,
                'scope' => $scope,
                'message' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Documento completo a partir de planes ya resueltos. Lo usa también la vista previa.
     *
     * @param  list<array{plan_id: int, details: array<string, mixed>, theme: ?QuoteDesignTheme, version: ?QuoteDesignVersion}>  $plans
     * @param  list<string>  $files  temporales creados (el llamador los borra)
     */
    public static function assemble(string $scope, array $plans, array &$files, ?QuoteDesignTheme $fixedPagesTheme): string
    {
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);

        if ($fixedPagesTheme !== null && $fixedPagesTheme->pagesBefore !== []) {
            self::appendImages($pdf, $fixedPagesTheme, $fixedPagesTheme->pagesBefore);
        } else {
            self::appendDocument($pdf, self::renderDefaultPages('before'), $files);
        }

        foreach ($plans as $plan) {
            $page = $plan['theme'] !== null
                ? QuoteDesignPage::render($plan['details'], $plan['theme'], $scope)
                : IndividualQuoteProposalPages::renderPlanPage($plan['details']);

            self::appendDocument($pdf, $page, $files);

            if ($plan['plan_id'] === self::PATHOLOGIES_PLAN_ID) {
                self::appendDocument($pdf, self::renderDefaultPages('pathologies'), $files);
            }
        }

        if ($fixedPagesTheme !== null && $fixedPagesTheme->pagesAfter !== []) {
            self::appendImages($pdf, $fixedPagesTheme, $fixedPagesTheme->pagesAfter);
        } else {
            self::appendDocument($pdf, self::renderDefaultPages('after'), $files);
        }

        return (string) $pdf->Output('S');
    }

    /**
     * Las páginas fijas del diseño solo se usan si todos los planes llevan el mismo diseño: en una
     * multiplan con planes de marcas distintas, la portada de una marca no puede presidir la otra.
     *
     * @param  list<array{plan_id: int, details: array<string, mixed>, theme: ?QuoteDesignTheme, version: ?QuoteDesignVersion}>  $plans
     */
    public static function sharedTheme(array $plans): ?QuoteDesignTheme
    {
        $designIds = [];

        foreach ($plans as $plan) {
            if ($plan['version'] === null || $plan['theme'] === null) {
                return null;
            }

            $designIds[(int) $plan['version']->quote_design_id] = true;
        }

        if (count($designIds) !== 1) {
            return null;
        }

        return $plans[0]['theme'];
    }

    /**
     * @param  list<int>  $planIds
     * @return list<array{plan_id: int, details: array<string, mixed>, theme: ?QuoteDesignTheme, version: ?QuoteDesignVersion}>
     */
    private static function plans(string $scope, IndividualQuote|CorporateQuote $quote, array $planIds, bool $quoteIsNew): array
    {
        $plans = [];

        foreach ($planIds as $planId) {
            $details = self::details($quote, $planId);

            if ($details === null) {
                continue;
            }

            $version = QuoteDesignResolver::forIssuance($scope, (int) $quote->getKey(), $planId, $quoteIsNew);

            $plans[] = [
                'plan_id' => $planId,
                'details' => $details,
                'theme' => $version !== null ? QuoteDesignTheme::fromSnapshot((array) $version->snapshot) : null,
                'version' => $version,
            ];
        }

        return $plans;
    }

    /**
     * Mismo payload que la página dinámica de siempre: estructura del plan, tarifas y upgrades.
     *
     * @return array<string, mixed>|null
     */
    private static function details(IndividualQuote|CorporateQuote $quote, int $planId): ?array
    {
        $layout = IndividualQuotePdfLayout::resolveForIndividual($planId);

        return $quote instanceof CorporateQuote
            ? CorporateQuotePdfGenerator::detailsPayload($quote, $planId, $layout)
            : IndividualQuotePdfGenerator::detailsPayload($quote, $planId, $layout);
    }

    /**
     * Páginas fijas de siempre, dibujadas con las mismas vistas que la propuesta local.
     */
    private static function renderDefaultPages(string $part): string
    {
        return Pdf::loadView('documents.propuesta-paginas-fijas', ['part' => $part])
            ->setPaper('a4')
            ->output();
    }

    /**
     * @param  list<string>  $paths
     */
    private static function appendImages(Fpdi $pdf, QuoteDesignTheme $theme, array $paths): void
    {
        foreach ($paths as $path) {
            $absolute = $theme->absolutePath($path);

            if ($absolute === null || ! is_file($absolute)) {
                continue;
            }

            $pdf->AddPage('P', 'A4');
            $pdf->Image($absolute, 0, 0, 210, 297, 'JPG');
        }
    }

    /**
     * @param  list<string>  $files
     */
    private static function appendDocument(Fpdi $pdf, string $document, array &$files): void
    {
        $file = tempnam(sys_get_temp_dir(), 'quote-design-');

        if ($file === false) {
            throw new \RuntimeException('No se pudo crear un archivo temporal para la propuesta.');
        }

        file_put_contents($file, $document);
        $files[] = $file;

        $pages = $pdf->setSourceFile($file);

        for ($page = 1; $page <= $pages; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }
    }

    private static function store(string $code, string $document): bool
    {
        if (trim($code) === '') {
            return false;
        }

        $directory = public_path('storage/quotes');
        File::ensureDirectoryExists($directory);

        /** Se escribe aparte y se renombra: quien descarga nunca ve un PDF a medio escribir. */
        $target = $directory.'/'.$code.'.pdf';
        $temporary = $target.'.'.uniqid('tmp', true);

        if (file_put_contents($temporary, $document) === false) {
            Log::warning('quote-design: no se pudo escribir la propuesta en disco', ['code' => $code]);

            return false;
        }

        if (! rename($temporary, $target)) {
            @unlink($temporary);
            Log::warning('quote-design: no se pudo reemplazar la propuesta en disco', ['code' => $code]);

            return false;
        }

        return true;
    }

    private static function notifySuccess(string $code): void
    {
        $notification = Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('📎 '.$code.'.pdf ya se encuentra disponible para su descarga.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar archivo')
                    ->url('/storage/quotes/'.$code.'.pdf'),
            ]);

        $user = Auth::user();

        try {
            if ($user instanceof User) {
                $notification->sendToDatabase($user);

                return;
            }

            $notification->send();
        } catch (Throwable) {
            /** Avisar es secundario: el PDF ya quedó escrito. */
        }
    }

    /**
     * @param  list<int|string>  $planIds
     * @return list<int>
     */
    private static function normalizePlanIds(array $planIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $planIds), static fn (int $id): bool => $id > 0)));
        sort($ids);

        return $ids;
    }

    /**
     * Ámbito de la cotización, para los llamadores que tienen el modelo.
     */
    public static function scopeFor(IndividualQuote|CorporateQuote $quote): string
    {
        return $quote instanceof CorporateQuote ? QuoteDocumentLayout::SCOPE_CORPORATE : QuoteDocumentLayout::SCOPE_INDIVIDUAL;
    }
}
