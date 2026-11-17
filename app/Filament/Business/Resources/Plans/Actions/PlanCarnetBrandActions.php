<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Actions;

use App\Models\Plan;
use App\Models\User;
use App\Support\Affiliations\Certificates\PlanCarnetBrands;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Marca del carnet por plan desde la tabla de planes, con el mismo permiso que
 * el diseño del certificado. Cambia logo, color y pie de los carnets del plan.
 */
final class PlanCarnetBrandActions
{
    public static function canManage(): bool
    {
        return PlanCarnetBrands::available()
            && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::DESIGN_PLAN_CERTIFICATE);
    }

    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::edit(), self::remove()];
    }

    public static function edit(): Action
    {
        return Action::make('carnetBrand')
            ->label(fn (Plan $record): string => PlanCarnetBrands::hasBrand($record) ? 'Marca del carnet (activa)' : 'Marca del carnet')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('info')
            ->visible(fn (): bool => self::canManage())
            ->modalHeading(fn (Plan $record): string => 'Marca del carnet · '.($record->description ?: $record->code))
            ->modalDescription('Opcional. Cambia el logo, el color de los detalles y el nombre del pie en los carnets de los afiliados de este plan, en el PDF del Generador de Certificado y en el carnet digital que abre el QR. Teléfonos, correo y datos del afiliado no cambian. Los certificados ya generados se actualizan al emitirlos de nuevo.')
            ->modalIcon(Heroicon::OutlinedIdentification)
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(function (Plan $record): array {
                $brand = PlanCarnetBrands::recordFor((int) $record->getKey());

                return [
                    'logo_path' => $brand?->logo_path,
                    'color' => $brand?->color ?? PlanCarnetBrands::DEFAULT_COLOR,
                    'company_label' => $brand?->company_label,
                ];
            })
            ->schema([
                FileUpload::make('logo_path')
                    ->label('Logo del carnet')
                    ->helperText('Reemplaza al logo de Tu Doctor en Casa en el frente del carnet. PNG o JPG, máximo 5 MB; mejor recortado al borde y con fondo transparente.')
                    ->image()
                    ->acceptedFileTypes(PlanCarnetBrands::ACCEPTED_TYPES)
                    ->maxSize(PlanCarnetBrands::MAX_KILOBYTES)
                    ->disk('public')
                    ->directory(PlanCarnetBrands::DIRECTORY)
                    ->visibility('public')
                    ->imagePreviewHeight('80')
                    ->required(),
                ColorPicker::make('color')
                    ->label('Color de los detalles')
                    ->helperText('Reemplaza el azul: etiqueta del plan, textos de apoyo, «Humanizamos la salud» y los círculos del reverso.')
                    ->hex()
                    ->required(),
                TextInput::make('company_label')
                    ->label('Nombre en el pie del reverso')
                    ->placeholder('RENACER ECOFUNERAL')
                    ->helperText('Reemplaza «Tu Doctor en Casa, C.A. · RIF J-50358368-1».')
                    ->maxLength(PlanCarnetBrands::LABEL_MAX_LENGTH)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Guardar marca')
            ->action(function (array $data, Plan $record, Action $action): void {
                abort_unless(self::canManage(), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                try {
                    PlanCarnetBrands::save($record, $data, $user);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('La marca no se guardó')
                        ->body(collect($exception->errors())->flatten()->implode(' '))
                        ->danger()
                        ->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Marca del carnet guardada')
                    ->body('Los carnets que se emitan desde ahora para este plan salen con esta marca.')
                    ->success()
                    ->send();
            });
    }

    public static function remove(): Action
    {
        return Action::make('carnetBrandRemove')
            ->label('Quitar marca del carnet')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Plan $record): bool => self::canManage() && PlanCarnetBrands::hasBrand($record))
            ->requiresConfirmation()
            ->modalHeading('Quitar la marca del carnet')
            ->modalDescription('Los carnets de este plan volverán al diseño de Tu Doctor en Casa la próxima vez que se emitan. Los certificados ya generados no cambian.')
            ->modalSubmitActionLabel('Quitar marca')
            ->action(function (Plan $record): void {
                abort_unless(self::canManage(), 403);

                PlanCarnetBrands::remove($record);

                Notification::make()->title('Marca del carnet quitada')->success()->send();
            });
    }
}
