@php
    $link = fn (string $f) => route('yurba.page.show', 'media-usage').($f ? '?filter='.$f : '');
    $handle = route('yurba.page.handle', 'media-usage');
    $ic = $yurbaActionIcons ?? false;
@endphp

<div class="y-usage">
    <div class="y-usage__intro">
        <span>{{ __('Preset thumbnails (generated on demand):') }}</span>
        @foreach($presets as $name => $w)
            <span class="y-pill y-pill--muted">{{ $name }} {{ $w }}px</span>
        @endforeach
    </div>

    <div class="y-toolbar">
        <span class="y-usage__globals">
            <form method="POST" action="{{ $handle }}"
                  onsubmit="return confirm('{{ __('Generate all missing thumbnails? This may take a while.') }}')">
                @csrf
                <button type="submit" name="action" value="generate_all" class="y-btn y-btn__primary y-btn__xs">{{ __('Generate all missing') }}</button>
            </form>
            @if($counts['unused'] > 0)
                <form method="POST" action="{{ $handle }}"
                      onsubmit="return confirm('{{ __('Delete all unused images? This removes the files.') }}')">
                    @csrf
                    <button type="submit" name="action" value="delete_unused" class="y-btn y-btn__danger y-btn__xs">{{ __('Delete unused') }} ({{ $counts['unused'] }})</button>
                </form>
            @endif
            @if($counts['all'] > 0)
                <form method="POST" action="{{ $handle }}"
                      onsubmit="return confirm('{{ __('Delete all cached thumbnails? They will be regenerated on demand.') }}')">
                    @csrf
                    <button type="submit" name="action" value="delete_thumbs_all" class="y-btn y-btn__ghost y-btn__xs">{{ __('Delete thumbnails') }}</button>
                </form>
            @endif
        </span>
        <span class="y-toolbar__actions">
            <a href="{{ $link('') }}" class="y-btn y-btn__ghost y-btn__xs {{ $filter === '' ? 'is-active' : '' }}">{{ __('All') }} ({{ $counts['all'] }})</a>
            <a href="{{ $link('missing') }}" class="y-btn y-btn__ghost y-btn__xs {{ $filter === 'missing' ? 'is-active' : '' }}">{{ __('Missing thumbnails') }} ({{ $counts['missing'] }})</a>
            <a href="{{ $link('unused') }}" class="y-btn y-btn__ghost y-btn__xs {{ $filter === 'unused' ? 'is-active' : '' }}">{{ __('Unused') }} ({{ $counts['unused'] }})</a>
        </span>
    </div>

    <form method="POST" action="{{ $handle }}" id="y-bulk-form" class="y-bulkbar" hidden>
        @csrf
        <input type="hidden" name="action" value="">
        <span class="y-bulkbar__count">0 selected</span>
        <span class="y-bulkbar__actions">
            <button type="submit" class="y-btn y-btn__ghost y-btn__xs" data-bulk="generate">{{ __('Generate') }}</button>
            <button type="submit" class="y-btn y-btn__ghost y-btn__xs" data-bulk="delete_thumbs"
                    onclick="return confirm('{{ __('Delete cached thumbnails for the selected images?') }}')">{{ __('Delete thumbnails') }}</button>
            <button type="submit" class="y-btn y-btn__danger y-btn__xs" data-bulk="delete_media"
                    onclick="return confirm('{{ __('Delete the selected images? This removes the files.') }}')">{{ __('Delete') }}</button>
        </span>
    </form>

    <div class="y-table-wrap">
        <table class="y-table">
            <thead>
                <tr>
                    <th class="y-col-check"><input type="checkbox" id="y-check-all" aria-label="{{ __('Select all') }}"></th>
                    <th>{{ __('Image') }}</th>
                    <th>{{ __('Used in') }}</th>
                    <th>{{ __('Thumbnails') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    @php $m = $r['media']; @endphp
                    <tr>
                        <td class="y-col-check">
                            <input type="checkbox" class="y-row-check" name="ids[]" value="{{ $m->id }}" form="y-bulk-form" aria-label="{{ __('Select row') }}">
                        </td>
                        <td>
                            <div class="y-usage__media">
                                <a href="{{ $m->url }}" target="_blank" rel="noopener">
                                    <img src="{{ \Yurba\Cmf\Media\Media::thumb($m->url, 'small') }}" data-full="{{ $m->url }}" alt="" class="y-thumb"
                                         onerror="this.onerror=null;this.src='{{ $m->url }}'">
                                </a>
                                <div>
                                    <code>{{ \Illuminate\Support\Str::afterLast($m->path, '/') }}</code>
                                    <div class="y-help">{{ $m->width && $m->height ? $m->width.'×'.$m->height : '' }} · {{ $m->humanSize() }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($r['uses'])
                                @foreach($r['uses'] as $u)
                                    <div>
                                        @if($u['url'])
                                            <a href="{{ $u['url'] }}">{{ $u['resource'] }}: {{ \Illuminate\Support\Str::limit($u['title'], 40) }}</a>
                                        @else
                                            {{ $u['resource'] }}: {{ \Illuminate\Support\Str::limit($u['title'], 40) }}
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <span class="y-muted">{{ __('Unused') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="y-usage__thumbs">
                                @foreach($r['thumbs'] as $name => $t)
                                    <span class="y-pill {{ $t['exists'] ? 'y-pill--ok' : 'y-pill--muted' }}" title="{{ $name }} — {{ $t['width'] }}px">{{ $name }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <div class="y-col-actions">
                                @if($r['missing'])
                                    <form method="POST" action="{{ $handle }}" class="y-inline">
                                        @csrf
                                        <input type="hidden" name="action" value="generate">
                                        <input type="hidden" name="id" value="{{ $m->id }}">
                                        <button type="submit" class="y-btn y-btn__ghost y-btn__xs {{ $ic ? 'y-btn__icon' : '' }}" @if($ic) title="{{ __('Generate') }}" aria-label="{{ __('Generate') }}" @endif>@include('yurba::partials.action-inner', ['icon' => 'add_photo_alternate', 'text' => __('Generate')])</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ $handle }}" class="y-inline"
                                      onsubmit="return confirm('{{ __('Delete the cached thumbnails for this image?') }}')">
                                    @csrf
                                    <input type="hidden" name="action" value="delete_thumbs">
                                    <input type="hidden" name="id" value="{{ $m->id }}">
                                    <button type="submit" class="y-btn y-btn__ghost y-btn__xs {{ $ic ? 'y-btn__icon' : '' }}" @if($ic) title="{{ __('Delete thumbnails') }}" aria-label="{{ __('Delete thumbnails') }}" @endif>@include('yurba::partials.action-inner', ['icon' => 'hide_image', 'text' => __('Delete thumbnails')])</button>
                                </form>
                                <form method="POST" action="{{ $handle }}" class="y-inline"
                                      onsubmit="return confirm('{{ __('Delete this image? This removes the file.') }}')">
                                    @csrf
                                    <input type="hidden" name="action" value="delete_media">
                                    <input type="hidden" name="id" value="{{ $m->id }}">
                                    <button type="submit" class="y-btn y-btn__danger y-btn__xs {{ $ic ? 'y-btn__icon' : '' }}" @if($ic) title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}" @endif>@include('yurba::partials.action-inner', ['icon' => 'delete', 'text' => __('Delete')])</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="y-empty-row">{{ __('No images match this filter.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <div class="y-pagination">{{ $rows->links('yurba::pagination') }}</div>
    @endif

    @if(config('yurba.ui.viewer', true))
        @include('yurba::partials.ui-viewer')
    @endif
</div>
