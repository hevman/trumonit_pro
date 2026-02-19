@php
    $appName = config('app.name', 'TruMonit');
@endphp

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon-32x32.png') }}" type="image/png" sizes="32x32">
<link rel="icon" href="{{ asset('favicon-16x16.png') }}" type="image/png" sizes="16x16">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="application-name" content="{{ $appName }}">
<meta name="apple-mobile-web-app-title" content="{{ $appName }}">
<meta name="theme-color" content="#0b1220">

