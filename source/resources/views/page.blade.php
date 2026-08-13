@extends('yurba::layout')

@section('title', $page->label())
@section('heading', $page->label())

@section('content')
    @if($slot instanceof \Illuminate\Contracts\View\View)
        {!! $slot->render() !!}
    @else
        {!! $slot !!}
    @endif
@endsection
