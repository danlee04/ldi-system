@php
    $idleMinutes = (int) config('session.idle_timeout');
@endphp

{{-- Signs the person out after config('session.idle_timeout') minutes with
     no mouse movement, key press, click, scroll or touch, warning them for the last minute.

     It watches the person, not the server. The notification bell polls
     every minute and each poll keeps the session alive, so a session
     lifetime alone would never see an open tab as idle.

     Every tab of the app shares one clock through localStorage, so
     working in one tab keeps the others signed in. Storage can be absent
     or throw (a private window, blocked site data); each tab then keeps
     its own clock, which is still correct for that tab.

     Wall-clock time, not a count of ticks: a laptop that slept through
     the twenty minutes signs out the moment it wakes. --}}
<div
    x-data="{
        idleMs: {{ $idleMinutes }} * 60000,
        warnMs: 60000,
        key: 'ldi:last-activity',
        lastActivity: Date.now(),
        lastWritten: 0,
        secondsLeft: 60,
        warning: false,
        leaving: false,
        timer: null,

        init() {
            {{-- Ignored while the warning is up, or reaching for one of its
                 buttons would move the mouse and take the warning away. --}}
            this.onActivity = () => this.warning || this.touch();

            ['mousemove', 'keydown', 'pointerdown', 'scroll', 'touchstart', 'wheel'].forEach((name) =>
                window.addEventListener(name, this.onActivity, { passive: true, capture: true }));

            this.touch(true);
            this.timer = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            clearInterval(this.timer);

            ['mousemove', 'keydown', 'pointerdown', 'scroll', 'touchstart', 'wheel'].forEach((name) =>
                window.removeEventListener(name, this.onActivity, { capture: true }));
        },

        touch(force = false) {
            this.lastActivity = Date.now();
            this.warning = false;

            {{-- Written at most every five seconds: scrolling fires dozens of
                 events a second, and each write wakes every other tab. --}}
            if (force || this.lastActivity - this.lastWritten > 5000) {
                this.lastWritten = this.lastActivity;

                try { localStorage.setItem(this.key, String(this.lastActivity)); } catch (e) {}
            }
        },

        sharedLastActivity() {
            let stored = 0;

            try { stored = Number(localStorage.getItem(this.key)) || 0; } catch (e) {}

            return Math.max(this.lastActivity, stored);
        },

        tick() {
            if (this.leaving) {
                return;
            }

            const left = this.sharedLastActivity() + this.idleMs - Date.now();

            this.warning = left <= this.warnMs;
            this.secondsLeft = Math.max(0, Math.ceil(left / 1000));

            if (left <= 0) {
                this.signOut();
            }
        },

        signOut() {
            this.leaving = true;

            {{-- Only the first tab to get here posts the logout. The rest just
                 go to the login form: their CSRF token dies with the
                 session, and posting it would land them on a 419 page. --}}
            let already = false;

            try {
                already = Date.now() - (Number(localStorage.getItem('ldi:idle-signed-out')) || 0) < 10000;
                localStorage.setItem('ldi:idle-signed-out', String(Date.now()));
            } catch (e) {}

            if (already) {
                window.location.href = '{{ route('login') }}';

                return;
            }

            this.$refs.logout.submit();
        },
    }"
>
    <form x-ref="logout" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
        <input type="hidden" name="reason" value="idle">
    </form>

    <div x-show="warning" style="display: none" x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4"
        role="alertdialog" aria-modal="true" aria-labelledby="idle-logout-heading" aria-describedby="idle-logout-text">
        <div class="w-full max-w-md space-y-6 rounded-xl bg-white p-6 shadow-lg dark:bg-zinc-800">
            <div class="space-y-2">
                <flux:heading size="lg" id="idle-logout-heading">{{ __('Still there?') }}</flux:heading>

                <flux:text id="idle-logout-text">
                    {{ __('You have been inactive for a while. For your security, you will be signed out in') }}
                    <span class="font-semibold tabular-nums text-zinc-800 dark:text-white" x-text="secondsLeft + ' s'"></span>.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:button variant="ghost" x-on:click="signOut()">{{ __('Log out now') }}</flux:button>

                <flux:button variant="primary" x-on:click="touch(true)">{{ __('Stay signed in') }}</flux:button>
            </div>
        </div>
    </div>
</div>
