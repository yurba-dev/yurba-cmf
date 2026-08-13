@php($seo = (array) $field->formValue($record))
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
    <label class="y-seo__row">
        <span class="y-seo__label">OG image URL</span>
        <input type="text" name="{{ $field->name }}[og_image]" value="{{ $seo['og_image'] ?? '' }}"
               class="y-input" placeholder="/storage/media/… (paste from the Media library)">
    </label>
    <label class="y-check y-seo__noindex">
        <input type="hidden" name="{{ $field->name }}[noindex]" value="0">
        <input type="checkbox" name="{{ $field->name }}[noindex]" value="1" @checked(! empty($seo['noindex']))>
        Hide from search engines (noindex)
    </label>
</div>
