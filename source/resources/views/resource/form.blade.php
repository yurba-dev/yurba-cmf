@extends('yurba::layout')

@php $editing = $record->exists; @endphp
@section('title', ($editing ? 'Edit ' : 'New ').$res->label())
@section('heading', $editing ? 'Edit '.$res->label().': '.$res->title($record) : 'New '.$res->label())

@section('content')
    @php
        // Group form fields by tab (preserving order); a blank tab is the implicit
        // "General" tab. Grouping is purely presentational - the flat field list
        // still drives validation and persistence.
        $formFields = $res->formFields();
        $tabs = [];
        foreach ($formFields as $f) {
            $tabs[$f->tab ?? ''][] = $f;
        }
        $tabNames = array_keys($tabs);
        $hasTabs = count($tabNames) > 1 || (count($tabNames) === 1 && $tabNames[0] !== '');
    @endphp

    @if($editing && $res->hasRevisions())
        @php($revisions = $res->revisions($record))
        @php($uiSelect = (bool) config('yurba.ui.select', true))
        @if($revisions->isNotEmpty())
            <div class="y-revisions {{ ($loadedRevision ?? null) ? 'is-loaded' : '' }}">
                <form method="GET" action="{{ route('yurba.resource.edit', [$res->uriKey(), $record->getKey()]) }}" class="y-revisions__pick">
                    <label for="y-revision-select" class="y-revisions__label">
                        <span class="material-symbols-rounded" aria-hidden="true">history</span> Revision
                    </label>
                    <select id="y-revision-select" name="revision" class="y-input" onchange="this.form.submit()" @if($uiSelect) data-yurba-select @endif>
                        <option value="">Current (latest)</option>
                        @foreach($revisions as $rev)
                            <option value="{{ $rev->id }}" @selected(($loadedRevision ?? null) && $loadedRevision->id === $rev->id)>{{ $rev->created_at?->format('Y-m-d H:i') }} · {{ $rev->user_name ?? '—' }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="y-btn y-btn__ghost y-btn__xs">Load</button></noscript>
                </form>
                @if($loadedRevision ?? null)
                    <p class="y-revisions__note">
                        Editing a revision from <strong>{{ $loadedRevision->created_at }}</strong>. Save to make it the current version.
                        <a href="{{ route('yurba.resource.edit', [$res->uriKey(), $record->getKey()]) }}" class="y-btn y-btn__ghost y-btn__xs">Discard</a>
                    </p>
                @endif
                @if($uiSelect)@include('yurba::partials.ui-select')@endif
            </div>
        @endif
    @endif

    <form method="POST"
          action="{{ $editing ? route('yurba.resource.update', [$res->uriKey(), $record->getKey()]) : route('yurba.resource.store', $res->uriKey()) }}"
          enctype="multipart/form-data" class="y-form y-form--card">
        @csrf
        @if($editing) @method('PUT') @endif

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
            @include('yurba::resource._fields', ['fields' => $formFields, 'record' => $record])
        @endif

        <div class="y-form__actions">
            @if($editing)
                <button type="submit" name="after" value="edit" class="y-btn y-btn__primary">Save</button>
                <button type="submit" name="after" value="index" class="y-btn y-btn__ghost">Save and exit</button>
            @else
                <button type="submit" class="y-btn y-btn__primary">Create</button>
            @endif
            <a href="{{ route('yurba.resource.index', $res->uriKey()) }}" class="y-btn y-btn__ghost">Cancel</a>
            @if($editing && ($preview = $res->previewUrl($record)))
                <a href="{{ $preview }}" target="_blank" rel="noopener" class="y-btn y-btn__ghost">
                    <span class="material-symbols-rounded">visibility</span> Preview
                </a>
            @endif
        </div>
    </form>

@endsection
