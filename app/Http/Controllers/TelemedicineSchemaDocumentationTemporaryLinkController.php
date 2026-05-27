<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class TelemedicineSchemaDocumentationTemporaryLinkController extends Controller
{
    public function __invoke(): View
    {
        $expiresAt = Carbon::now()->addHours(12);

        return view('public.telemedicine-schema-documentation-link', [
            'temporaryUrl' => URL::temporarySignedRoute(
                'telemedicine.schema.documentation',
                $expiresAt,
            ),
            'expiresAt' => $expiresAt,
        ]);
    }
}
