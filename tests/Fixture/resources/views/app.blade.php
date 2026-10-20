@php
    $buildDirectory = 'vendor/storefront-fixture';
    if (!is_dir(public_path($buildDirectory))) {
        throw new \RuntimeException('Active theme path not found.');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page['props']['meta']['title'] ?? setting('site_name', 'Restaurant') }}</title>
    @if(!empty($page['props']['meta']['description']))
        <meta name="description" content="{{ $page['props']['meta']['description'] }}" data-inertia="description">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ \Igniter\Storefront\Support\ThemeFont::stylesheet() }}" rel="stylesheet">
    @if($font = \Igniter\Storefront\Support\ThemeFont::css())
        <style>{!! $font !!}</style>
    @endif
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
