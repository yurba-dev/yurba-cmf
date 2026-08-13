@extends('yurba::layout')

@section('title', 'Media')
@section('heading', 'Media')
@section('titleCount', number_format($media->total()))

@section('content')
    <div class="y-toolbar">
        <form method="GET" action="{{ route('yurba.media') }}" class="y-toolbar__search">
            <input type="search" name="q" value="{{ $search }}" class="y-filters__search" placeholder="Search media…">
        </form>
        <span class="y-toolbar__actions">
            <form method="POST" action="{{ route('yurba.media.store') }}" enctype="multipart/form-data" id="y-media-upload" class="y-inline">
                @csrf
                <input type="file" name="files[]" id="y-media-files" multiple class="y-media-upload__input"
                       accept="image/*,application/pdf">
                <label for="y-media-files" class="y-btn y-btn__primary">
                    <span class="material-symbols-rounded">upload</span> Upload
                </label>
                <noscript><button type="submit" class="y-btn y-btn__ghost">Upload selected</button></noscript>
            </form>
        </span>
    </div>

    @if($media->total() === 0)
        <p class="y-muted">No media yet. Upload images or files to reuse across the panel — copy a URL and paste it into any image or link field.</p>
    @else
        <div class="y-media-grid">
            @foreach($media as $item)
                <div class="y-media-card">
                    <div class="y-media-card__preview">
                        @if($item->isImage())
                            <img src="{{ $item->thumb_url }}" data-full="{{ $item->url }}" alt="{{ $item->name }}" loading="lazy">
                        @else
                            <span class="material-symbols-rounded y-media-card__icon">description</span>
                        @endif
                    </div>
                    <div class="y-media-card__body">
                        <div class="y-media-card__name" title="{{ $item->name }}">{{ $item->name }}</div>
                        <div class="y-media-card__meta">
                            {{ $item->humanSize() }}@if($item->width) · {{ $item->width }}×{{ $item->height }}@endif
                        </div>
                    </div>
                    <div class="y-media-card__actions">
                        <button type="button" class="y-btn y-btn__ghost y-btn__xs y-btn__icon" data-copy="{{ $item->url }}" title="Copy URL" aria-label="Copy URL">
                            <span class="material-symbols-rounded">link</span>
                        </button>
                        <a href="{{ $item->url }}" target="_blank" rel="noopener" class="y-btn y-btn__ghost y-btn__xs y-btn__icon" title="Open" aria-label="Open">
                            <span class="material-symbols-rounded">open_in_new</span>
                        </a>
                        <form method="POST" action="{{ route('yurba.media.destroy', $item->id) }}" onsubmit="return confirm('Delete this file?');" class="y-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="y-btn y-btn__danger y-btn__xs y-btn__icon" title="Delete" aria-label="Delete">
                                <span class="material-symbols-rounded">delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="y-pagination">{{ $media->links('yurba::pagination') }}</div>

        {{-- Click a card's image to open it full-size in YurbaPV (when enabled). --}}
        @if(config('yurba.ui.viewer', true))
            @include('yurba::partials.ui-viewer')
        @endif
    @endif

@endsection
