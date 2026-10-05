{{-- Almost always a page left open past the session and then sent, such as
     the sign-in form opened before lunch and filled in after it. --}}
<x-layouts::error code="419" :title="__('This page expired')" :action-label="__('Sign in again')" :action-href="route('login')">
    {{ __('It was open longer than a session lasts (:minutes minutes), so what you sent could not be accepted. Sign in again, then redo the last step.', [
        'minutes' => config('session.lifetime'),
    ]) }}
</x-layouts::error>
