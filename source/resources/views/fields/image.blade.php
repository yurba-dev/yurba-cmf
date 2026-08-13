@php($current = $field->value($record))
<div class="y-image">
    @if($current)<span class="y-image__preview"><img src="{{ $current }}" alt=""></span>@endif
    <input type="file" id="{{ $field->name }}" name="{{ $field->name }}" accept="image/*" class="y-file">
</div>
@if($current && config('yurba.ui.viewer', true))
    @include('yurba::partials.ui-viewer')
@endif
