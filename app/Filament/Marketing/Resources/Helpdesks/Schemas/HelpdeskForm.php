<?php

namespace App\Filament\Marketing\Resources\Helpdesks\Schemas;

use App\Support\HelpdeskFormSchema;
use Filament\Schemas\Schema;

class HelpdeskForm
{
    public static function configure(Schema $schema): Schema
    {
        return HelpdeskFormSchema::configure($schema, assigneesRequired: true);
    }

    /**
     * @return array<int, string>
     */
    public static function rrhhColaboradorOptionsForHelpdeskMultiselect(): array
    {
        return HelpdeskFormSchema::rrhhColaboradorOptionsForHelpdeskMultiselect();
    }
}
