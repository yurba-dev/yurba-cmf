@php($selected = array_map('strval', (array) $field->formValue($record)))
<select id="{{ $field->name }}" name="{{ $field->name }}[]" multiple size="6" class="y-input y-multiselect"
        @if($field->readonly) disabled @endif>
    @foreach($field->options() as $val => $label)
        <option value="{{ $val }}" {{ in_array((string) $val, $selected, true) ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
</select>
