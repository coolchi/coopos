<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#071c1b">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="icon" type="image/png" href="/images/coopos-logo.png">
        <link rel="apple-touch-icon" href="/images/coopos-logo.png">
        <title inertia>{{ config('app.name', 'CoopOS') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        @inertia
    </body>
</html>
