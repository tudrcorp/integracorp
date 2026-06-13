<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TelemedicineCaseMessage;
use App\Models\User;
use App\Support\Operations\CaseFollowUpChatManager;

class TelemedicineCaseMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TelemedicineCaseMessage $telemedicineCaseMessage): bool
    {
        $case = $telemedicineCaseMessage->telemedicineCase;

        return $case !== null && CaseFollowUpChatManager::canAccessCase($user, $case);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TelemedicineCaseMessage $telemedicineCaseMessage): bool
    {
        return $user->id === $telemedicineCaseMessage->user_id;
    }

    public function delete(User $user, TelemedicineCaseMessage $telemedicineCaseMessage): bool
    {
        return $user->id === $telemedicineCaseMessage->user_id;
    }
}
