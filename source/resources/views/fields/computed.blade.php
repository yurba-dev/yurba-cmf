@php($value = $field->formValue($record))
<div class="y-computed" id="{{ $field->name }}">{{ filled($value) ? $value : '—' }}</div>
