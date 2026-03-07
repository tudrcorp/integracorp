<?php

namespace App\Filament\AvatarProviders;

use App\Models\RrhhColaborador;
use Filament\AvatarProviders\Contracts;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class BoringAvatarsProvider implements Contracts\AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        if ($record instanceof RrhhColaborador && filled($record->avatar)) {
            return asset('storage/'.$record->avatar);
        }

        $userId = $record instanceof Authenticatable
            ? $record->getAuthIdentifier()
            : ($record->user_id ?? null);

        if ($userId !== null) {
            $colaborador = RrhhColaborador::query()
                ->where('user_id', $userId)
                ->first();

            if ($colaborador && filled($colaborador->avatar)) {
                return asset('storage/'.$colaborador->avatar);
            }
        }

        $name = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
            ->join(' ');

        return 'https://ui-avatars.com/api/?background=0D8ABC&color=ffffff&name='.urlencode($name);
    }
}
