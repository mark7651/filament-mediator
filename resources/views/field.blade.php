@php
    /** @var \Mediator\Models\Media|null $chosen */
    $chosen = $getChosen();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="media-field">
        @if ($chosen)
            <div class="media-field__chosen">
                <div class="media-field__frame">
                    @if (str_starts_with((string) $chosen->type, 'image/'))
                        <img src="{{ $chosen->thumbnailUrl }}" alt="{{ $chosen->alt }}" class="media-field__image">
                    @else
                        @svg('heroicon-o-document', 'media-field__sign')
                    @endif
                </div>

                <div class="media-field__text">
                    <p class="media-field__name">{{ $chosen->title ?? $chosen->name }}</p>

                    <p class="media-field__facts">
                        @if ($chosen->width && $chosen->height)
                            {{ $chosen->width }}×{{ $chosen->height }}<span aria-hidden="true"> · </span>
                        @endif

                        {{ \Illuminate\Support\Number::fileSize((int) $chosen->size, precision: 1) }}
                    </p>
                </div>

                <div class="media-field__deeds">
                    {{ $getAction('choose') }}
                    {{ $getAction('clear') }}
                </div>
            </div>
        @else
            <div class="media-field__deeds">
                {{ $getAction('choose') }}
            </div>
        @endif
    </div>
</x-dynamic-component>
