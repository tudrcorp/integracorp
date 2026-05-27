<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Telemedicine\TelemedicineSchemaDocumentation;
use Illuminate\Contracts\View\View;

class TelemedicineSchemaDocumentationController extends Controller
{
    public function __invoke(): View
    {
        return view('public.telemedicine-schema-documentation', [
            'version' => TelemedicineSchemaDocumentation::VERSION,
            'updatedAt' => TelemedicineSchemaDocumentation::UPDATED_AT,
            'tables' => TelemedicineSchemaDocumentation::tables(),
            'relationships' => TelemedicineSchemaDocumentation::relationships(),
            'mermaidErDiagram' => TelemedicineSchemaDocumentation::mermaidErDiagram(),
        ]);
    }
}
