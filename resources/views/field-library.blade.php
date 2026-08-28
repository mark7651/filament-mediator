{{--
    The library itself, opened inside the field to choose one file of the kinds
    that field takes. The choice comes back as an event of the component rather
    than as a value, because the wall stands under the modal and knows nothing
    of the record above it.
--}}
<div x-on:media-chosen="$wire.callSchemaComponentMethod(@js($key), 'chosen', $event.detail); close()">
    @livewire(\Mediator\Livewire\MediaLibrary::class, ['picking' => true, 'takes' => $takes], key('media-field-'.$key))
</div>
