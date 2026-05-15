<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HelpdeskFlowProcessFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class HelpdeskFlowProcessFileController extends Controller
{
    public function download(HelpdeskFlowProcessFile $helpdeskFlowProcessFile): BinaryFileResponse
    {
        $path = (string) $helpdeskFlowProcessFile->file_path;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        $filename = (string) ($helpdeskFlowProcessFile->original_name ?: basename($path));

        return response()->download($disk->path($path), $filename);
    }
}
