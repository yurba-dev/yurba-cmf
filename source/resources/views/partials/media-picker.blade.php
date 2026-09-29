{{-- @once so several includes (a Media field plus media repeater columns) emit a single #y-media-picker --}}
@once
<div class="y-picker" id="y-media-picker" hidden
     data-list-url="{{ route('yurba.media.list') }}"
     data-store-url="{{ route('yurba.media.store') }}">
    <div class="y-picker__backdrop" data-picker-close></div>
    <div class="y-picker__panel" role="dialog" aria-modal="true" aria-label="Media library">
        <div class="y-picker__head">
            <input type="search" class="y-input y-picker__search" placeholder="Search media…" data-picker-search>
            <label class="y-btn y-btn__ghost">
                <span class="material-symbols-rounded">upload</span> Upload
                <input type="file" accept="image/*,application/pdf" data-picker-upload hidden>
            </label>
            <button type="button" class="y-btn y-btn__ghost y-picker__x" data-picker-close aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M4 4 12 12M12 4 4 12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        <div class="y-picker__body">
            <div class="y-picker__grid" data-picker-grid></div>
            <div class="y-picker__empty" data-picker-empty hidden>No media found.</div>
        </div>
    </div>
</div>
@if(config('yurba.ui.viewer', true))
    @include('yurba::partials.ui-viewer')
@endif
{{-- YurbaUI lets main.js open the picker in a y-win modal; without it the standalone overlay is used --}}
@if(config('yurba.ui.select', true))
    @include('yurba::partials.ui-select')
@endif
@endonce
