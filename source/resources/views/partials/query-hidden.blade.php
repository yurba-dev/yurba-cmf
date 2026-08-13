{{-- Emit hidden inputs mirroring a (possibly nested) query array, so a GET form
     preserves the rest of the current query string. --}}
@foreach($data as $key => $value)
    @php($name = ($prefix ?? '') === '' ? $key : $prefix.'['.$key.']')
    @if(is_array($value))
        @include('yurba::partials.query-hidden', ['data' => $value, 'prefix' => $name])
    @else
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endif
@endforeach
