<textarea id="{{ $field->name }}"
          name="{{ $field->name }}"
          rows="{{ $field->rows ?? 5 }}"
          @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
          @if($field->readonly) readonly @endif
          class="y-input y-textarea">{{ $field->formValue($record) }}</textarea>
