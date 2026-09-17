<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="{{ asset('images/logo.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

{{-- Flux's @fonts directive is gone from here on purpose: it preloads the
     Inter it ships with, and this app sets Poppins in app.css. Leaving it
     in would fetch a typeface nothing renders. --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
