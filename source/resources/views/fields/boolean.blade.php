<label class="y-switch">
    <input type="hidden" name="{{ $field->name }}" value="0">
    <input type="checkbox" id="{{ $field->name }}" name="{{ $field->name }}" value="1" {{ $field->formValue($record) ? 'checked' : '' }} @if($field->readonly) disabled @endif>
    <span class="y-switch__track"><span class="y-switch__thumb"></span></span>
</label>
