@php
    // group fields by tab (preserving order); blank tab = the implicit first tab
    $fields = $page->fields();
    $tabs = [];
    foreach ($fields as $f) {
        $tabs[$f->tab ?? ''][] = $f;
    }
    $tabNames = array_keys($tabs);
    $hasTabs = count($tabNames) > 1 || (count($tabNames) === 1 && $tabNames[0] !== '');
@endphp

<form method="POST"
      action="{{ route('yurba.page.handle', $page->uriKey()) }}"
      enctype="multipart/form-data" class="y-form y-form--card">
    @csrf

    @if($hasTabs)
        <div class="y-tabs" role="tablist">
            @foreach($tabNames as $i => $name)
                <button type="button" class="y-tabs__tab {{ $i === 0 ? 'is-active' : '' }}" data-tab-target="y-tab-{{ $i }}">
                    {{ $name !== '' ? $name : 'General' }}
                </button>
            @endforeach
        </div>
        @foreach($tabNames as $i => $name)
            <div class="y-tabs__panel {{ $i === 0 ? 'is-active' : '' }}" id="y-tab-{{ $i }}">
                @include('yurba::resource._fields', ['fields' => $tabs[$name], 'record' => $record])
            </div>
        @endforeach
    @else
        @include('yurba::resource._fields', ['fields' => $fields, 'record' => $record])
    @endif

    <div class="y-form__actions">
        <button type="submit" class="y-btn y-btn__primary">Save changes</button>
    </div>
</form>
