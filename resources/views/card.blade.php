@php
    /** @var \Mediator\Models\Media $file */
    $type = (string) $file->type;
    $sign = match (true) {
        str_starts_with($type, 'video/') => 'heroicon-o-film',
        str_starts_with($type, 'audio/') => 'heroicon-o-musical-note',
        default => 'heroicon-o-document',
    };
@endphp

<div
    class="media-card @if ($open) media-card--open @endif"
    data-file="{{ $file->id }}"
    wire:key="file-{{ $file->id }}"
    wire:click="{{ $deed }}({{ $file->id }})"
    role="button"
    tabindex="0"
>
    <div class="media-card__frame">
        @if (str_starts_with($type, 'image/'))
            <img
                src="{{ $file->thumbnailUrl }}"
                alt="{{ $file->alt }}"
                loading="lazy"
                class="media-card__image"
                x-data="{ ready: false }"
                x-init="ready = $el.complete"
                x-on:load="ready = true"
                x-bind:class="ready && 'media-card__image--ready'"
            >
        @else
            @svg($sign, 'media-card__sign')
        @endif
    </div>

    <label class="media-card__tick" wire:click.stop>
        <input type="checkbox" class="media-card__box" wire:click.stop="toggle({{ $file->id }})" @checked($ticked)>
        @svg('heroicon-m-check', 'media-card__check')
    </label>

    <div class="media-card__text">
        <p class="media-card__title" title="{{ $file->title ?? $file->name }}">{{ $file->title ?? $file->name }}</p>

        <p class="media-card__facts">
            @if ($file->width && $file->height)
                {{ $file->width }}×{{ $file->height }}<span aria-hidden="true"> · </span>
            @endif

            {{ \Illuminate\Support\Number::fileSize((int) $file->size, precision: 1) }}
        </p>
    </div>
</div>
