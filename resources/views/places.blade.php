@php
    /** @var \Mediator\Models\Media $open */
    // A picture of a heading can stand in fifty records, and fifty lines of them
    // would push the deeds of the file off the panel and the buttons out of the
    // warning. What is worth reading is which kinds of record are involved, and
    // that is answered by the first of them.
    $places = array_slice($open->standsIn(), 0, 8);
    $rest = $open->usedBy() - count($places);
@endphp

@if ($places !== [])
    <ul class="media-places">
        @foreach ($places as $place)
            <li class="media-places__place">
                <span class="media-places__kind">{{ $place['kind'] }}</span>

                @if ($place['url'] === null)
                    <span class="media-places__name">{{ $place['label'] }}</span>
                @else
                    <a href="{{ $place['url'] }}" target="_blank" class="media-places__name media-places__name--way">{{ $place['label'] }}</a>
                @endif
            </li>
        @endforeach

        @if ($rest > 0)
            <li class="media-places__place media-places__place--rest">
                {{ trans_choice('mediator::media.elsewhere', $rest, ['count' => $rest]) }}
            </li>
        @endif
    </ul>
@endif
