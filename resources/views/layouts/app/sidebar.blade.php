<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    {{-- The nav carries the Center's blue as a surface. Flux's own item
         colours are repainted for it in app.css, outside every layer. --}}
    <flux:sidebar sticky collapsible
        class="w-64 print:hidden border-e border-white/10 bg-(--color-sidebar)">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />

            {{-- No breakpoint on it: the sidebar folds to its icons on a
                 desktop as well as stashing itself on a phone. --}}
            <flux:sidebar.collapse />
        </flux:sidebar.header>

        {{-- Grouped by the thing being worked on rather than by who may see
             it: everything of a person's own together, everything about
             training together, everything about the needs assessment
             together. Each group is shown when at least one of its items is,
             so nobody is given an empty heading. --}}
        <flux:sidebar.nav>
            <x-sidebar-group :heading="__('Overview')">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="calendar-days" :href="route('calendar')"
                    :current="request()->routeIs('calendar')" wire:navigate>
                    {{ __('Calendar') }}
                </flux:sidebar.item>

                {{-- The roster is looked up as often as the dashboard is
                     read, so it sits with the two pages everybody starts
                     from rather than under a heading of its own. --}}
                @if (auth()->user()->role !== App\Enums\UserRole::Employee)
                    <flux:sidebar.item icon="users" :href="route('employees.index')"
                        :current="request()->routeIs('employees.*')" wire:navigate>
                        {{ __('Employees') }}
                    </flux:sidebar.item>
                @endif
            </x-sidebar-group>

            {{-- All four need an employee record behind them, so an
                 administrative account is not offered a profile it would
                 only be refused. --}}
            @if (auth()->user()->employee !== null)
                {{-- My profile is not here: it sits at the foot of the nav,
                     beside the name it is about. --}}
                <x-sidebar-group :heading="__('Mine')">
                    <flux:sidebar.item icon="identification" :href="route('my-pds')"
                        :current="request()->routeIs('my-pds')" wire:navigate>
                        {{ __('My PDS') }}
                    </flux:sidebar.item>

                    @if (auth()->user()->hasOwnTrainings())
                        <flux:sidebar.item icon="academic-cap" :href="route('trainings.mine')"
                            :current="request()->routeIs('trainings.*')" wire:navigate>
                            {{ __('My trainings') }}
                        </flux:sidebar.item>
                    @endif

                    <flux:sidebar.item icon="clipboard-document-list" :href="route('ldna.mine')"
                        :current="request()->routeIs('ldna.mine')" wire:navigate>
                        {{ __('My LDNA') }}
                    </flux:sidebar.item>
                </x-sidebar-group>
            @endif

            {{-- Whoever decides on a training is HR, admin, or a designated
                 head, which is the same test the approvals queue uses. --}}
            @if (auth()->user()->decidesOnTrainings())
                <x-sidebar-group :heading="__('Training')">
                    {{-- The count sits here rather than on the dashboard, so it
                         is in front of the approver on every page instead of
                         only on the one they land on. --}}
                    @php($pendingDecisions = app(App\Actions\Training\CountPendingDecisions::class)->handle(auth()->user()))

                    <flux:sidebar.item icon="check-badge" :href="route('approvals')"
                        :current="request()->routeIs('approvals')"
                        :badge="$pendingDecisions > 0 ? $pendingDecisions : null" badge-color="amber" wire:navigate>
                        {{ __('Approvals') }}
                    </flux:sidebar.item>

                    @if (auth()->user()->isAdminOrHr())
                        <flux:sidebar.item icon="presentation-chart-bar" :href="route('ldi.index')"
                            :current="request()->routeIs('ldi.*')" wire:navigate>
                            {{ __('LDI trainings') }}
                        </flux:sidebar.item>

                    @endif
                </x-sidebar-group>

                <x-sidebar-group :heading="__('LDNA')">
                    {{-- The same people approve a training and confirm an
                         assessment, so the same test decides who is
                         offered it. --}}
                    @php($ldnaDue = app(App\Actions\Ldna\CountLdnaConfirmationsDue::class)->handle(auth()->user()))

                    <flux:sidebar.item icon="clipboard-document-check" :href="route('ldna.confirmations')"
                        :current="request()->routeIs('ldna.confirmations', 'ldna.review')"
                        :badge="$ldnaDue > 0 ? $ldnaDue : null" badge-color="amber" wire:navigate>
                        {{ __('LDNA confirmations') }}
                    </flux:sidebar.item>

                    @if (auth()->user()->isAdminOrHr())
                        <flux:sidebar.item icon="chart-bar-square" :href="route('ldna.index')"
                            :current="request()->routeIs('ldna.index', 'ldna.show')" wire:navigate>
                            {{ __('Cycles') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="puzzle-piece" :href="route('setup.competencies')"
                            :current="request()->routeIs('setup.competencies')" wire:navigate>
                            {{ __('Competencies') }}
                        </flux:sidebar.item>
                    @endif
                </x-sidebar-group>
            @endif

            {{-- The shape of the office and the money behind it: the things
                 set once and then read against. User accounts is not here —
                 it is the administrator's own tool and sits at the foot
                 beside their name. --}}
            @if (auth()->user()->isAdminOrHr())
                <x-sidebar-group :heading="__('Setup')">
                    <flux:sidebar.item icon="building-office-2" :href="route('setup.divisions')"
                        :current="request()->routeIs('setup.divisions')" wire:navigate>
                        {{ __('Divisions') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="rectangle-group" :href="route('setup.sections')"
                        :current="request()->routeIs('setup.sections')" wire:navigate>
                        {{ __('Sections') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="identification" :href="route('setup.positions')"
                        :current="request()->routeIs('setup.positions')" wire:navigate>
                        {{ __('Positions') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="banknotes" :href="route('setup.budget-caps')"
                        :current="request()->routeIs('setup.budget-caps')" wire:navigate>
                        {{ __('Budget caps') }}
                    </flux:sidebar.item>
                </x-sidebar-group>
            @endif
        </flux:sidebar.nav>

        <flux:spacer />

        <x-desktop-user-menu class="hidden lg:flex" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden print:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <livewire:notifications />

        <flux:dropdown position="top" align="end">
            {{-- Its own component, so a photograph saved on My profile
                     appears here without a page load. --}}
            <livewire:profile-avatar />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :src="auth()->user()->employee?->photoUrl()"
                                :name="auth()->user()->employee?->personal_name ?? auth()->user()->name"
                                :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">
                                    {{ auth()->user()->employee?->personal_name ?? auth()->user()->name }}
                                </flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                @if (auth()->user()->employee !== null)
                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('my-profile')" icon="user-circle" wire:navigate>
                            {{ __('My profile') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>
                @endif

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
