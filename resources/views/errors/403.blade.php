@php
    // A reason written for people, such as "Your account is not linked to an
    // employee record." The framework's stock wording says no more than the
    // title does, so it gives way to a plain explanation.
    $reason = $exception->getMessage();

    if (in_array($reason, ['', 'Forbidden', 'This action is unauthorized.'], true)) {
        $reason = __("Your account doesn't have permission to open this. If you think it should, ask HR.");
    }
@endphp

<x-layouts::error code="403" :title="__('You don\'t have access')">
    {{ $reason }}
</x-layouts::error>
