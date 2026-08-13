@php($value = (string) $field->formValue($record))
<div class="y-media-field" data-media-field>
    <input type="hidden" name="{{ $field->name }}" value="{{ $value }}" data-media-input>
    <div class="y-media-field__preview {{ $value ? '' : 'is-empty' }}" data-media-preview>
        @if($value)<img src="{{ $value }}" alt="" loading="lazy">@endif
    </div>
    <div class="y-media-field__buttons">
        <button type="button" class="y-btn y-btn__ghost y-btn__xs" data-media-open>
            <span class="material-symbols-rounded">perm_media</span> Choose from library
        </button>
        <button type="button" class="y-btn y-btn__ghost y-btn__xs" data-media-clear @if(! $value) hidden @endif>Clear</button>
    </div>
</div>

@include('yurba::partials.media-picker')
