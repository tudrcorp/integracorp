@php
    use App\Filament\Business\Resources\PlanGeneratorImages\PlanGeneratorImageResource;

    $galleryUrl = PlanGeneratorImageResource::getUrl('index');
    $images = $this->quotationGalleryImages;
    $activePageNumber = $this->quotationGalleryPickerPageNumber;
@endphp

@if ($activePageNumber !== null)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
        wire:key="plan-generator-quotation-gallery-modal"
    >
        <div
            class="absolute inset-0 bg-slate-950/60 backdrop-blur-[2px]"
            wire:click="closeQuotationGalleryPicker"
        ></div>

        <div class="relative z-10 flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200/80 px-5 py-4 dark:border-white/10">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                        Galería de imágenes
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Seleccione una imagen para la página {{ $activePageNumber }}.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="closeQuotationGalleryPicker"
                    class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                    aria-label="Cerrar galería"
                >
                    <x-filament::icon icon="heroicon-m-x-mark" class="size-5" />
                </button>
            </div>

            <div class="overflow-y-auto px-5 py-4">
                @if ($images->isEmpty())
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center dark:border-white/15 dark:bg-white/5">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            Aún no hay imágenes en la galería.
                        </p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Cargue una imagen en cualquier página o administre la galería desde el menú lateral.
                        </p>
                        <x-filament::link
                            :href="$galleryUrl"
                            target="_blank"
                            class="mt-4 inline-flex"
                            icon="heroicon-m-arrow-top-right-on-square"
                        >
                            Abrir galería de imágenes
                        </x-filament::link>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($images as $image)
                            <button
                                type="button"
                                wire:key="pg-gallery-image-{{ $image->id }}"
                                wire:click="selectQuotationGalleryImage({{ $image->id }})"
                                class="group overflow-hidden rounded-xl border border-slate-200/90 bg-white text-left shadow-sm transition hover:border-blue-400 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-white/10 dark:bg-slate-950/40 dark:hover:border-blue-400/70"
                            >
                                <div class="aspect-[4/3] overflow-hidden bg-slate-100 dark:bg-white/5">
                                    <img
                                        src="{{ $image->publicUrl() }}"
                                        alt="{{ $image->name }}"
                                        class="size-full object-cover transition group-hover:scale-[1.02]"
                                        loading="lazy"
                                    />
                                </div>
                                <div class="space-y-0.5 px-3 py-2">
                                    <p class="truncate text-xs font-semibold text-slate-800 dark:text-slate-100">
                                        {{ $image->name }}
                                    </p>
                                    @if (filled($image->created_by))
                                        <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">
                                            {{ $image->created_by }}
                                        </p>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-slate-200/80 px-5 py-3 dark:border-white/10">
                <x-filament::link
                    :href="$galleryUrl"
                    target="_blank"
                    icon="heroicon-m-arrow-top-right-on-square"
                    size="sm"
                >
                    Administrar galería
                </x-filament::link>
                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    wire:click="closeQuotationGalleryPicker"
                >
                    Cancelar
                </x-filament::button>
            </div>
        </div>
    </div>
@endif
