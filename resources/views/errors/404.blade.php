{{-- Never the exception's own message: for a missing record it names the
     model and the id that was asked for. --}}
<x-layouts::error code="404" :title="__('Page not found')">
    {{ __('The link may be wrong, or what it pointed to has been removed.') }}
</x-layouts::error>
