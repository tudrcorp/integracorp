<?php

declare(strict_types=1);

use App\Http\Controllers\HelpdeskAttachmentDownloadController;
use App\Models\HelpDesk;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('fuerza descarga (attachment) de adjuntos helpdesk', function (): void {
    $user = User::factory()->create();

    $path = 'helpdesks-documents/test-download.txt';
    Storage::disk('public')->put($path, 'hello');

    $ticket = HelpDesk::query()->create([
        'description' => 'Ticket test',
        'image' => $path,
        'priority' => 'MEDIA',
        'status' => 'PENDIENTE POR INICIAR',
        'created_by' => $user->name,
    ]);

    $this->actingAs($user);

    $controller = app(HelpdeskAttachmentDownloadController::class);
    $request = Request::create('/helpdesks/'.$ticket->getKey().'/attachments/0/download', 'GET');

    $response = $controller($request, $ticket, 0);

    expect((string) $response->headers->get('content-disposition'))->toContain('attachment');
});
