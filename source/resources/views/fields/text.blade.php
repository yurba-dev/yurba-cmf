<input type="{{ $field->type ?? 'text' }}"
       id="{{ $field->name }}"
       name="{{ $field->name }}"
       value="{{ $field->formValue($record) }}"
       @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
       @if($field->readonly) readonly @endif
       class="y-input">
