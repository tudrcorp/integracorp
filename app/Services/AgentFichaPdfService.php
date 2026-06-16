<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentNoteBlog;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;

class AgentFichaPdfService
{
    /**
     * @return list<string>
     */
    private static function logoCandidatePaths(): array
    {
        return [
            public_path('storage/administracion/logoNewPdfTDEC.png'),
            public_path('storage/logo1-pdf.png'),
            public_path('image/logoNewPdf.png'),
        ];
    }

    public static function logoDataUri(): string
    {
        foreach (self::logoCandidatePaths() as $path) {
            if (is_file($path)) {
                return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        return '';
    }

    public static function notesForAgent(int $agentId): Collection
    {
        return AgentNoteBlog::query()
            ->where('agent_id', $agentId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public static function make(Agent $agent): PdfDocument
    {
        $agent->loadMissing(['agency', 'country', 'state', 'city', 'typeAgent']);

        $notes = self::notesForAgent((int) $agent->getKey());

        return Pdf::loadView('documents.agent-ficha-detalle', [
            'agent' => $agent,
            'notes' => $notes,
            'logoDataUri' => self::logoDataUri(),
            'generatedAt' => now()->timezone(config('app.timezone')),
        ])->setPaper('a4', 'portrait');
    }

    public static function outputBinary(Agent $agent): string
    {
        return (string) self::make($agent)->output();
    }

    public static function filename(Agent $agent): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', 'AGT-000'.$agent->getKey()) ?: 'agente';

        return 'ficha-agente-'.$safe.'.pdf';
    }

    public static function codeLabel(Agent $agent): string
    {
        $code = (string) ($agent->code_agent ?? 'AGT-000'.$agent->getKey());
        $def = $agent->relationLoaded('typeAgent') ? $agent->typeAgent?->definition : null;

        return filled($def) ? $def.' — '.$code : $code;
    }

    public static function whatsappStorageRelativePath(Agent $agent): string
    {
        return 'business-fichas/agents/'.self::filename($agent);
    }

    public static function persistForWhatsApp(Agent $agent): string
    {
        $relativePath = self::whatsappStorageRelativePath($agent);
        $absolutePath = public_path('storage/'.$relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, self::outputBinary($agent));

        return $relativePath;
    }

    public static function whatsappCaption(Agent $agent): string
    {
        $codeLabel = self::codeLabel($agent);
        $displayName = (string) ($agent->name ?? 'Agente');

        return <<<TEXT
        📎 *Ficha de agente*

        Agente: *{$displayName}*
        Código: *{$codeLabel}*

        Documento generado por Integracorp · Tu Dr en Casa.
        TEXT;
    }
}
