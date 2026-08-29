{{--
    The library itself, opened inside the field to choose the files of the kinds
    that field takes. The choice comes back as an event of the component rather
    than as a value, because the wall stands under the modal and knows nothing
    of the record above it.

    A field holding several files gathers them: the wall hands over everything
    that was ticked at once, under the name «ids», and a field holding one hands
    back the card that was opened, under the name «id». The field answers to
    both, so the one listener serves either.
--}}
<div x-on:media-chosen="$wire.callSchemaComponentMethod(@js($key), 'chosen', $event.detail); close()">
    @livewire(\Mediator\Livewire\MediaLibrary::class, ['picking' => true, 'many' => $many ?? false, 'takes' => $takes], key('media-field-'.$key))
</div>
