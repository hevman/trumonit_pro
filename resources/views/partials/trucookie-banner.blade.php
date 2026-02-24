@php
    $trucookieSiteId = (string) config('services.trucookie.site_id', '');
    $trucookieBannerBaseUrl = (string) config('services.trucookie.banner_base_url', 'https://trucookie.pro/banner.js');
    $allowedHosts = array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        (array) config('services.trucookie.banner_allowed_hosts', [])
    )));
    $parsedBannerUrl = parse_url($trucookieBannerBaseUrl);
    $bannerScheme = strtolower((string) ($parsedBannerUrl['scheme'] ?? ''));
    $bannerHost = strtolower((string) ($parsedBannerUrl['host'] ?? ''));
    $hasAllowedBannerHost = $bannerHost !== '' && in_array($bannerHost, $allowedHosts, true);
    $bannerSeparator = str_contains($trucookieBannerBaseUrl, '?') ? '&' : '?';
    $bannerScriptSrc = $trucookieBannerBaseUrl.$bannerSeparator.'site='.rawurlencode($trucookieSiteId);
@endphp

@if ($trucookieSiteId !== '' && $bannerScheme === 'https' && $hasAllowedBannerHost)
    <script
        id="trucookie-banner"
        src="{{ $bannerScriptSrc }}"
        data-cfasync="false"
        defer
        referrerpolicy="strict-origin-when-cross-origin"
    ></script>
@endif
