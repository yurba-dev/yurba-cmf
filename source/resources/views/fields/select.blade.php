@php($value = (string) $field->formValue($record))
@php($ui = (bool) config('yurba.ui.select', true))
<select id="{{ $field->name }}" name="{{ $field->name }}" class="y-input{{ $ui ? '' : ' y-select' }}" @if($ui && ! $field->readonly) data-yurba-select @endif @if($field->readonly) disabled @endif>
    @if($field->nullable)<option value="">—</option>@endif
    @foreach($field->options as $val => $label)
        <option value="{{ $val }}" {{ $value === (string) $val ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
</select>
@if($ui)@include('yurba::partials.ui-select')@endif
