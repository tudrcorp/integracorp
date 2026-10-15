<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un archivo HTML subido para una página alojada, con la revisión que se le hizo.
 */
class QrHostedPageVersion extends Model
{
    protected $fillable = [
        'qr_hosted_page_id',
        'version',
        'file_path',
        'original_name',
        'size_bytes',
        'sha256',
        'review',
        'created_by',
        'created_by_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size_bytes' => 'integer',
            'review' => 'array',
        ];
    }

    /**
     * @return BelongsTo<QrHostedPage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(QrHostedPage::class, 'qr_hosted_page_id');
    }
}
