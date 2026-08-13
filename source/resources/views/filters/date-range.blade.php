<span class="y-filter">
    <span class="y-filter__label">{{ $filter->label }}</span>
    <span class="y-filter__range">
        <input type="date" name="f[{{ $filter->key }}][from]" value="{{ is_array($value) ? ($value['from'] ?? '') : '' }}"
               class="y-input y-filter__control" aria-label="{{ $filter->label }} from">
        <span class="y-filter__dash">—</span>
        <input type="date" name="f[{{ $filter->key }}][to]" value="{{ is_array($value) ? ($value['to'] ?? '') : '' }}"
               class="y-input y-filter__control" aria-label="{{ $filter->label }} to">
    </span>
</span>
