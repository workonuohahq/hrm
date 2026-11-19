<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        (function () {
            const appearance = @json($appearance ?? 'system');
            if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <style>
        html { background-color: oklch(1 0 0); }
        html.dark { background-color: oklch(0.145 0 0); }
    </style>

    @if(request()->routeIs('home') || request()->routeIs('custom-page.show') || request()->routeIs('login') || request()->routeIs('register'))
        @php($seoSettings = get_seo_setting())
        <meta name="title" content="{{ $seoSettings['meta_title'] ?? '' }}">
        <meta name="description" content="{{ $seoSettings['meta_description'] ?? '' }}">
        <meta name="keywords" content="{{ $seoSettings['meta_keywords'] ?? '' }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="{{ $seoSettings['meta_title'] ?? '' }}">
        <meta property="og:description" content="{{ $seoSettings['meta_description'] ?? '' }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:url" content="{{ url()->current() }}">
        <meta name="twitter:title" content="{{ $seoSettings['meta_title'] ?? '' }}">
        <meta name="twitter:description" content="{{ $seoSettings['meta_description'] ?? '' }}">
        @if(!empty($seoSettings['meta_image']))
            <meta name="twitter:image" content="{{ $seoSettings['meta_image'] }}">
            <meta property="og:image" content="{{ $seoSettings['meta_image'] }}">
        @endif
    @endif

    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    @routes
    @if(app()->environment('local'))
        @viteReactRefresh
    @endif
    @vite(['resources/css/app.css', 'resources/css/dark-mode.css', 'resources/js/app.tsx'])
    <script>
        window.baseUrl = @json(url('/'));
        window.asset = (path) => @json(asset('')) + path;
        window.storage = (path) => @json(asset('storage')) + '/' + path;
        fetch(@json(route('initial-locale')))
            .then((response) => response.text())
            .then((locale) => { window.initialLocale = locale; })
            .catch(() => { window.initialLocale = 'en'; });
    </script>
    @inertiaHead
</head>
<body class="font-sans antialiased !mb-0">
    @inertia
</body>
</html>
