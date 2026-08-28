@php($seo = (array) $field->formValue($record))
@php($ogImage = (string) ($seo['og_image'] ?? ''))
<div class="y-seo">
    <label class="y-seo__row">
        <span class="y-seo__label">Meta title</span>
        <input type="text" name="{{ $field->name }}[meta_title]" value="{{ $seo['meta_title'] ?? '' }}"
               class="y-input" placeholder="Defaults to the record title" maxlength="255">
    </label>
    <label class="y-seo__row">
        <span class="y-seo__label">Meta description</span>
        <textarea name="{{ $field->name }}[meta_description]" rows="3" class="y-input y-textarea"
                  placeholder="Shown in search results / social shares">{{ $seo['meta_description'] ?? '' }}</textarea>
    </label>
    <div class="y-seo__row">
        <span class="y-seo__label">OG image</span>
        <div class="y-media-field" data-media-field>
            <input type="hidden" name="{{ $field->name }}[og_image]" value="{{ $ogImage }}" data-media-input>
            <div class="y-media-field__preview {{ $ogImage ? '' : 'is-empty' }}" data-media-preview>
                @if($ogImage)<img src="{{ $ogImage }}" alt="" loading="lazy">@endif
            </div>
            <div class="y-media-field__buttons">
                <button type="button" class="y-btn y-btn__ghost y-btn__xs" data-media-open>
                    <span class="material-symbols-rounded">perm_media</span> Choose from library
                </button>
                <button type="button" class="y-btn y-btn__ghost y-btn__xs" data-media-clear @if(! $ogImage) hidden @endif>Clear</button>
            </div>
        </div>
    </div>
    <label class="y-check y-seo__noindex">
        <input type="hidden" name="{{ $field->name }}[noindex]" value="0">
        <input type="checkbox" name="{{ $field->name }}[noindex]" value="1" @checked(! empty($seo['noindex']))>
        Hide from search engines (noindex)
    </label>
</div>

@include('yurba::partials.media-picker')
