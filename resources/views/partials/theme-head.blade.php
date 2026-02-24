@php
    $themeScriptPath = public_path('js/theme.js');
@endphp
@if (file_exists($themeScriptPath))
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime($themeScriptPath) }}"></script>
@endif
