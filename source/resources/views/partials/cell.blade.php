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
        <img src="{{ $src }}" alt="" class="y-thumb">
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
