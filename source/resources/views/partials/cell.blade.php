@php($type = $field->indexComponent())
@if($type === 'boolean')
    @if($field->value($record))
        <span class="y-pill y-pill--ok">Yes</span>
    @else
        <span class="y-pill y-pill--muted">No</span>
    @endif
@elseif($type === 'badge')
    @php($text = $field->indexValue($record))
    @if(filled($text))<span class="y-pill">{{ $text }}</span>@else<span class="y-muted">—</span>@endif
@elseif($type === 'slug')
    @php($url = $field->permalinkFor($record))
    @php($text = $field->indexValue($record))
    @if($url)
        <a href="{{ $url }}" target="_blank" rel="noopener">{{ $text }}</a>
    @elseif(filled($text))
        {{ $text }}
    @else
        <span class="y-muted">—</span>
    @endif
@elseif($type === 'image')
    @php($src = $field->value($record))
    @if($src)
        {{-- data-full is the original: the panel viewer (main.js bindImg) reads
             it for the lightbox, otherwise it would open the thumbnail src. --}}
        <a href="{{ $src }}" target="_blank" rel="noopener">
            <img src="{{ \Yurba\Cmf\Media\Media::thumbFor($src) }}" data-full="{{ $src }}" alt="" class="y-thumb"
                 onerror="this.onerror=null;this.src='{{ $src }}'">
        </a>
        @if(config('yurba.ui.viewer', true))
            @include('yurba::partials.ui-viewer')
        @endif
    @else
        <span class="y-muted">—</span>
    @endif
@else
    @php($text = $field->indexValue($record))
    {{ \Illuminate\Support\Str::limit($text, 70) ?: '' }}@if(!filled($text))<span class="y-muted">—</span>@endif
@endif
