@php($name = $field->name.'['.$i.']['.$key.']')
@php($val = $row[$key] ?? '')
@if(is_array($conf) && !empty($conf['media']))
    <div class="y-media-field" data-media-field>
        <input type="hidden" name="{{ $name }}" value="{{ $val }}" data-media-input>
        <div class="y-media-field__preview {{ $val ? '' : 'is-empty' }}" data-media-preview>
            @if($val)<img src="{{ $val }}" alt="" loading="lazy">@endif
        </div>
        <div class="y-media-field__buttons">
            <button type="button" class="y-btn y-btn__ghost y-btn__xs" data-media-open>
                <span class="material-symbols-rounded">perm_media</span> Choose from library
            </button>
            <button type="button" class="y-btn y-btn__ghost y-btn__xs" data-media-clear @if(! $val) hidden @endif>Clear</button>
        </div>
    </div>
    @include('yurba::partials.media-picker')
@elseif(is_array($conf) && !empty($conf['image']))
    @if($val)
        <div style="margin-bottom:6px"><img src="{{ $val }}" alt="" style="max-height:70px;border-radius:6px;border:1px solid #e5e5ea"></div>
    @endif
    <input type="hidden" name="{{ $name }}" value="{{ $val }}">
    <input type="file" class="y-input" accept="image/*" name="{{ $name }}">
@elseif(is_array($conf) && !empty($conf['editor']))
    <textarea class="y-input y-textarea y-editor" data-editor data-yurba-editor
              data-toolbar="bold italic underline | ul ol | link | clear source"
              data-min-height="160" rows="6" name="{{ $name }}">{{ $val }}</textarea>
@elseif(is_array($conf) && !empty($conf['textarea']))
    <textarea class="y-input y-textarea" rows="4" name="{{ $name }}">{{ $val }}</textarea>
@else
    <input type="text" class="y-input" name="{{ $name }}" value="{{ $val }}">
@endif
