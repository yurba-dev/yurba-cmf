{{-- Shared media picker modal (rendered once per page). Any [data-media-field]
     opens it; picking an item writes the URL back into that field. The @once
     guards the whole modal so several includes (a Media field plus media
     repeater columns) still emit a single #y-media-picker. --}}
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
{{-- load YurbaPV so [data-media-preview] images zoom (parity with the Image field);
     when ui.viewer is off, main.js's initViewer no-ops and previews just don't zoom --}}
@if(config('yurba.ui.viewer', true))
    @include('yurba::partials.ui-viewer')
@endif
{{-- load YurbaUI so main.js renders the picker inside a y-win modal; without it the
     standalone .y-picker overlay is used. @once-guarded, shared with the Select field. --}}
@if(config('yurba.ui.select', true))
    @include('yurba::partials.ui-select')
@endif
@endonce
