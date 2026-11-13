<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FiscalBook;
use App\Filament\Administration\Resources\FiscalBooks\FiscalPurchasesBookResource;
use App\Filament\Administration\Resources\FiscalBooks\FiscalSalesBookResource;
use App\Support\Administration\FiscalBooks\FiscalBookExcelWriter;
use App\Support\Administration\FiscalBooks\FiscalBookPdf;
use App\Support\Administration\FiscalBooks\FiscalBookPeriods;
use App\Support\Administration\FiscalBooks\FiscalBookReport;
use App\Support\SecurityAudit;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga el Libro de Ventas o de Compras de un mes en Excel o PDF.
 *
 * Un mes son unos cientos de filas: se arma al instante (decenas de
 * milisegundos el Excel, menos de un segundo el PDF), sin cola. Exige el panel
 * de Administración y el permiso de menú del libro.
 */
class FiscalBookDownloadController extends Controller
{
    public function __invoke(Request $request, string $book, string $period, string $format): BinaryFileResponse
    {
        $user = $request->user();
        $fiscalBook = FiscalBook::fromSlug($book);

        abort_if($user === null || $fiscalBook === null, 404);
        abort_unless($user->canAccessPanel(Filament::getPanel('administration')), 403);
        abort_unless($fiscalBook === FiscalBook::Sales ? FiscalSalesBookResource::canAccess() : FiscalPurchasesBookResource::canAccess(), 403);

        $month = FiscalBookPeriods::parse($period);
        $directory = storage_path('app/private/fiscal-books/tmp');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.Str::uuid().'.'.$format;

        if ($format === 'xlsx') {
            FiscalBookExcelWriter::write($fiscalBook, $month, $path);
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            FiscalBookPdf::write($fiscalBook, $month, $path);
            $mime = 'application/pdf';
        }

        SecurityAudit::log('AUDIT_FISCAL_BOOK_DOWNLOADED', 'administration.fiscal-books.download', [
            'book' => $fiscalBook->value,
            'period' => $month->toDateString(),
            'format' => $format,
        ], $user);

        return response()
            ->download($path, FiscalBookReport::filename($fiscalBook, $month, $format), ['Content-Type' => $mime])
            ->deleteFileAfterSend();
    }
}
