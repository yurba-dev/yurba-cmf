@php($ui = (bool) config('yurba.ui.select', true))
<label class="y-filter">
    <span class="y-filter__label">{{ $filter->label }}</span>
    <select name="f[{{ $filter->key }}]" class="y-input y-filter__control" @if($ui) data-yurba-select @endif>
        <option value="">All</option>
        @foreach($filter->getOptions() as $val => $label)
            <option value="{{ $val }}" {{ (string) $value === (string) $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</label>
