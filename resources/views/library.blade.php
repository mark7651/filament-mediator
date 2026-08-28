@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \Mediator\Models\Media> $wall */
    /** @var \Mediator\Models\Media|null $open */
    $deed = match (true) {
        $many => 'toggle',
        $picking => 'choose',
        default => 'open',
    };
    $types = [
        'image' => __('mediator::media.types.image'),
        'video' => __('mediator::media.types.video'),
        'audio' => __('mediator::media.types.audio'),
        'document' => __('mediator::media.types.document'),
    ];
@endphp

<div
    class="media @if ($picking) media--picking @endif @if ($many) media--many @endif"
    x-data="{
        dragging: false,
        uploading: false,
        progress: 0,
        places: new Map(),
        init() {
            this.$wire.intercept(({ onSend, onFinish }) => {
                onSend(() => this.remember())
                onFinish(() => this.rebuild())
            })
        },
        spot(card) {
            const wall = this.$refs.wall.getBoundingClientRect()
            const place = card.getBoundingClientRect()

            return { left: place.left - wall.left, top: place.top - wall.top }
        },
        remember() {
            this.places.clear()

            this.$el.querySelectorAll('.media-card').forEach((card) => {
                this.places.set(card.dataset.file, this.spot(card))
            })
        },
        rebuild() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return
            }

            this.$el.querySelectorAll('.media-card').forEach((card) => {
                const was = this.places.get(card.dataset.file)

                if (! was) {
                    return
                }

                const now = this.spot(card)
                const across = was.left - now.left
                const down = was.top - now.top

                if (Math.abs(across) < 1 && Math.abs(down) < 1) {
                    return
                }

                card.animate(
                    [{ transform: `translate(${across}px, ${down}px)` }, { transform: 'none' }],
                    { duration: 280, easing: 'cubic-bezier(0.32, 0.72, 0, 1)' },
                )
            })
        },
        walking(event) {
            return $wire.openId && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)
        },
        take(files) {
            if (! files.length) {
                return
            }

            this.uploading = true

            $wire.uploadMultiple(
                'files',
                files,
                () => { this.uploading = false; this.progress = 0 },
                () => { this.uploading = false; this.progress = 0 },
                (event) => { this.progress = event.detail.progress },
            )
        },
    }"
    x-on:dragover.prevent="dragging = true"
    x-on:dragleave.prevent="dragging = false"
    x-on:drop.prevent="dragging = false; take($event.dataTransfer.files)"
    x-on:keydown.window.arrow-right="walking($event) && $wire.next()"
    x-on:keydown.window.arrow-left="walking($event) && $wire.previous()"
    x-on:keydown.window.escape="walking($event) && $wire.close()"
>
    @if ($errors->any())
        <p class="media__alarm">{{ $errors->first() }}</p>
    @endif

    <div class="media__bar">
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="media__search">
            <x-filament::input
                type="search"
                wire:model.live.debounce.400ms="search"
                :placeholder="__('mediator::media.search')"
            />
        </x-filament::input.wrapper>

        @if ($takes === [])
            <x-filament::input.wrapper class="media__type">
                <x-filament::input.select wire:model.live="type">
                    <option value="">{{ __('mediator::media.types.all') }}</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @endif

        <div class="media__deeds">
            @if ($many)
                <x-filament::button
                    icon="heroicon-m-check"
                    wire:click="chooseMany"
                    :disabled="blank($chosen)"
                >
                    {{ __('mediator::media.actions.choose_many') }}@if (filled($chosen)) ({{ count($chosen) }})@endif
                </x-filament::button>
            @elseif ($canDelete && filled($chosen))
                <x-filament::button
                    color="danger"
                    icon="heroicon-m-trash"
                    wire:click="deleteChosen"
                    wire:confirm="{{ __('mediator::media.delete.heading_many') }}"
                >
                    {{ __('mediator::media.actions.delete_selected') }} ({{ count($chosen) }})
                </x-filament::button>
            @endif

            <x-filament::button icon="heroicon-m-arrow-up-tray" x-on:click="$refs.picker.click()">
                {{ __('mediator::media.actions.upload') }}
            </x-filament::button>

            <input type="file" @if (! $picking || $many) multiple @endif wire:model="files" x-ref="picker" class="media__picker">
        </div>
    </div>

    <div class="media__body @if ($open) media__body--open @endif">
        <div class="media__wall-side">
            <div
                class="media__wall"
                x-ref="wall"
                x-bind:class="dragging && 'media__wall--dragging'"
                wire:loading.class="media__wall--busy"
                wire:target="search, type"
            >
                @forelse ($wall as $file)
                    @include('mediator::card', ['file' => $file, 'deed' => $deed, 'open' => $openId === $file->id, 'ticked' => in_array($file->id, $chosen, true)])
                @empty
                    <p class="media__empty">{{ __('mediator::media.empty') }}</p>
                @endforelse

                {{-- The places of the cards on their way, held from the moment they are
                     asked for so the wall grows into a space that is already there and
                     the foot of it stays under the eye that reached it. --}}
                @for ($number = 1; $number <= $coming; $number++)
                    <div
                        class="media-ghost"
                        wire:loading.block
                        wire:target="loadMore"
                        wire:key="ghost-{{ $number }}"
                        aria-hidden="true"
                    >
                        <div class="media-ghost__frame"></div>

                        <div class="media-ghost__text">
                            <span class="media-ghost__line"></span>
                            <span class="media-ghost__line media-ghost__line--short"></span>
                        </div>
                    </div>
                @endfor

                <div class="media__drop" x-show="dragging || uploading" x-cloak>
                    <span x-show="! uploading">{{ __('mediator::media.drop') }}</span>
                    <span x-show="uploading" x-text="`{{ __('mediator::media.uploading') }} ${progress}%`"></span>
                </div>
            </div>

            @if ($left > 0)
                <div
                    class="media__more"
                    x-data="{
                        watcher: null,
                        init() {
                            const port = this.$el.closest('.media__wall-side')

                            this.watcher = new IntersectionObserver(([entry]) => this.reach(entry), {
                                root: getComputedStyle(port).overflowY === 'auto' ? port : null,
                                rootMargin: '100px',
                            })

                            this.watcher.observe(this.$el)
                        },
                        reach(entry) {
                            if (! entry.isIntersecting) {
                                return
                            }

                            this.$wire.loadMore().then(() => {
                                this.watcher.unobserve(this.$el)
                                this.$nextTick(() => this.watcher.observe(this.$el))
                            })
                        },
                        destroy() {
                            this.watcher.disconnect()
                        },
                    }"
                >
                    <x-filament::button color="gray" wire:click="loadMore" wire:loading.attr="disabled">
                        {{ __('mediator::media.more', ['count' => $left]) }}
                    </x-filament::button>
                </div>
            @endif
        </div>

        <div class="media__side">
            @if ($open)
                <aside class="media-details" wire:key="media-details">
                    <div class="media-details__head">
                        <p class="media-details__name" title="{{ $open->title ?? $open->name }}">{{ $open->title ?? $open->name }}</p>

                        <div class="media-details__walk">
                            <x-filament::icon-button
                                icon="heroicon-m-chevron-up"
                                :label="__('mediator::media.actions.previous')"
                                color="gray"
                                size="sm"
                                wire:click="previous"
                            />
                            <x-filament::icon-button
                                icon="heroicon-m-chevron-down"
                                :label="__('mediator::media.actions.next')"
                                color="gray"
                                size="sm"
                                wire:click="next"
                            />
                            <x-filament::icon-button
                                icon="heroicon-m-x-mark"
                                :label="__('mediator::media.actions.close')"
                                color="gray"
                                size="sm"
                                wire:click="close"
                            />
                        </div>
                    </div>

                    <div class="media-details__frame">
                        @if (str_starts_with((string) $open->type, 'image/'))
                            <a
                                href="{{ $open->largeUrl }}"
                                target="_blank"
                                class="media-details__look"
                                title="{{ __('mediator::media.actions.view') }}"
                            >
                                <img src="{{ $open->largeUrl }}" alt="{{ $open->alt }}" class="media-details__image">
                            </a>
                        @elseif (str_starts_with((string) $open->type, 'video/'))
                            <video src="{{ $open->url }}" controls class="media-details__player"></video>
                        @elseif (str_starts_with((string) $open->type, 'audio/'))
                            <audio src="{{ $open->url }}" controls class="media-details__sound"></audio>
                        @else
                            @svg('heroicon-o-document', 'media-details__sign')
                        @endif
                    </div>

                    <div class="media-details__form">
                        <label class="media-details__label" for="media-title">{{ __('mediator::media.fields.title') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" id="media-title" wire:model="title" />
                        </x-filament::input.wrapper>

                        <label class="media-details__label" for="media-alt">{{ __('mediator::media.fields.alt') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" id="media-alt" wire:model="alt" />
                        </x-filament::input.wrapper>

                        <div class="media-details__save">
                            <x-filament::button size="sm" wire:click="saveDetails" wire:loading.attr="disabled">
                                {{ __('mediator::media.actions.save') }}
                            </x-filament::button>
                        </div>
                    </div>

                    <div class="media-details__facts">
                        <p class="media-details__file">{{ $open->name }}.{{ $open->ext }}</p>

                        <p class="media-details__note">
                            {{ mb_strtoupper((string) $open->ext) }}
                            @if ($open->width && $open->height)
                                <span aria-hidden="true"> · </span>{{ $open->width }}×{{ $open->height }}
                            @endif
                            <span aria-hidden="true"> · </span>{{ \Illuminate\Support\Number::fileSize((int) $open->size, precision: 1) }}
                        </p>

                        <p class="media-details__note">
                            {{ __('mediator::media.taken', ['when' => $open->created_at?->format('d.m.Y H:i')]) }}
                            <span aria-hidden="true"> · </span>{{ trans_choice('mediator::media.standing', $open->usedBy(), ['count' => $open->usedBy()]) }}
                        </p>
                    </div>

                    <div class="media-details__deeds">
                        <x-filament::icon-button
                            icon="heroicon-m-arrow-path"
                            :label="__('mediator::media.actions.replace')"
                            color="gray"
                            x-on:click="$refs.swap.click()"
                            wire:loading.attr="disabled"
                            wire:target="replacement"
                        />

                        <input type="file" wire:model="replacement" x-ref="swap" class="media__picker">

                        <div class="media-details__copy" x-data="{ copied: false }">
                            <x-filament::icon-button
                                icon="heroicon-m-link"
                                :label="__('mediator::media.actions.copy')"
                                color="gray"
                                x-show="! copied"
                                x-on:click="navigator.clipboard.writeText('{{ $open->url }}'); copied = true; setTimeout(() => copied = false, 1500)"
                            />
                            <x-filament::icon-button
                                icon="heroicon-m-check"
                                :label="__('mediator::media.copied')"
                                color="success"
                                x-show="copied"
                                x-cloak
                            />
                        </div>

                        <x-filament::icon-button
                            icon="heroicon-m-arrow-down-tray"
                            :label="__('mediator::media.actions.download')"
                            color="gray"
                            tag="a"
                            :href="$open->url"
                            download
                        />

                        @if ($canDelete)
                            <x-filament::icon-button
                                icon="heroicon-m-trash"
                                :label="__('mediator::media.actions.delete')"
                                color="danger"
                                class="media-details__erase"
                                wire:click="delete({{ $open->id }})"
                                wire:confirm="{{ $open->usedBy() > 0
                                    ? trans_choice('mediator::media.delete.in_use', $open->usedBy(), ['count' => $open->usedBy()])
                                    : __('mediator::media.delete.unused') }}"
                            />
                        @endif
                    </div>
                </aside>
            @endif
        </div>
    </div>
</div>
