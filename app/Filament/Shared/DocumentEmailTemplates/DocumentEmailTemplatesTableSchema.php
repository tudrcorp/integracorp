<?php

declare(strict_types=1);

namespace App\Filament\Shared\DocumentEmailTemplates;

use App\Enums\DocumentEmailAudience;
use App\Enums\DocumentEmailType;
use App\Models\DocumentEmailTemplate;
use App\Support\DocumentEmails\DocumentEmailTemplateAccess;
use App\Support\DocumentEmails\DocumentEmailTemplateRenderer;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

/**
 * Tabla de copys de un documento. Ver y la vista previa: quien entra a la pantalla. Editar,
 * activar, desactivar y marcar predeterminado: el permiso de acción de ese documento.
 */
final class DocumentEmailTemplatesTableSchema
{
    public static function configure(Table $table, DocumentEmailType $document): Table
    {
        $canManage = fn (): bool => DocumentEmailTemplateAccess::canManage($document);
        $label = mb_strtolower($document->label());

        return $table
            ->heading('Copys de '.($document === DocumentEmailType::ReceiptPayment ? 'recibos de pago' : 'avisos de cobro'))
            ->description('Textos que el analista elige para el correo del afiliado y del agente / agencia al enviar el '.$label.'. No se eliminan: se desactivan.')
            ->emptyStateHeading('No hay copys registrados')
            ->emptyStateDescription('Agregue un copy para poder enviar el '.$label.' por correo.')
            ->emptyStateIcon('heroicon-o-envelope-open')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (DocumentEmailTemplate $record): string => $record->subject),
                TextColumn::make('audience')
                    ->label('Dirigido a')
                    ->badge()
                    ->formatStateUsing(fn (DocumentEmailAudience $state): string => $state->label())
                    ->color(fn (DocumentEmailAudience $state): string => $state->color()),
                IconColumn::make('is_default')
                    ->label('Predeterminado')
                    ->boolean()
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Última edición por')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('audience')
                    ->label('Dirigido a')
                    ->options(DocumentEmailAudience::options()),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->placeholder('Todos'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver ficha')
                        ->icon('heroicon-o-document-text')
                        ->color('gray'),
                    Action::make('preview')
                        ->label('Vista previa')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading(fn (DocumentEmailTemplate $record): string => 'Vista previa · '.$record->name)
                        ->modalDescription('Así se ve el correo con datos de ejemplo. En cada envío se usan los datos reales del documento.')
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalContent(fn (DocumentEmailTemplate $record) => View::make(
                            'filament.administration.document-emails.copy-preview',
                            [
                                ...DocumentEmailTemplateRenderer::render($record, $document->sampleValues()),
                                'documentLabel' => $label,
                            ],
                        ))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar'),
                    EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-m-pencil-square')
                        ->color('primary'),
                    Action::make('make_default')
                        ->label('Marcar como predeterminado')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->visible(fn (DocumentEmailTemplate $record): bool => $canManage() && $record->is_active && ! $record->is_default)
                        ->requiresConfirmation()
                        ->modalHeading('Marcar como predeterminado')
                        ->modalDescription(fn (DocumentEmailTemplate $record): string => 'Este copy quedará preseleccionado para «'.$record->audience->label().'» y el predeterminado anterior dejará de serlo.')
                        ->action(function (DocumentEmailTemplate $record) use ($document): void {
                            self::update($document, $record, ['is_default' => true], 'DEFAULT_SET');

                            Notification::make()->title('Copy predeterminado actualizado')->success()->send();
                        }),
                    Action::make('deactivate')
                        ->label('Desactivar')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (DocumentEmailTemplate $record): bool => $canManage() && $record->is_active)
                        ->requiresConfirmation()
                        ->modalHeading('Desactivar copy')
                        ->modalDescription('El copy dejará de aparecer al enviar. Los envíos anteriores no cambian y puede reactivarlo cuando quiera.')
                        ->modalSubmitActionLabel('Sí, desactivar')
                        ->action(function (DocumentEmailTemplate $record) use ($document): void {
                            self::update($document, $record, ['is_active' => false], 'DEACTIVATED');

                            Notification::make()->title('Copy desactivado')->success()->send();
                        }),
                    Action::make('activate')
                        ->label('Activar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (DocumentEmailTemplate $record): bool => $canManage() && ! $record->is_active)
                        ->requiresConfirmation()
                        ->modalHeading('Activar copy')
                        ->modalDescription('El copy volverá a estar disponible al enviar.')
                        ->action(function (DocumentEmailTemplate $record) use ($document): void {
                            self::update($document, $record, ['is_active' => true], 'ACTIVATED');

                            Notification::make()->title('Copy activado')->success()->send();
                        }),
                ])
                    ->label('Más')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->striped();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function update(DocumentEmailType $document, DocumentEmailTemplate $record, array $attributes, string $event): void
    {
        abort_unless(DocumentEmailTemplateAccess::canManage($document) && $record->document === $document, 403);

        $record->fill([...$attributes, 'updated_by' => Auth::user()?->name])->save();

        SecurityAudit::log('AUDIT_ADMIN_DOCUMENT_EMAIL_COPY_'.$event, 'administration.document-email-templates', [
            'panel' => 'administration',
            'document' => $document->value,
            'template_id' => $record->getKey(),
            'template_name' => $record->name,
            'changes' => $attributes,
        ]);
    }
}
