@foreach($data as $key => $value)
    @php($name = ($prefix ?? '') == '' ? $key : $prefix.'['.$key.']')
    @if(is_array($value))
        @include('yurba::partials.query-hidden', ['data' => $value, 'prefix' => $name])
    @else
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endif
@endforeach
