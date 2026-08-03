<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Static OG tags — crawlers (Facebook, WhatsApp, Telegram) don't run JS -->
    <meta name="description" content="Sincroniza tus contribuyentes con el SRI y obtén compras, ventas y retenciones organizadas automáticamente. Sin importaciones manuales ni horas perdidas." />
    <link rel="canonical" href="{{ url()->current() }}" />

    <meta property="og:title" content="Declárame — Sincroniza el SRI y declara con confianza" />
    <meta property="og:description" content="Conecta tus contribuyentes al SRI y ten compras, ventas y retenciones organizadas en segundos. La herramienta que todo contador ecuatoriano necesita." />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:image" content="{{ asset('og-image.jpg') }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:locale" content="es_EC" />
    <meta property="og:site_name" content="Declárame" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Declárame — Sincroniza el SRI y declara con confianza" />
    <meta name="twitter:description" content="Conecta tus contribuyentes al SRI y ten compras, ventas y retenciones organizadas en segundos. La herramienta que todo contador ecuatoriano necesita." />
    <meta name="twitter:image" content="{{ asset('og-image.jpg') }}" />

    <meta name="theme-color" content="#059669" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />

    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "SoftwareApplication",
        "name": "Declárame",
        "description": "Sincroniza tus contribuyentes con el SRI y obtén compras, ventas y retenciones organizadas automáticamente.",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "url": "{{ url('/') }}"
    }
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <script>
        (function() {
            const stored = localStorage.getItem('theme') ?? 'system'
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
            const theme = stored === 'system' ? (prefersDark ? 'dark' : 'light') : stored
            document.documentElement.classList.add(theme)
        })()
    </script>

    @routes
    @vite(['resources/js/app.ts'])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>