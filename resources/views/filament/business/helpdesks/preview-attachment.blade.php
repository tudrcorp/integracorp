@include('filament.business.helpdesks.file-preview-card', [
    'url' => $url,
    'extension' => $extension,
    'missing' => $missing,
    'storedPath' => $record->image ?? '',
    'basename' => basename((string) ($record->image ?? '')),
])
