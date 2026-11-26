<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>

    @php
        // The office can drop a photograph of the building in and it becomes
        // the ground. Until then the Center's blue carries the page.
        $background = file_exists(public_path('images/background.jpg'))
            ? asset('images/background.jpg')
            : null;
    @endphp

    <body class="min-h-screen bg-gate-paper antialiased dark:bg-gate-desk">
        <div @class(['gate relative flex min-h-dvh items-center justify-center p-4 sm:p-8', 'gate-photo' => $background])
            @if ($background) style="--gate-photo: url('{{ $background }}')" @endif>

            <div class="gate-rise relative w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl shadow-black/25 dark:bg-zinc-900">
                <div class="grid md:grid-cols-2">
                    {{-- What the system is, for whoever is about to use it.
                         The circles are the only decoration on the page. --}}
                    <div class="relative hidden flex-col justify-center overflow-hidden bg-linear-135 from-brand-primary to-blue-500 p-10 text-white md:flex">
                        <div class="pointer-events-none absolute -top-16 -right-20 size-64 rounded-full bg-white/10"></div>
                        <div class="pointer-events-none absolute -bottom-24 -left-16 size-72 rounded-full bg-white/10"></div>

                        <div class="relative space-y-5">
                            <span class="inline-block rounded-full bg-white/15 px-3 py-1 text-xs font-medium">
                                {{ __('v2.0') }}
                            </span>

                            <h1 class="text-3xl leading-tight font-semibold">{{ __('HR Training System') }}</h1>

                            <p class="text-sm leading-relaxed text-white/85">
                                {{ __('Where the Center plans its year of learning, records who attended what, and carries a training through every approval it needs.') }}
                            </p>

                            <ul class="space-y-3 text-sm">
                                @foreach ([
                                    __('Dashboards for HR, division and section heads'),
                                    __('Multi-level approval, from submission to endorsement'),
                                    __('A Personal Data Sheet that fills itself from approved training'),
                                    __('LDI planning, budgets and compliance reports'),
                                    __('A whole training history at a glance'),
                                ] as $line)
                                    <li class="flex gap-3">
                                        <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-white/70" aria-hidden="true"></span>
                                        <span class="text-white/90">{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="flex flex-col justify-center gap-6 p-8 sm:p-10">
                        <x-seals class="justify-center" />

                        {{ $slot }}

                        {{-- Every account passes this door before it sees anyone's
                             personal data, so the duty of confidentiality is read here.
                             The agreement page already shows it in full. --}}
                        @unless (request()->routeIs('confidentiality'))
                        <p class="text-center text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Authorized users only.') }}
                            {{ __('By using this system, you agree to its') }}
                            {{-- flux:modal.trigger wraps in a div, which a <p> cannot hold. --}}
                            <button type="button" x-data x-on:click="$dispatch('modal-show', { name: 'confidentiality' })"
                                class="cursor-pointer font-medium text-[var(--color-accent-content)] hover:underline">{{ __('Statement of Confidentiality') }}</button>.
                        </p>

                        <flux:modal name="confidentiality" class="max-w-lg">
                            <div class="space-y-4">
                                <flux:heading size="lg">{{ __('Statement of Confidentiality') }}</flux:heading>

                                <x-confidentiality-statement />

                                <div class="flex justify-end">
                                    <flux:modal.close>
                                        <flux:button variant="primary">{{ __('I understand') }}</flux:button>
                                    </flux:modal.close>
                                </div>
                            </div>
                        </flux:modal>
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        {{-- A signed-in page on this layout (the confidentiality agreement,
             confirm password) times out like any other. --}}
        @auth
            <x-idle-logout />
        @endauth

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
