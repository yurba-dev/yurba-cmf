@php $handle = route('yurba.page.handle', 'languages'); @endphp

<div class="y-langs">
    <p class="y-help">{{ __('Languages the frontend is served in. The slug is the URL segment, e.g. /uk/portfolio. Turn multilingual on or off under Settings -> Panel.') }}</p>

    <form method="POST" action="{{ $handle }}" class="y-form y-form--card">
        @csrf

        <table class="y-table y-langs__table">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('URL slug') }}</th>
                    <th>{{ __('Label') }}</th>
                    <th class="y-langs__default">{{ __('Default') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="y-langs-rows">
                @php $i = 0; @endphp
                @foreach($locales as $code => $def)
                    <tr class="y-langs__row">
                        <td><input type="text" name="locales[{{ $i }}][code]" value="{{ $code }}" class="y-input" maxlength="5" placeholder="uk"></td>
                        <td><input type="text" name="locales[{{ $i }}][slug]" value="{{ $def['slug'] }}" class="y-input" placeholder="uk"></td>
                        <td><input type="text" name="locales[{{ $i }}][label]" value="{{ $def['label'] }}" class="y-input" placeholder="Українська"></td>
                        <td class="y-langs__default"><input type="radio" name="default" value="{{ $code }}" @checked($code === $default)></td>
                        <td><button type="button" class="y-btn y-btn__ghost y-btn__xs" data-lang-remove>&times;</button></td>
                    </tr>
                    @php $i++; @endphp
                @endforeach
            </tbody>
        </table>

        <template id="y-langs-template">
            <tr class="y-langs__row">
                <td><input type="text" name="locales[__i__][code]" value="" class="y-input" maxlength="5" placeholder="uk"></td>
                <td><input type="text" name="locales[__i__][slug]" value="" class="y-input" placeholder="uk"></td>
                <td><input type="text" name="locales[__i__][label]" value="" class="y-input" placeholder="Українська"></td>
                <td class="y-langs__default"><input type="radio" name="default" value=""></td>
                <td><button type="button" class="y-btn y-btn__ghost y-btn__xs" data-lang-remove>&times;</button></td>
            </tr>
        </template>

        <div class="y-form__actions">
            <button type="button" id="y-langs-add" class="y-btn y-btn__ghost">{{ __('Add language') }}</button>
            <button type="submit" class="y-btn y-btn__primary">{{ __('Save') }}</button>
        </div>
    </form>
</div>

<script>
(function () {
    let rows = document.querySelector('#y-langs-rows')
    let addBtn = document.querySelector('#y-langs-add')
    let template = document.querySelector('#y-langs-template')
    let next = rows.querySelectorAll('.y-langs__row').length

    function addRow () {
        let html = template.innerHTML.replaceAll('__i__', next++)
        rows.insertAdjacentHTML('beforeend', html)
    }

    // the code field feeds the row's default radio, so a new language is selectable
    // as default without a save-and-reload first
    function syncDefaultValue (e) {
        let cell = e.target.closest('.y-langs__row')
        if (!cell || e.target.name.indexOf('[code]') < 0) {
            return
        }
        let radio = cell.querySelector('input[type="radio"]')
        radio.value = e.target.value.trim().toLowerCase()
    }

    addBtn.addEventListener('click', addRow)
    rows.addEventListener('input', syncDefaultValue)
    rows.addEventListener('click', function (e) {
        if (e.target.matches('[data-lang-remove]')) {
            e.target.closest('.y-langs__row').remove()
        }
    })
})()
</script>
