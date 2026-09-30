<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\Helpdesks\Schemas;

use App\Support\HelpdeskFormSchema;
use Filament\Schemas\Schema;

final class HelpdeskForm
{
    public static function configure(Schema $schema): Schema
    {
        return HelpdeskFormSchema::configure(
            $schema,
            assigneesRequired: true,
            scrumProductOwnerInbox: false,
            assigneeDepartments: HelpdeskFormSchema::TELEMEDICINE_ASSIGNEE_DEPARTMENTS,
        );
    }
}
