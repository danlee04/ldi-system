@php
    // Still locked out? Only the email is known on this GET, as old input
    // from the attempt that tripped the lock.
    $lockedSeconds = old('email')
        ? app(\App\Actions\Fortify\FailedLoginLimiter::class)->secondsLockedFor(request(), (string) old('email'))
        : 0;
@endphp

<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Welcome back')" :description="__('Use the account HR set up for you.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        {{-- While the email is locked, the password and the button are shut
             and a clock counts down to when they open again. Editing the
             email opens them at once: the lock is on that one account, and a
             colleague at the same PC is not locked out with it. --}}
        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6"
            x-data="{
                left: {{ $lockedSeconds }},
                timer: null,
                init() {
                    if (this.left > 0) {
                        this.timer = setInterval(() => { if (--this.left <= 0) { this.unlock(); } }, 1000);
                    }
                },
                unlock() {
                    this.left = 0;
                    clearInterval(this.timer);
                },
                get clock() {
                    return Math.floor(this.left / 60) + ':' + String(this.left % 60).padStart(2, '0');
                },
            }">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                :autofocus="$lockedSeconds === 0"
                autocomplete="email"
                placeholder="email@example.com"
                x-on:input="unlock()"
            />

            <!-- Password -->
            <div class="space-y-2">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                    :disabled="$lockedSeconds > 0"
                    x-bind:disabled="left > 0"
                />

                <flux:text size="sm" x-show="left > 0" @style(['display: none' => $lockedSeconds === 0])>
                    {{ __('You can try again in') }} <span class="font-semibold tabular-nums" x-text="clock"></span>.
                </flux:text>
            </div>

            {{-- No "Remember me": its cookie signs a closed browser back in for
                 weeks, past the 20-minute idle sign-out, on PCs the office shares.
                 No "Forgot your password?" either: the Center sends no mail, so
                 HR sets a new one in Setup → Users. --}}

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button"
                    :disabled="$lockedSeconds > 0" x-bind:disabled="left > 0">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
