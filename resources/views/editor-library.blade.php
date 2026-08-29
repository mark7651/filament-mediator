{{--
    The library itself, opened from the toolbar of the editor to gather the
    pictures that go into the text. The choice comes back as an argument of the
    action the toolbar mounted, because the wall stands under the modal and
    knows nothing of the text above it.
--}}
<div x-on:media-chosen="$wire.callMountedAction($event.detail)">
    @livewire(\Mediator\Livewire\MediaLibrary::class, ['picking' => true, 'many' => true, 'intoText' => true, 'takes' => $takes], key('media-editor-'.$key))
</div>
