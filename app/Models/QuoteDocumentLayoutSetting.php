<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Composición del PDF de la Propuesta Económica por ámbito de cotización.
 */
class QuoteDocumentLayoutSetting extends Model
{
    protected $table = 'quote_document_layout_settings';

    protected $fillable = [
        'scope',
        'total_pages',
        'calculations_page',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_pages' => 'integer',
            'calculations_page' => 'integer',
        ];
    }
}
