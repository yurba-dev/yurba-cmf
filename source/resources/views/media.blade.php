@extends('yurba::layout')

@section('title', __('Media'))
@section('heading', __('Media'))
@section('titleCount', number_format($media->total()))

@section('content')
    <div class="y-toolbar">
        <form method="GET" action="{{ route('yurba.media') }}" class="y-toolbar__search">
            <input type="search" name="q" value="{{ $search }}" class="y-filters__search" placeholder="{{ __('Search media…') }}">
        </form>
        <span class="y-toolbar__actions">
            <form method="POST" action="{{ route('yurba.media.store') }}" enctype="multipart/form-data" id="y-media-upload" class="y-inline">
                @csrf
                <input type="file" name="files[]" id="y-media-files" multiple class="y-media-upload__input"
                       accept="image/*,application/pdf">
                <label for="y-media-files" class="y-btn y-btn__primary">
                    {!! \Yurba\Cmf\Facades\Yurba::icon('upload') !!} {{ __('Upload') }}
                </label>
                <noscript><button type="submit" class="y-btn y-btn__ghost">{{ __('Upload selected') }}</button></noscript>
            </form>
        </span>
    </div>

    @if($media->total() == 0)
        <p class="y-muted">{{ __('No media yet. Upload images or files to reuse across the panel: copy a URL and paste it into any image or link field.') }}</p>
    @else
        <div class="y-media-grid">
            @foreach($media as $item)
                <div class="y-media-card">
                    <div class="y-media-card__preview">
                        @if($item->isImage())
                            <img src="{{ $item->thumb_url }}" data-full="{{ $item->url }}" alt="{{ $item->name }}" loading="lazy">
                        @else
                            {!! \Yurba\Cmf\Facades\Yurba::icon('description', 'y-media-card__icon') !!}
                        @endif
                    </div>
                    <div class="y-media-card__body">
                        <div class="y-media-card__name" title="{{ $item->name }}">{{ $item->name }}</div>
                        <div class="y-media-card__meta">
                            {{ $item->humanSize() }}@if($item->width) · {{ $item->width }}×{{ $item->height }}@endif
                        </div>
                    </div>
                    <div class="y-media-card__actions">
                        <button type="button" class="y-btn y-btn__ghost y-btn__xs y-btn__icon" data-copy="{{ $item->url }}" title="{{ __('Copy URL') }}" aria-label="{{ __('Copy URL') }}">
                            {!! \Yurba\Cmf\Facades\Yurba::icon('link') !!}
                        </button>
                        <a href="{{ $item->url }}" target="_blank" rel="noopener" class="y-btn y-btn__ghost y-btn__xs y-btn__icon" title="{{ __('Open') }}" aria-label="{{ __('Open') }}">
                            {!! \Yurba\Cmf\Facades\Yurba::icon('open_in_new') !!}
                        </a>
                        <form method="POST" action="{{ route('yurba.media.destroy', $item->id) }}" onsubmit="return confirm(@js(__('Delete this file?')));" class="y-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="y-btn y-btn__danger y-btn__xs y-btn__icon" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                {!! \Yurba\Cmf\Facades\Yurba::icon('delete') !!}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="y-pagination">{{ $media->links('yurba::pagination') }}</div>

        @if(config('yurba.ui.viewer', true))
            @include('yurba::partials.ui-viewer')
        @endif
    @endif

@endsection
