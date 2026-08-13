{{-- Renders a list of fields, grouped by their optional section heading. --}}
@php
    $bySection = [];
    foreach ($fields as $f) {
        $bySection[$f->section ?? ''][] = $f;
    }
@endphp

@foreach($bySection as $sectionName => $sectionFields)
    @if($sectionName !== '')
        <h3 class="y-section__title">{{ $sectionName }}</h3>
    @endif

    @foreach($sectionFields as $field)
        <div class="y-field {{ $errors->has($field->name) ? 'has-error' : '' }}"
             @if($field->condition)
                 data-when-field="{{ $field->condition['field'] }}"
                 data-when-values='@json($field->condition['values'])'
             @endif>
            <label for="{{ $field->name }}">{{ $field->label }}</label>
            @include($field->component(), ['field' => $field, 'record' => $record])
            @error($field->name)<p class="y-error">{{ $message }}</p>@enderror
            @if($field->help)<p class="y-help">{{ $field->help }}</p>@endif
        </div>
    @endforeach
@endforeach
