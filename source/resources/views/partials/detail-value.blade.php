{{-- Read-only rendering of one field's value for the detail page. --}}
@php($type = $field->indexComponent())
@if($type === 'boolean')
    @if($field->value($record))
        <span class="y-pill y-pill--ok">Yes</span>
    @else
        <span class="y-pill y-pill--muted">No</span>
    @endif
@elseif($type === 'slug')
    @php($url = $field->permalinkFor($record))
    @php($text = $field->indexValue($record))
    @if($url)
        <a href="{{ $url }}" target="_blank" rel="noopener">{{ $text }}</a>
    @elseif(filled($text)){{ $text }}@else<span class="y-muted">—</span>@endif
@elseif($type === 'badge')
    @php($text = $field->indexValue($record))
    @if(filled($text))<span class="y-pill">{{ $text }}</span>@else<span class="y-muted">—</span>@endif
@elseif($type === 'image')
    @php($src = $field->value($record))
    @if($src)<img src="{{ $src }}" alt="" class="y-thumb y-thumb--lg">@else<span class="y-muted">—</span>@endif
@elseif($field->isHtml())
    @php($html = (string) $field->value($record))
    @if(filled(trim(strip_tags($html))) || \Illuminate\Support\Str::contains($html, ['<img', '<iframe', '<hr', '<table']))
        <div class="y-detail__html">{!! $html !!}</div>
    @else<span class="y-muted">—</span>@endif
@else
    @php($text = $field->indexValue($record))
    @if(filled($text))<div class="y-detail__text">{{ $text }}</div>@else<span class="y-muted">—</span>@endif
@endif
