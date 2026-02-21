@php
    $trucookieSiteId = (string) config('services.trucookie.site_id', '');
    $trucookieBannerBaseUrl = (string) config('services.trucookie.banner_base_url', 'https://trucookie.pro/banner.js');
@endphp

@if ($trucookieSiteId !== '' && str_starts_with($trucookieBannerBaseUrl, 'https://'))
    <script
        id="trucookie-banner"
        src="{{ $trucookieBannerBaseUrl }}?site={{ rawurlencode($trucookieSiteId) }}"
        data-cfasync="false"
    ></script>
@endif
