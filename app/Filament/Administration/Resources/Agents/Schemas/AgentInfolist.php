<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agents\Schemas;

use Filament\Schemas\Schema;

class AgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return \App\Filament\Business\Resources\Agents\Schemas\AgentInfolist::configure($schema);
    }
}
