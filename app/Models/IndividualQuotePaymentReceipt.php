<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IndividualQuotePaymentReceipt extends Model
{
    protected $fillable = [
        'individual_quote_id',
        'storefront_user_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'channel',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'individual_quote_id' => 'integer',
            'storefront_user_id' => 'integer',
            'size' => 'integer',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(IndividualQuote::class, 'individual_quote_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'storefront_user_id');
    }

    public function absolutePath(): ?string
    {
        $path = (string) $this->path;

        if ($path === '') {
            return null;
        }

        $absolute = Storage::disk((string) ($this->disk ?: 'public'))->path($path);

        return is_file($absolute) ? $absolute : null;
    }

    public function publicUrl(): ?string
    {
        $path = (string) $this->path;

        if ($path === '') {
            return null;
        }

        try {
            return Storage::disk((string) ($this->disk ?: 'public'))->url($path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isImage(): bool
    {
        $mime = strtolower((string) $this->mime);
        $extension = strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION));

        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'gif'], true);
    }
}
