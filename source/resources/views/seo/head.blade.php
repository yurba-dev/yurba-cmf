{{-- SEO meta tags. Pass $model (+ optional $fallback: title/description/image),
     or a pre-resolved $seo array. Include inside your frontend <head>. --}}
@php($__seo = $seo ?? \Yurba\Cmf\Seo\Seo::resolve($model, $fallback ?? []))
@if(! empty($__seo['title']))
    <title>{{ $__seo['title'] }}</title>
    <meta property="og:title" content="{{ $__seo['title'] }}">
    <meta name="twitter:title" content="{{ $__seo['title'] }}">
@endif
@if(! empty($__seo['description']))
    <meta name="description" content="{{ $__seo['description'] }}">
    <meta property="og:description" content="{{ $__seo['description'] }}">
    <meta name="twitter:description" content="{{ $__seo['description'] }}">
@endif
@if(! empty($__seo['image']))
    <meta property="og:image" content="{{ \Illuminate\Support\Str::startsWith($__seo['image'], 'http') ? $__seo['image'] : url($__seo['image']) }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ \Illuminate\Support\Str::startsWith($__seo['image'], 'http') ? $__seo['image'] : url($__seo['image']) }}">
@endif
@if(! empty($__seo['noindex']))
    <meta name="robots" content="noindex, nofollow">
@endif
<meta property="og:url" content="{{ url()->current() }}">
