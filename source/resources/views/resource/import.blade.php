@extends('yurba::layout')

@section('title', __('Import :name', ['name' => $res->pluralLabel()]))
@section('heading', __('Import :name', ['name' => $res->pluralLabel()]))

@section('content')
    <form method="POST" action="{{ route('yurba.resource.import.run', $res->uriKey()) }}"
          enctype="multipart/form-data" class="y-form y-form--card">
        @csrf

        <div class="y-field {{ $errors->has('file') ? 'has-error' : '' }}">
            <label for="file">{{ __('CSV file') }}</label>
            <input type="file" id="file" name="file" accept=".csv,text/csv" class="y-input" required>
            @error('file')<p class="y-error">{{ $message }}</p>@enderror
            <p class="y-help">
                A header row is required. Rows with a matching
                <code>{{ $res->newModel()->getKeyName() }}</code> update the existing record;
                rows without one create a new record. Tip: export first to get the exact format.
            </p>
        </div>

        <div class="y-field">
            <label>{{ __('Recognised columns') }}</label>
            <p class="y-help">
                @foreach($res->exportColumns() as $col)<code>{{ $col }}</code>@if(! $loop->last), @endif @endforeach
            </p>
        </div>

        <div class="y-form__actions">
            <button type="submit" class="y-btn y-btn__primary">{{ __('Import') }}</button>
            <a href="{{ route('yurba.resource.index', $res->uriKey()) }}" class="y-btn y-btn__ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
