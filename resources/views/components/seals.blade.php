{{-- Whichever seals the office has put in public/images, in the order they
     are worn. The sign-in page and the error pages both carry them, so a
     seal dropped in once turns up on both. --}}
@php
    $seals = collect(['bagong-pilipinas.png', 'doh.png', 'logo.png'])
        ->filter(fn (string $file): bool => file_exists(public_path('images/'.$file)))
        ->map(fn (string $file): string => asset('images/'.$file));
@endphp

@if ($seals->isNotEmpty())
    <div {{ $attributes->class('flex items-center gap-4') }}>
        @foreach ($seals as $seal)
            <img src="{{ $seal }}" alt="" class="h-12 w-auto object-contain" />
        @endforeach
    </div>
@endif
