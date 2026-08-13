@php($base = $field->baseUrl)
@php($url = $field->permalinkFor($record))
@php($slug = $field->formValue($record))

<input type="text"
       id="{{ $field->name }}"
       name="{{ $field->name }}"
       value="{{ $slug }}"
       @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
       class="y-input"
       autocapitalize="off" autocorrect="off" spellcheck="false"
       @if($base !== null) data-slug-base="{{ $base }}/" @endif>

@if($base !== null)
    <p class="y-help" data-slug-preview @if(blank($slug)) hidden @endif>
        Permalink:
        <a href="{{ $url ?: $base }}" target="_blank" rel="noopener"
           data-slug-link style="word-break:break-all">{{ $url ?: $base.'/'.$slug }}</a>
    </p>
@endif
