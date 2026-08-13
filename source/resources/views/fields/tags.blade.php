<input type="text"
       id="{{ $field->name }}"
       name="{{ $field->name }}"
       value="{{ $field->formValue($record) }}"
       placeholder="{{ $field->placeholder ?? 'comma, separated, values' }}"
       class="y-input">
