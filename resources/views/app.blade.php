<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/main.tsx'])
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
    <div id="root" data-base="{{ rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?: '', '/') }}"></div>
</body>
</html>
