<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HelpdeskVideoTutorialFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class HelpdeskVideoTutorialFileController extends Controller
{
    public function download(HelpdeskVideoTutorialFile $helpdeskVideoTutorialFile): BinaryFileResponse
    {
        $path = (string) $helpdeskVideoTutorialFile->file_path;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        $filename = (string) ($helpdeskVideoTutorialFile->original_name ?: basename($path));

        return response()->download($disk->path($path), $filename);
    }
}
