<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\Affiliation;
use App\Models\AffiliationFamilyMerge;
use App\Models\User;
use App\Support\Affiliations\FamilyMergeRollback;
use App\Support\Filament\UserNavigationAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * Punto de restauración visible de la unificación de grupo familiar: devuelve cada
 * persona, cuota, renovación y paciente a la foto previa, o no toca nada.
 */
final class RevertFamilyMergeAction
{
    /**
     * @var array<int, AffiliationFamilyMerge|false>
     */
    private static array $mergeByAffiliation = [];

    public static function make(): Action
    {
        return Action::make('revertFamilyMerge')
            ->label('Revertir unificación')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->modalHeading('Revertir unificación de grupo familiar')
            ->modalDescription('Cada persona vuelve a su póliza original con su tarifa, parentesco y cuotas previas. Es todo o nada: si algo cambió después de unificar, no se toca ninguna fila.')
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalSubmitActionLabel('Revertir ahora')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth(Width::FourExtraLarge)
            ->visible(function (?Affiliation $record): bool {
                $user = Auth::user();

                return $user !== null
                    && UserNavigationAccess::isSuperAdmin($user)
                    && $record instanceof Affiliation
                    && self::mergeFor($record) instanceof AffiliationFamilyMerge;
            })
            ->authorize(function (): bool {
                $user = Auth::user();

                return $user !== null && UserNavigationAccess::isSuperAdmin($user);
            })
            ->schema([
                Placeholder::make('rollback_preview')
                    ->hiddenLabel()
                    ->content(function (Affiliation $record): HtmlString {
                        $merge = self::mergeFor($record);

                        if (! $merge instanceof AffiliationFamilyMerge) {
                            return new HtmlString('<p class="text-sm">Esta póliza no tiene una unificación aplicada que revertir.</p>');
                        }

                        try {
                            return self::previewHtml($merge);
                        } catch (Throwable $exception) {
                            report($exception);

                            return new HtmlString('<p class="text-sm text-red-700 dark:text-red-300">No se pudo revisar la unificación: '.e($exception->getMessage()).'</p>');
                        }
                    })
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label('Motivo de la reversión')
                    ->required()
                    ->minLength(FamilyMergeRollback::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3)
                    ->placeholder('Ejemplo: se unificó la póliza equivocada; el grupo debe seguir en dos pólizas separadas.')
                    ->columnSpanFull(),
                Checkbox::make('confirmed')
                    ->label('Revisé el resumen. Entiendo que las pólizas, personas, cuotas y renovaciones vuelven a como estaban antes de unificar.')
                    ->accepted()
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (Affiliation $record, array $data): void {
                $merge = self::mergeFor($record);
                $user = Auth::user();

                if (! $merge instanceof AffiliationFamilyMerge || ! $user instanceof User) {
                    Notification::make()
                        ->title('No hay nada que revertir')
                        ->body('Esta póliza ya no tiene una unificación aplicada.')
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    $summary = FamilyMergeRollback::execute($merge, $user, (string) ($data['note'] ?? ''));
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('No se revirtió la unificación')
                        ->body($exception->getMessage().' No se cambió ningún dato.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('No se revirtió la unificación')
                        ->body('Ocurrió un error inesperado y se deshizo todo: no se cambió ningún dato. Intente de nuevo o avise a Sistemas.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                self::$mergeByAffiliation = [];

                Notification::make()
                    ->title('Unificación revertida')
                    ->body('Volvieron a su estado previo: '.implode(', ', $summary['affiliations'])
                        .'. Las propuestas de renovación ya se recalcularon; certificados y carnets se regeneran en cola.')
                    ->success()
                    ->persistent()
                    ->send();

                $record->refresh();
                $record->load(['affiliates', 'affiliationObservations', 'status_log_affiliations']);
            });
    }

    private static function mergeFor(Affiliation $record): ?AffiliationFamilyMerge
    {
        $id = (int) $record->id;

        if (! array_key_exists($id, self::$mergeByAffiliation)) {
            self::$mergeByAffiliation[$id] = FamilyMergeRollback::latestAppliedFor($record) ?? false;
        }

        return self::$mergeByAffiliation[$id] ?: null;
    }

    private static function previewHtml(AffiliationFamilyMerge $merge): HtmlString
    {
        $blockers = FamilyMergeRollback::blockers($merge);
        $warnings = FamilyMergeRollback::warnings($merge);
        $members = FamilyMergeRollback::restoredMembers($merge);

        $html = '<div class="space-y-3 text-sm">';
        $html .= '<div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">'
            .'<p><strong>Unificación del '.e($merge->merged_at?->format('d/m/Y H:i') ?? '').'</strong> por '.e((string) $merge->merged_by_name).'</p>'
            .'<p>Destino: <strong>'.e((string) $merge->target_code).'</strong> · Origen (volverán a su estado previo): <strong>'.e(implode(', ', (array) $merge->source_codes)).'</strong></p>'
            .'<p class="text-gray-600 dark:text-gray-400">Motivo original: '.e((string) $merge->reason).'</p>'
            .'</div>';

        if ($blockers !== []) {
            $html .= '<div class="rounded-lg border border-red-300 bg-red-50 p-3 text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">'
                .'<p class="font-semibold">No se puede revertir todavía:</p><ul class="list-disc ps-5">';

            foreach ($blockers as $blocker) {
                $html .= '<li>'.nl2br(e($blocker)).'</li>';
            }

            $html .= '</ul></div>';
        } else {
            $html .= '<p class="font-semibold text-green-700 dark:text-green-300">Nada cambió desde la unificación: se puede revertir de forma segura.</p>';
        }

        if ($members !== []) {
            $html .= '<table class="w-full text-left"><thead><tr class="border-b border-gray-200 dark:border-white/10">'
                .'<th class="px-2 py-1">Persona</th><th class="px-2 py-1">Cédula</th><th class="px-2 py-1">Hoy en</th><th class="px-2 py-1">Vuelve a</th><th class="px-2 py-1">Parentesco previo</th>'
                .'</tr></thead><tbody>';

            foreach ($members as $member) {
                $html .= '<tr class="border-b border-gray-100 dark:border-white/5">'
                    .'<td class="px-2 py-1">'.e($member['name']).'</td>'
                    .'<td class="px-2 py-1">'.e($member['identification']).'</td>'
                    .'<td class="px-2 py-1">'.e($member['from']).'</td>'
                    .'<td class="px-2 py-1"><strong>'.e($member['back_to']).'</strong></td>'
                    .'<td class="px-2 py-1">'.e($member['relationship']).'</td>'
                    .'</tr>';
            }

            $html .= '</tbody></table>';
        }

        if ($warnings !== []) {
            $html .= '<ul class="list-disc ps-5 text-amber-700 dark:text-amber-300">';

            foreach ($warnings as $warning) {
                $html .= '<li>'.e($warning).'</li>';
            }

            $html .= '</ul>';
        }

        return new HtmlString($html.'</div>');
    }
}
