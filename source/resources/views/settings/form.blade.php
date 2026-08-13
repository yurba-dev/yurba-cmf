@extends('yurba::layout')

@section('title', $page->label().' settings')
@section('heading', $page->label().' settings')

@section('content')
    <form method="POST"
          action="{{ route('yurba.settings.save', $page->uriKey()) }}"
          enctype="multipart/form-data" class="y-form y-form--card">
        @csrf
        @method('PUT')

        @foreach($page->fields() as $field)
            <div class="y-field {{ $errors->has($field->name) ? 'has-error' : '' }}">
                <label for="{{ $field->name }}">{{ $field->label }}</label>
                @include($field->component(), ['field' => $field, 'record' => $record])
                @error($field->name)<p class="y-error">{{ $message }}</p>@enderror
                @if($field->help)<p class="y-help">{{ $field->help }}</p>@endif
            </div>
        @endforeach

        <div class="y-form__actions">
            <button type="submit" class="y-btn y-btn__primary">Save changes</button>
        </div>
    </form>
@endsection
