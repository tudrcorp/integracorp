<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Helpdesks\Actions;

use App\Filament\Shared\Helpdesks\Actions\HelpdeskTicketModalActions as SharedHelpdeskTicketModalActions;
use App\Models\HelpDesk;
use Filament\Actions\Action;

final class HelpdeskTicketModalActions
{
    public const IOS_SECTION_CLASS = SharedHelpdeskTicketModalActions::IOS_SECTION_CLASS;

    public const IOS_SUCCESS_BTN = SharedHelpdeskTicketModalActions::IOS_SUCCESS_BTN;

    public const IOS_GRAY_BTN = SharedHelpdeskTicketModalActions::IOS_GRAY_BTN;

    public static function currentUserIsTicketAssignee(HelpDesk $record): bool
    {
        return SharedHelpdeskTicketModalActions::currentUserIsTicketAssignee($record);
    }

    public static function shouldHideAddNoteAction(HelpDesk $record): bool
    {
        return SharedHelpdeskTicketModalActions::shouldHideAddNoteAction($record);
    }

    public static function assertMayAddNote(HelpDesk $record): bool
    {
        return SharedHelpdeskTicketModalActions::assertMayAddNote($record);
    }

    public static function makeAddNoteAction(): Action
    {
        return SharedHelpdeskTicketModalActions::makeAddNoteAction('operations');
    }

    public static function makeUpdateStatusAction(): Action
    {
        return SharedHelpdeskTicketModalActions::makeUpdateStatusAction('operations');
    }

    public static function makeUpdatePriorityAction(): Action
    {
        return SharedHelpdeskTicketModalActions::makeUpdatePriorityAction('operations');
    }
}
