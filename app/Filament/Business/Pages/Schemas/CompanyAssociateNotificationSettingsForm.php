<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages\Schemas;

use Filament\Schemas\Schema;

/**
 * @deprecated Use SystemNotificationRecipientSettingsForm.
 */
final class CompanyAssociateNotificationSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return SystemNotificationRecipientSettingsForm::configure($schema);
    }
}
