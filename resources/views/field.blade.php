@php
    $many = $isMultiple();
    /** @var list<\Mediator\Models\Media> $standing */
    $standing = $many ? $getChosenMany() : [];
    /** @var \Mediator\Models\Media|null $chosen */
    $chosen = $many ? null : $getChosen();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="media-field">
        @if ($many)
            @if ($standing !== [])
                <div class="media-field__standing">
                    @foreach ($standing as $file)
                        <div class="media-field__chosen" wire:key="{{ $getKey() }}-{{ $file->getKey() }}">
                            <div class="media-field__frame">
                                @if (str_starts_with((string) $file->type, 'image/'))
                                    <img src="{{ $file->thumbnailUrl }}" alt="{{ $file->alt }}" class="media-field__image">
                                @else
                                    @svg('heroicon-o-document', 'media-field__sign')
                                @endif
                            </div>

                            <div class="media-field__text">
                                <p class="media-field__name">{{ $file->title ?? $file->name }}</p>

                                <p class="media-field__facts">
                                    @if ($file->width && $file->height)
                                        {{ $file->width }}×{{ $file->height }}<span aria-hidden="true"> · </span>
                                    @endif

                                    {{ \Illuminate\Support\Number::fileSize((int) $file->size, precision: 1) }}
                                </p>
                            </div>

                            @unless ($isDisabled())
                                <button
                                    type="button"
                                    class="media-field__remove"
                                    title="{{ __('mediator::media.field.remove') }}"
                                    aria-label="{{ __('mediator::media.field.remove') }}"
                                    x-on:click="$wire.callSchemaComponentMethod(@js($getKey()), 'remove', { id: @js($file->getKey()) })"
                                >
                                    @svg('heroicon-m-x-mark', 'media-field__remove-sign')
                                </button>
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="media-field__deeds">
                {{ $getAction('choose') }}

                @if ($standing !== [])
                    {{ $getAction('clear') }}
                @endif
            </div>
        @elseif ($chosen)
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
