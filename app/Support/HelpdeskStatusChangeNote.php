<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Forms\Components\RichEditor;

final class HelpdeskStatusChangeNote
{
    public const MIN_PLAIN_TEXT_LENGTH = 3;

    /**
     * @return array{title: string, body: string}|null
     */
    public static function validateAssigneeExplanation(?string $html): ?array
    {
        $plainLength = self::plainTextLength($html);

        if ($plainLength < self::MIN_PLAIN_TEXT_LENGTH) {
            return [
                'title' => 'Explicación requerida',
                'body' => 'Como analista asignado, debe añadir una nota con el motivo del cambio de estado (mínimo '.self::MIN_PLAIN_TEXT_LENGTH.' caracteres).',
            ];
        }

        return null;
    }

    public static function buildObservationHtml(
        string $previousStatus,
        string $newStatus,
        ?string $assigneeExplanationHtml,
        bool $includeAssigneeExplanation,
    ): string {
        $statusBlock = '<p>Estado del ticket actualizado de <strong>'.e($previousStatus).'</strong> a <strong>'.e($newStatus).'</strong>.</p>';

        if (! $includeAssigneeExplanation) {
            return $statusBlock;
        }

        $explanation = HelpdeskNoteHtmlSanitizer::sanitize(trim((string) $assigneeExplanationHtml));

        if ($explanation === '' || self::plainTextLength($explanation) < self::MIN_PLAIN_TEXT_LENGTH) {
            return $statusBlock;
        }

        return $statusBlock
            .'<p><strong>Motivo del cambio (analista asignado):</strong></p>'
            .$explanation;
    }

    /**
     * @return array<int, RichEditor>
     */
    public static function assigneeExplanationEditor(): array
    {
        return [
            RichEditor::make('status_explanation')
                ->label('Nota del cambio de estado')
                ->placeholder('Indique las razones, avances, acuerdos o explicaciones pertinentes al nuevo estado…')
                ->helperText('Obligatorio para el analista asignado al ticket.')
                ->required()
                ->fileAttachments(false)
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'highlight', 'textColor'],
                    ['h2', 'h3'],
                    ['bulletList', 'orderedList', 'blockquote'],
                    ['link'],
                    ['undo', 'redo'],
                ])
                ->extraInputAttributes([
                    'class' => 'min-h-[10rem]',
                ])
                ->columnSpanFull(),
        ];
    }

    public static function plainTextLength(?string $html): int
    {
        return mb_strlen(trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
