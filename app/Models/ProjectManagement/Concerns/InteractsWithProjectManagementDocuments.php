<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement\Concerns;

use App\Models\ProjectManagement\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait InteractsWithProjectManagementDocuments
{
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
