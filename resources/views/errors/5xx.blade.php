{{-- Never the exception's own message: in production it carries whatever
     broke, a database error included. The details go to the log. --}}
<x-layouts::error :code="$exception->getStatusCode()" :title="__('Something went wrong')">
    {{ __("The fault is on the system's side, not yours. Try again in a moment, and if it keeps happening, tell HR what you were doing when it did.") }}
</x-layouts::error>
