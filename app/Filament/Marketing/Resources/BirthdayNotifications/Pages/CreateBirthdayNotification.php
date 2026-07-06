<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Pages;

use App\Filament\Marketing\Resources\BirthdayNotifications\BirthdayNotificationResource;
use App\Filament\Marketing\Resources\BirthdayNotifications\Schemas\BirthdayNotificationForm;
use App\Support\BirthdayNotificationAudience;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;

class CreateBirthdayNotification extends CreateRecord
{
    protected static string $resource = BirthdayNotificationResource::class;

    #[Url]
    public ?string $audience = BirthdayNotificationAudience::AFFILIATES;

    public function mount(): void
    {
        if (! in_array($this->audience, BirthdayNotificationAudience::keys(), true)) {
            $this->audience = BirthdayNotificationAudience::AFFILIATES;
        }

        parent::mount();
    }

    public function form(Schema $schema): Schema
    {
        return BirthdayNotificationForm::configure($schema, $this->audience);
    }
}
