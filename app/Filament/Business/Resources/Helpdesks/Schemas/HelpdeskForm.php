<?php

namespace App\Filament\Business\Resources\Helpdesks\Schemas;

use App\Support\HelpdeskFormSchema;
use Filament\Schemas\Schema;

class HelpdeskForm
{
    public static function configure(Schema $schema): Schema
    {
        return HelpdeskFormSchema::configure($schema, assigneesRequired: false);
    }

    /**
     * @return array<int, string>
     */
    public static function rrhhColaboradorOptionsForHelpdeskMultiselect(): array
    {
        return HelpdeskFormSchema::rrhhColaboradorOptionsForHelpdeskMultiselect();
    }
}
