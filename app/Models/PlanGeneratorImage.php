<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PlanGeneratorImage extends Model
{
    protected $fillable = [
        'name',
        'image_path',
        'created_by',
    ];

    public function publicUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
