{{-- Click-to-zoom for the panel's image thumbnails / previews, powered by
     YurbaPV. Editor content images are excluded (they stay editable). --}}
@once
    <script src="{{ asset('vendor/yurba/yurba-pv.min.js') }}?v={{ \Yurba\Cmf\Fields\Image::VIEWER_ASSET_VERSION }}"></script>
@endonce
