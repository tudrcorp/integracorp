<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Tables\Columns\TextColumn;

final class HelpdeskTableTeamColumns
{
    /**
     * @return array<int, TextColumn>
     */
    public static function make(): array
    {
        return [
            TextColumn::make('team')
                ->label('Equipo')
                ->icon('heroicon-m-user-group')
                ->searchable()
                ->sortable()
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('team_members')
                ->label('Integrantes del equipo')
                ->icon('heroicon-m-users')
                ->formatStateUsing(function (mixed $state): string {
                    $members = HelpdeskTeamMembersState::normalize($state);

                    if ($members === []) {
                        return '—';
                    }

                    return collect($members)
                        ->map(static function (mixed $member): string {
                            if (! is_array($member)) {
                                return '';
                            }

                            $name = trim((string) ($member['name'] ?? ''));
                            $phone = trim((string) ($member['telefono_corporativo'] ?? ''));

                            if ($name === '') {
                                return '';
                            }

                            return $phone !== '' ? "{$name} ({$phone})" : $name;
                        })
                        ->filter()
                        ->implode(', ');
                })
                ->wrap()
                ->toggleable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
