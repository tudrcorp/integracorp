<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Helpdesks\Schemas;

use App\Support\HelpdeskInfolistSchema;
use Filament\Schemas\Schema;

final class HelpdeskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return HelpdeskInfolistSchema::configure($schema);
    }
}
