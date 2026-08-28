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
    x-on:keydown.enter.self="$el.click()"
    x-on:keydown.space.self.prevent="$el.click()"
    x-on:focus="roving = $el.dataset.file"
    role="button"
    {{-- The wall is one stop of the keyboard and the arrows walk it from there,
         so the tab of an editor looking for the button under the wall does not
         go through seventy cards to reach it. The number stands written as well
         as bound, because the wall has to be reachable before Alpine wakes. --}}
    tabindex="{{ $loop->first ? 0 : -1 }}"
    x-bind:tabindex="tabbable('{{ $file->id }}')"
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
        <input
            type="checkbox"
            class="media-card__box"
            wire:click.stop="toggle({{ $file->id }})"
            tabindex="{{ $loop->first ? 0 : -1 }}"
            x-bind:tabindex="tabbable('{{ $file->id }}')"
            @checked($ticked)
        >
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
