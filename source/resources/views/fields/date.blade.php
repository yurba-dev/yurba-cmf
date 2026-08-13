<input type="{{ $field->withTime ? 'datetime-local' : 'date' }}"
       id="{{ $field->name }}"
       name="{{ $field->name }}"
       value="{{ $field->formValue($record) }}"
       class="y-input">
