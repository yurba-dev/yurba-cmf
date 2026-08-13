{{-- Progressive enhancement of native <select data-yurba-select> with
     YurbaUI.Select. The native select stays in the DOM (hidden) as the posted
     field and no-JS fallback; the pretty control syncs its value back. --}}
@once
    <script src="{{ asset('vendor/yurba/yurba-ui.min.js') }}?v={{ \Yurba\Cmf\Fields\Select::UI_ASSET_VERSION }}"></script>
@endonce
