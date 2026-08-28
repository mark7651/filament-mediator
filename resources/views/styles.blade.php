<style>
    .media {
        display: grid;
        gap: 1rem;
    }

    .media__bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }

    .media__search {
        flex: 1 1 16rem;
    }

    .media__type {
        flex: 0 0 auto;
    }

    .media__deeds {
        display: flex;
        gap: 0.5rem;
        margin-inline-start: auto;
    }

    .media__picker {
        display: none;
    }

    .media__alarm {
        padding: 0.625rem 0.875rem;
        border-radius: 0.5rem;
        background-color: var(--danger-50);
        font-size: 0.875rem;
        color: var(--danger-700);
    }

    .dark .media__alarm {
        background-color: color-mix(in srgb, var(--danger-400) 15%, transparent);
        color: var(--danger-400);
    }

    .media__body {
        display: grid;
        gap: 1.5rem;
        align-items: start;
    }

    /* The column of the panel is given its width at once and the cards are
       carried to their new places by the script instead: a grid told to grow
       over time lays itself out anew on every frame, and a wall of pictures
       laid out sixty times a second stutters. */
    @media (min-width: 1024px) {
        .media__body--open {
            grid-template-columns: minmax(0, 1fr) 19rem;
            column-gap: 1.25rem;
        }

        .media__side {
            position: sticky;
            inset-block-start: 5rem;
            min-width: 0;
        }
    }

    .media__side:empty {
        display: none;
    }

    .media__wall-side {
        display: grid;
        gap: 1rem;
        min-width: 0;
    }

    .media__wall {
        position: relative;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
        gap: 1rem;
        transition: opacity 150ms ease;
    }

    .media__wall--busy {
        opacity: 0.5;
    }

    .media__empty {
        grid-column: 1 / -1;
        padding: 3rem 1rem;
        text-align: center;
        font-size: 0.875rem;
        color: var(--gray-500);
    }

    .media__drop {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        border: 2px dashed var(--primary-500);
        background-color: color-mix(in srgb, var(--gray-50) 90%, transparent);
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--gray-950);
    }

    .dark .media__drop {
        background-color: color-mix(in srgb, var(--gray-900) 90%, transparent);
        color: white;
    }

    .media__more {
        display: flex;
        justify-content: center;
    }
    .media-card {
        position: relative;
        overflow: hidden;
        cursor: pointer;
        border-radius: 0.875rem;
        background-color: var(--gray-50);
        box-shadow: 0 0 0 1px var(--gray-200);
        transition: box-shadow 200ms ease, transform 200ms ease;
        animation: media-card-in 240ms ease-out;
    }

    /* Only a card that is new to the page runs this: the ones already standing
       are kept by their key and morphed rather than drawn again. Left without a
       fill so the raised state of the hover is the card's own again as soon as
       it has arrived. */
    @keyframes media-card-in {
        from {
            opacity: 0;
            transform: translateY(0.375rem);
        }
    }

    .dark .media-card {
        background-color: var(--gray-900);
        box-shadow: 0 0 0 1px var(--gray-800);
    }

    .media-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0 0 1px var(--gray-300), 0 14px 26px -18px rgb(0 0 0 / 0.45);
    }

    .dark .media-card:hover {
        box-shadow: 0 0 0 1px var(--gray-700), 0 14px 26px -14px rgb(0 0 0 / 0.75);
    }

    .media-card:focus-visible {
        outline: 2px solid var(--primary-500);
        outline-offset: 2px;
    }

    .media-card.media-card--open,
    .media-card:has(.media-card__box:checked) {
        box-shadow: 0 0 0 2px var(--primary-500);
    }

    .media-card.media-card--open:hover,
    .media-card:has(.media-card__box:checked):hover {
        box-shadow: 0 0 0 2px var(--primary-500), 0 14px 26px -18px rgb(0 0 0 / 0.45);
    }

    .media-card__frame {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        aspect-ratio: 1 / 1;
        overflow: hidden;
        background-color: var(--gray-100);
    }

    .dark .media-card__frame {
        background-color: var(--gray-950);
    }

    .media-card__frame::after {
        content: '';
        position: absolute;
        inset: 0;
        background-image: linear-gradient(to top, rgb(0 0 0 / 0.4), transparent 45%);
        opacity: 0;
        transition: opacity 200ms ease;
    }

    .media-card:hover .media-card__frame::after {
        opacity: 1;
    }

    /* A card stands before the picture in it has arrived, so the picture is
       brought up rather than dropped into the grey frame it was waiting in. */
    .media-card__image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0;
        transition: opacity 200ms ease, transform 350ms ease;
    }

    .media-card__image--ready {
        opacity: 1;
    }

    .media-card:hover .media-card__image {
        transform: scale(1.04);
    }

    .media-card__sign {
        width: 2.5rem;
        height: 2.5rem;
        color: var(--gray-400);
    }

    .media-card__tick {
        position: absolute;
        inset-block-start: 0.5rem;
        inset-inline-start: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 150ms ease;
    }

    .media-card__box {
        appearance: none;
        width: 1.375rem;
        height: 1.375rem;
        margin: 0;
        border: 2px solid rgb(255 255 255 / 0.9);
        border-radius: 999px;
        background-color: rgb(0 0 0 / 0.35);
        box-shadow: 0 1px 3px rgb(0 0 0 / 0.35);
        backdrop-filter: blur(6px);
        cursor: pointer;
        transition: background-color 150ms ease, border-color 150ms ease;
    }

    .media-card__box:checked {
        border-color: var(--primary-500);
        background-color: var(--primary-500);
    }

    .media-card__check {
        position: absolute;
        width: 0.875rem;
        height: 0.875rem;
        color: white;
        opacity: 0;
        pointer-events: none;
    }

    .media-card:hover .media-card__tick,
    .media-card:focus-within .media-card__tick,
    .media-card__tick:has(:checked),
    .media--many .media-card__tick {
        opacity: 1;
    }

    .media-card__tick:has(:checked) .media-card__check {
        opacity: 1;
    }

    .media-card__text {
        min-width: 0;
        padding: 0.625rem 0.75rem 0.75rem;
    }

    .media-card__title {
        overflow: hidden;
        font-size: 0.8125rem;
        font-weight: 500;
        line-height: 1.25rem;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--gray-950);
    }

    .dark .media-card__title {
        color: white;
    }

    .media-card__facts {
        margin-top: 0.125rem;
        overflow: hidden;
        font-size: 0.6875rem;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--gray-500);
    }

    .dark .media-card__facts {
        color: var(--gray-400);
    }

    /* Kept away by the stylesheet rather than by the script, because the wall is
       drawn before Livewire is awake and a wall that opens on a row of grey
       cards is the very jump these are here to take out. */
    .media-ghost {
        display: none;
        overflow: hidden;
        border-radius: 0.875rem;
        background-color: var(--gray-50);
        box-shadow: 0 0 0 1px var(--gray-200);
    }

    .dark .media-ghost {
        background-color: var(--gray-900);
        box-shadow: 0 0 0 1px var(--gray-800);
    }

    .media-ghost__frame {
        aspect-ratio: 1 / 1;
        background-color: var(--gray-100);
    }

    .dark .media-ghost__frame {
        background-color: var(--gray-950);
    }

    .media-ghost__text {
        display: grid;
        gap: 0.4375rem;
        padding: 0.75rem;
    }

    .media-ghost__line {
        height: 0.5rem;
        border-radius: 999px;
        background-color: var(--gray-200);
    }

    .dark .media-ghost__line {
        background-color: var(--gray-800);
    }

    .media-ghost__line--short {
        width: 55%;
    }

    .media-ghost__frame,
    .media-ghost__line {
        animation: media-ghost-breath 1.4s ease-in-out infinite;
    }

    @keyframes media-ghost-breath {
        50% {
            opacity: 0.45;
        }
    }

    .media-details {
        display: grid;
        gap: 0.75rem;
        padding: 0.875rem;
        border-radius: 0.75rem;
        background-color: var(--gray-50);
        box-shadow: 0 0 0 1px var(--gray-200);
        animation: media-details-in 260ms cubic-bezier(0.32, 0.72, 0, 1) both;
    }

    .dark .media-details {
        background-color: var(--gray-900);
        box-shadow: 0 0 0 1px var(--gray-700);
    }

    /* Slid in without fading, so the cards moving to their new places pass
       behind a panel that is already solid instead of showing through it. */
    @keyframes media-details-in {
        from {
            transform: translateX(1.25rem);
        }

        to {
            transform: none;
        }
    }

    .media-details__head {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .media-details__name {
        min-width: 0;
        overflow: hidden;
        font-size: 0.8125rem;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--gray-950);
    }

    .dark .media-details__name {
        color: white;
    }

    .media-details__walk {
        display: flex;
        gap: 0.125rem;
        margin-inline-start: auto;
    }

    .media-details__frame {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 10rem;
        overflow: hidden;
        border-radius: 0.5rem;
        background-color: var(--gray-100);
    }

    .dark .media-details__frame {
        background-color: var(--gray-950);
    }

    .media-details__look {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }

    .media-details__image,
    .media-details__player {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .media-details__sound {
        width: 90%;
    }

    .media-details__sign {
        width: 3rem;
        height: 3rem;
        color: var(--gray-400);
    }

    .media-details__form {
        display: grid;
        gap: 0.25rem;
    }

    .media-details__form .fi-input {
        padding-block: 0.3125rem;
        font-size: 0.8125rem;
    }

    .media-details__label {
        font-size: 0.6875rem;
        font-weight: 500;
        line-height: 1rem;
        color: var(--gray-500);
    }

    .media-details__label + .fi-input-wrp {
        margin-block-end: 0.375rem;
    }

    .dark .media-details__label {
        color: var(--gray-400);
    }

    .media-details__save {
        justify-self: end;
    }

    .media-details__facts {
        display: grid;
        gap: 0.125rem;
        padding-block-start: 0.75rem;
        border-block-start: 1px solid var(--gray-200);
    }

    .dark .media-details__facts {
        border-block-start-color: var(--gray-800);
    }

    .media-details__file {
        overflow-wrap: anywhere;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1.125rem;
        color: var(--gray-950);
    }

    .dark .media-details__file {
        color: white;
    }

    .media-details__note {
        font-size: 0.6875rem;
        line-height: 1.125rem;
        color: var(--gray-500);
    }

    .dark .media-details__note {
        color: var(--gray-400);
    }

    .media-details__deeds {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding-block-start: 0.75rem;
        border-block-start: 1px solid var(--gray-200);
    }

    .dark .media-details__deeds {
        border-block-start-color: var(--gray-800);
    }

    .media-details__copy {
        display: flex;
    }

    .media-details__erase {
        margin-inline-start: auto;
    }

    .media--picking .media__wall-side {
        height: 60vh;
        padding: 0.25rem;
        overflow-y: auto;
    }

    .media--picking .media__wall {
        align-content: start;
    }

    .media-field {
        display: grid;
        justify-items: start;
        gap: 0.75rem;
    }

    .media-field__chosen {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.875rem;
        max-width: 100%;
        padding: 0.5rem 0.75rem 0.5rem 0.5rem;
        border-radius: 0.875rem;
        background-color: var(--gray-50);
        box-shadow: 0 0 0 1px var(--gray-200);
        transition: box-shadow 200ms ease;
    }

    .dark .media-field__chosen {
        background-color: var(--gray-900);
        box-shadow: 0 0 0 1px var(--gray-800);
    }

    .media-field__chosen:hover {
        box-shadow: 0 0 0 1px var(--gray-300);
    }

    .dark .media-field__chosen:hover {
        box-shadow: 0 0 0 1px var(--gray-700);
    }

    .media-field__frame {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 4.5rem;
        height: 4.5rem;
        overflow: hidden;
        border-radius: 0.625rem;
        background-color: var(--gray-100);
    }

    .dark .media-field__frame {
        background-color: var(--gray-950);
    }

    .media-field__image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .media-field__sign {
        width: 1.75rem;
        height: 1.75rem;
        color: var(--gray-400);
    }

    .media-field__text {
        min-width: 0;
    }

    .media-field__name {
        overflow-wrap: anywhere;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.25rem;
        color: var(--gray-950);
    }

    .dark .media-field__name {
        color: white;
    }

    .media-field__facts {
        margin-top: 0.125rem;
        font-size: 0.75rem;
        color: var(--gray-500);
    }

    .dark .media-field__facts {
        color: var(--gray-400);
    }

    .media-field__deeds {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    @media (prefers-reduced-motion: reduce) {
        .media-details {
            animation: none;
        }

        .media-card {
            animation: none;
        }

        .media-ghost__frame,
        .media-ghost__line {
            animation: none;
        }

        .media-card,
        .media-card__image,
        .media-card__frame::after,
        .media-card__tick,
        .media-card__box {
            transition: none;
        }

        .media-card:hover {
            transform: none;
        }

        .media-card:hover .media-card__image {
            transform: none;
        }

        .media-card__image {
            opacity: 1;
        }
    }
</style>
