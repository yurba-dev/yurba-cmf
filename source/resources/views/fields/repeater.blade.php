@php($rows = $field->formValue($record))
@php($cols = $field->columns)
@php($stacked = $field->layout === 'stacked')

<div class="y-repeater {{ $stacked ? 'y-repeater--stacked' : '' }}" data-repeater>
    @if($stacked)
        <div class="y-repeater__items" data-repeater-body>
            @foreach($rows as $i => $row)
                <div class="y-repeater__item">
                    @foreach($cols as $key => $conf)
                        <div class="y-field">
                            <label>{{ is_array($conf) ? ($conf['label'] ?? $key) : $conf }}</label>
                            @include('yurba::fields.partials.repeater-input')
                        </div>
                    @endforeach
                    <div class="y-repeater__item-foot">
                        @if($field->withVisible)
                            <label class="y-check"><input type="checkbox" name="{{ $field->name }}[{{ $i }}][visible]" value="1" {{ ($row['visible'] ?? true) ? 'checked' : '' }}> Visible</label>
                        @else
                            <span></span>
                        @endif
                        <button type="button" class="y-btn y-btn__danger y-btn__xs" data-repeater-remove>Remove</button>
                    </div>
                </div>
            @endforeach
        </div>

        <button type="button" class="y-btn y-btn__ghost y-btn__xs" data-repeater-add>{{ $field->addLabel }}</button>

        <template data-repeater-template>
            <div class="y-repeater__item">
                @foreach($cols as $key => $conf)
                    <div class="y-field">
                        <label>{{ is_array($conf) ? ($conf['label'] ?? $key) : $conf }}</label>
                        @include('yurba::fields.partials.repeater-input', ['i' => '__i__', 'row' => []])
                    </div>
                @endforeach
                <div class="y-repeater__item-foot">
                    @if($field->withVisible)
                        <label class="y-check"><input type="checkbox" name="{{ $field->name }}[__i__][visible]" value="1" checked> Visible</label>
                    @else
                        <span></span>
                    @endif
                    <button type="button" class="y-btn y-btn__danger y-btn__xs" data-repeater-remove>Remove</button>
                </div>
            </div>
        </template>
    @else
        <div class="y-repeater__scroll">
            <table class="y-repeater__table">
                <thead>
                    <tr>
                        @foreach($cols as $key => $conf)<th>{{ is_array($conf) ? ($conf['label'] ?? $key) : $conf }}</th>@endforeach
                        @if($field->withVisible)<th class="y-repeater__vis">Visible</th>@endif
                        <th aria-hidden="true"></th>
                    </tr>
                </thead>
                <tbody data-repeater-body>
                    @foreach($rows as $i => $row)
                        <tr>
                            @foreach($cols as $key => $conf)
                                <td>@include('yurba::fields.partials.repeater-input')</td>
                            @endforeach
                            @if($field->withVisible)
                                <td class="y-repeater__vis"><input type="checkbox" name="{{ $field->name }}[{{ $i }}][visible]" value="1" {{ ($row['visible'] ?? true) ? 'checked' : '' }}></td>
                            @endif
                            <td><button type="button" class="y-btn y-btn__danger y-btn__xs" data-repeater-remove aria-label="Remove">×</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="button" class="y-btn y-btn__ghost y-btn__xs" data-repeater-add>{{ $field->addLabel }}</button>

        <template data-repeater-template>
            <tr>
                @foreach($cols as $key => $conf)
                    <td>@include('yurba::fields.partials.repeater-input', ['i' => '__i__', 'row' => []])</td>
                @endforeach
                @if($field->withVisible)
                    <td class="y-repeater__vis"><input type="checkbox" name="{{ $field->name }}[__i__][visible]" value="1" checked></td>
                @endif
                <td><button type="button" class="y-btn y-btn__danger y-btn__xs" data-repeater-remove aria-label="Remove">×</button></td>
            </tr>
        </template>
    @endif
</div>
