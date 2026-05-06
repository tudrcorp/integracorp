<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\HelpdeskDocumentPaths;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

final class HelpdeskAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, HelpDesk $helpDesk, int $index)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (! $this->userCanDownloadAttachment($user, $helpDesk)) {
            abort(403);
        }

        $paths = HelpdeskDocumentPaths::paths($helpDesk);
        $path = $paths[$index] ?? null;
        if (! is_string($path) || trim($path) === '') {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        $filename = basename($path);
        $absolutePath = $disk->path($path);

        return response()->download($absolutePath, $filename);
    }

    private function userCanDownloadAttachment(User $user, HelpDesk $helpDesk): bool
    {
        if ($this->userIsSuperAdmin($user)) {
            return true;
        }

        $creator = trim((string) $helpDesk->created_by);
        if ($creator !== '' && trim((string) $user->name) === $creator) {
            return true;
        }

        $colaboradorId = RrhhColaborador::query()
            ->where('user_id', (int) $user->getKey())
            ->value('id');

        if ($colaboradorId !== null) {
            $colaboradorId = (int) $colaboradorId;

            if ($helpDesk->rrhhColaboradores()->whereKey($colaboradorId)->exists()) {
                return true;
            }

            $cc = $helpDesk->cc_colaboradores;
            if (is_array($cc) && in_array($colaboradorId, $cc, true)) {
                return true;
            }
        }

        return false;
    }

    private function userIsSuperAdmin(User $user): bool
    {
        $raw = $user->departament ?? [];
        $departments = is_array($raw) ? $raw : [(string) $raw];

        foreach ($departments as $department) {
            $normalized = strtoupper(str_replace([' ', '-', '_'], '', (string) $department));
            if ($normalized !== '' && str_contains($normalized, 'SUPERADMIN')) {
                return true;
            }
        }

        return false;
    }
}
