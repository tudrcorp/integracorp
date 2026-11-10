<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentEmailAudience;
use App\Enums\DocumentEmailType;
use App\Support\DocumentEmails\DocumentEmailTemplateRenderer;
use Database\Factories\DocumentEmailTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Copy del correo con el que se envía un documento (recibo de pago, aviso de cobro).
 *
 * No se elimina: se desactiva. Solo hay un predeterminado por documento y audiencia; al marcar
 * uno, los demás de ese documento y audiencia dejan de serlo.
 *
 * @property int $id
 * @property DocumentEmailType $document
 * @property string $name
 * @property DocumentEmailAudience $audience
 * @property string $subject
 * @property string $body
 * @property bool $is_active
 * @property bool $is_default
 * @property int $sort_order
 */
class DocumentEmailTemplate extends Model
{
    /** @use HasFactory<DocumentEmailTemplateFactory> */
    use HasFactory;

    protected $table = 'document_email_templates';

    protected $fillable = [
        'document',
        'name',
        'audience',
        'subject',
        'body',
        'is_active',
        'is_default',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document' => DocumentEmailType::class,
            'audience' => DocumentEmailAudience::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            $template->name = trim((string) $template->name);
            $template->subject = trim((string) $template->subject);
            $template->body = DocumentEmailTemplateRenderer::sanitizeBody((string) $template->body);

            if (! $template->is_active) {
                $template->is_default = false;
            }
        });

        static::saved(function (self $template): void {
            if (! $template->is_default) {
                return;
            }

            static::query()
                ->whereKeyNot($template->getKey())
                ->where('document', $template->document)
                ->where('audience', $template->audience)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForDocument(Builder $query, DocumentEmailType $document): void
    {
        $query->where('document', $document->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name');
    }

    public function servesRecipient(DocumentEmailType $document, DocumentEmailAudience $recipient): bool
    {
        return $this->is_active
            && $this->document === $document
            && $this->audience->servesRecipient($recipient);
    }
}
