<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Actions\Action;
use Illuminate\Support\Js;
use Livewire\Component;

final class CsvExportDownloadTrigger
{
    public static function dispatch(Component $livewire, string $url): void
    {
        $livewire->js(
            '(function(){var frame=document.createElement("iframe");frame.style.display="none";frame.src='
            .Js::from($url)
            .';document.body.appendChild(frame);window.setTimeout(function(){frame.remove()},120000)})()',
        );
    }

    public static function fromAction(Action $action, string $url): void
    {
        self::dispatch($action->getLivewire(), $url);
    }
}
