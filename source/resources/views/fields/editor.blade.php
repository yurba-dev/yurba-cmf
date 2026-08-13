@php
    $driver = config('yurba.editor.driver', 'yurba');
    $isYurba = $driver === 'yurba';
    $value = $field->sanitize($field->formValue($record));
@endphp

{{-- The base control is always a plain <textarea> (so it posts, and degrades
     gracefully). Which editor enhances it is chosen by the yurba.editor.driver
     config: 'yurba' (bundled), 'none' (plain), or 'custom' (bring your own,
     targeting `textarea[data-editor]`). --}}
<textarea id="{{ $field->name }}"
          name="{{ $field->name }}"
          class="y-input y-textarea y-editor"
          data-editor
          @if($isYurba)
          data-yurba-editor
          data-toolbar="{{ implode(' ', $field->toolbar) }}"
          data-min-height="{{ (int) $field->minHeight }}"
          @if($field->maxChars) data-max-chars="{{ (int) $field->maxChars }}" @endif
          @if($field->uploads)
          data-upload-url="{{ route('yurba.editor.upload') }}"
          data-upload-csrf="{{ csrf_token() }}"
          data-max-image-kb="{{ (int) config('yurba.uploads.max_kb', 4096) }}"
          @endif
          @endif
          @if($field->placeholder) data-placeholder="{{ $field->placeholder }}" placeholder="{{ $field->placeholder }}" @endif
          rows="{{ (int) max(6, $field->minHeight / 28) }}">{{ $value }}</textarea>

@if($driver === 'custom')
    @once
        @foreach((array) config('yurba.editor.styles', []) as $href)
            <link rel="stylesheet" href="{{ $href }}">
        @endforeach
        @foreach((array) config('yurba.editor.scripts', []) as $src)
            <script src="{{ $src }}" defer></script>
        @endforeach
        @if($init = config('yurba.editor.init'))
            <script>{!! $init !!}</script>
        @endif
    @endonce
@endif
