{{-- Any client error without a page of its own, such as a 405 from a link
     to an address that only takes a form. --}}
<x-layouts::error :code="$exception->getStatusCode()" :title="__('That request didn\'t work')">
    {{ __('The system could not complete it. Go back and try again, or start over from the dashboard.') }}
</x-layouts::error>
