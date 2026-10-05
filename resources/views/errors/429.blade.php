@php
    // Seconds, from the limiter that turned the request away.
    $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
@endphp

<x-layouts::error code="429" :title="__('Too many attempts')">
    @if (is_numeric($retryAfter))
        {{ __('That was tried too many times in a row. You can try again in :time.', [
            'time' => \Carbon\CarbonInterval::seconds((int) $retryAfter)->cascade()->forHumans(),
        ]) }}
    @else
        {{ __('That was tried too many times in a row. Wait a little, then try again.') }}
    @endif
</x-layouts::error>
