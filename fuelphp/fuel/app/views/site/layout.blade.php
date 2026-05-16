<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Shop' }}</title>
    {!! Vite::asset('src/site/core.js') !!}
    {!! Vite::asset('src/site/pages/search.js') !!}
</head>
<body>
    @include('site.partials.header')
    <main class="site-main">
        @yield('content')
    </main>
    @include('site.partials.footer')
</body>
</html>
