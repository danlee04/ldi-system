@props([
    'code',
    'title',
    /** The main way out. Home sends a signed-in person to the dashboard and
        anybody else to sign-in, and needs no session to work out which. */
    'actionLabel' => null,
    'actionHref' => null,
    /** Off where going back cannot help, as during maintenance. */
    'back' => true,
])

@php
    $actionLabel ??= __('Go to dashboard');
    $actionHref ??= route('home');

    // The building photo, once the office drops one in, as on sign-in.
    $background = file_exists(public_path('images/background.jpg'))
        ? asset('images/background.jpg')
        : null;
@endphp

{{-- Every error page wears the sign-in page's ground and card, so a dead
     end still looks like this system rather than the framework.

     Nothing here may need a session, the database or Livewire: a mistyped
     address arrives before the session starts, and a 500 can be the
     database itself. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>

    <body class="min-h-screen bg-gate-paper antialiased dark:bg-gate-desk">
        <main @class(['gate relative flex min-h-dvh items-center justify-center p-4 sm:p-8', 'gate-photo' => $background])
            @if ($background) style="--gate-photo: url('{{ $background }}')" @endif>

            <div class="gate-rise relative w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl shadow-black/25 dark:bg-zinc-900">
                <div class="grid md:grid-cols-[2fr_3fr]">
                    {{-- The code, where sign-in says what the system is. A band
                         across the top on a phone. The gradient stops short of
                         sign-in's lighter blue so the small line keeps 4.5:1,
                         and the circles keep clear of the text. --}}
                    <div class="relative flex items-center justify-between gap-4 overflow-hidden bg-linear-135 from-brand-primary to-blue-600 px-6 py-5 text-white md:flex-col md:items-start md:justify-center md:p-10">
                        <div class="pointer-events-none absolute -top-16 -right-20 hidden size-64 rounded-full bg-white/10 md:block"></div>
                        <div class="pointer-events-none absolute -right-16 -bottom-16 hidden size-48 rounded-full bg-white/10 md:block"></div>

                        <p class="relative text-6xl leading-none font-semibold tracking-tight tabular-nums md:text-8xl">
                            <span class="sr-only">{{ __('Error') }}</span>
                            {{ $code }}
                        </p>

                        <p class="relative text-sm font-medium md:mt-4">{{ __('HR Training System') }}</p>
                    </div>

                    <div class="flex flex-col gap-6 p-6 sm:p-10">
                        <x-seals />

                        <div class="space-y-2">
                            <flux:heading level="1" size="xl">{{ $title }}</flux:heading>

                            <flux:text>{{ $slot }}</flux:text>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <flux:button variant="primary" :href="$actionHref">{{ $actionLabel }}</flux:button>

                            @if ($back)
                                <flux:button :href="url()->previous(route('home'))" icon="arrow-left">
                                    {{ __('Go back') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>
