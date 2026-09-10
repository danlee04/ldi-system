<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gate-paper antialiased dark:bg-gate-desk">
        <div class="gate flex min-h-dvh flex-col items-center justify-center p-6">
            <div class="relative flex w-full max-w-md flex-col items-center">
                <div class="gate-rise gate-rise-1 flex flex-col items-center gap-4 text-center">
                    <x-app-logo-icon class="h-28 w-auto" />

                    <div>
                        <div class="text-lg leading-tight font-medium">{{ config('app.name') }}</div>

                        <div class="text-sm text-zinc-600 dark:text-white/70">
                            {{ __('Drug Treatment and Rehabilitation Center Caraga') }}
                        </div>
                    </div>
                </div>

                {{-- Ruled at the top in the Center's blue, the way a letterhead is
                     ruled. It is the only colour on the card that is not doing
                     a job for the form itself. --}}
                <div class="gate-rise gate-rise-2 mt-10 w-full overflow-hidden rounded-lg border border-zinc-200 border-t-brand-primary bg-white p-8 pt-7 shadow-xl shadow-black/5 [border-top-width:3px] dark:border-white/10 dark:border-t-brand-primary dark:bg-white/5 dark:shadow-black/30">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
