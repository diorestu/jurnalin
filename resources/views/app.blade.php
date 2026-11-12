<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#021d4f">
        <meta name="description" content="{{ config('app.name', 'Konsulin Accurate') }} membantu pencatatan jurnal, transaksi, dan laporan keuangan perusahaan.">

        <meta property="og:type" content="website">
        <meta property="og:locale" content="id_ID">
        <meta property="og:title" content="{{ config('app.name', 'Konsulin Accurate') }}">
        <meta property="og:description" content="Pencatatan jurnal, transaksi, dan laporan keuangan perusahaan dalam satu sistem.">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="Logo {{ config('app.name', 'Konsulin Accurate') }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ config('app.name', 'Konsulin Accurate') }}">
        <meta name="twitter:description" content="Pencatatan jurnal, transaksi, dan laporan keuangan perusahaan dalam satu sistem.">
        <meta name="twitter:image" content="{{ asset('og-image.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon-192x192.png" type="image/png" sizes="192x192">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
